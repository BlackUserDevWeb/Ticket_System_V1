<?php

namespace App\Services;

use App\Models\Event;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\TicketUnit;
use App\Services\Payment\PaymentProviderManager;
use App\Services\QrCode\QrCodeService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * CheckoutService — cœur transactionnel de l'achat.
 *
 * Deux temps :
 *   1. holdSeats()   : bloque des sièges 5 min (statut held + TTL Redis doublure)
 *   2. confirmOrder(): après paiement réussi → reserved/sold + tickets + QR + email
 *
 * ANTI-OVERSELLING : toutes les mutations de sièges passent par une transaction SQL
 * avec lockForUpdate() (SELECT ... FOR UPDATE). Les deux requêtes concurrentes sur le
 * même siège sont sérialisées par MySQL ; la seconde ne trouve plus le siège "open"
 * et échoue proprement (RuntimeException traduite en HTTP 409 côté contrôleur).
 */
class CheckoutService
{
    public function __construct(
        private TicketScoringService $scoring,
        private DynamicPricingEngine $pricing,
        private QrCodeService $qr,
        private NotificationService $notifications,
    ) {}

    /**
     * Étape "hold" : verrouille temporairement les sièges demandés.
     * Retourne ['hold_id', 'expires_at', 'lines' => [...prix affichés...]].
     */
    public function holdSeats(Event $event, array $unitIds, int $buyerId): array
    {
        $unitIds = collect($unitIds)->unique()->values();
        if ($unitIds->isEmpty() || $unitIds->count() > 12) {
            throw new RuntimeException('Sélection invalide.');
        }

        return DB::transaction(function () use ($event, $unitIds, $buyerId) {
            // Tri par id : ordre de verrouillage cohérent partout → évite les interblocages.
            $units = TicketUnit::query()
                ->whereIn('id', $unitIds)
                ->where('event_id', $event->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($units->count() !== $unitIds->count()) {
                throw new RuntimeException('Certains sièges ne sont plus disponibles.');
            }
            foreach ($units as $u) {
                if ($u->status !== TicketUnit::STATUS_OPEN) {
                    throw new RuntimeException("Le siège {$u->label} vient d'être réservé.");
                }
            }

            // Limite par utilisateur anti-reventeurs : nb de billets déjà achetés sur l'événement.
            $type = $units->first()->ticketType;
            $alreadyBought = TicketUnit::query()
                ->join('order_items', 'order_items.ticket_unit_id', '=', 'ticket_units.id')
                ->join('orders', function ($q) use ($buyerId, $event) {
                    $q->on('orders.id', '=', 'order_items.order_id')
                      ->where('orders.buyer_id', $buyerId)
                      ->where('orders.event_id', $event->id)
                      ->whereIn('orders.status', ['pending', 'paid']);
                })
                ->count();
            if ($alreadyBought + $units->count() > (int) $type->per_user_limit) {
                throw new RuntimeException("Limite de {$type->per_user_limit} billets par personne atteinte.");
            }

            $expiresAt = now()->addSeconds(config('ticketflow.hold_seconds'));
            $units->each(function (TicketUnit $u) use ($expiresAt) {
                $u->update(['status' => TicketUnit::STATUS_HELD, 'held_until' => $expiresAt]);
            });

            // Doublure Redis : si le worker SQL de libération lag, le TTL expire la clé
            // et le front peut retirer visuellement le hold. (Source de vérité = SQL.)
            $holdId = 'H' . bin2hex(random_bytes(8));
            cache()->put("hold:{$holdId}", [
                'buyer_id' => $buyerId,
                'event_id' => $event->id,
                'unit_ids' => $units->pluck('id')->all(),
            ], $expiresAt);

            return [
                'hold_id' => $holdId,
                'expires_at' => $expiresAt->toIso8601String(),
                'lines' => $units->map(fn (TicketUnit $u) => [
                    'unit_id' => $u->id,
                    'label' => $u->label,
                    'section' => $u->section?->name,
                    'price_displayed' => $this->scoring->displayedPrice($u),
                ])->all(),
            ];
        });
    }

    /** Libère un hold (annulation explicite ou expiration planifiée). */
    public function releaseHold(string $holdId): void
    {
        $payload = cache()->pull("hold:{$holdId}");
        if (!$payload) {
            return;
        }
        TicketUnit::query()
            ->whereIn('id', $payload['unit_ids'])
            ->where('status', TicketUnit::STATUS_HELD)
            ->update(['status' => TicketUnit::STATUS_OPEN, 'held_until' => null]);
    }

    /**
     * Crée la commande (statut pending) adossée à un hold valide.
     * Les prix sont RE-VÉRIFIÉS ici : le client ne peut pas trafiquer les montants.
     */
    public function createOrderFromHold(int $buyerId, string $holdId, array $extras = []): \App\Models\Order
    {
        $payload = cache()->get("hold:{$holdId}");
        if (!$payload || $payload['buyer_id'] !== $buyerId) {
            throw new RuntimeException('Hold expiré, veuillez réessayer.');
        }

        return DB::transaction(function () use ($payload, $buyerId, $extras) {
            $units = TicketUnit::query()
                ->whereIn('id', $payload['unit_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            // Vérification que le hold appartient bien au buyer dans la table aussi.
            foreach ($units as $u) {
                if ($u->status !== TicketUnit::STATUS_HELD) {
                    throw new RuntimeException('Conflit de réservation, réessayez.');
                }
            }

            $subtotal = 0;
            $serviceFee = 0;
            $itemRows = [];
            foreach ($units as $u) {
                $net = $u->price_override ?? $this->pricing->currentNetPrice($u->ticketType);
                $fee = \App\Support\Money::applyRate($net, config('ticketflow.service_rate'));
                $subtotal += $net;
                $serviceFee += $fee;
                $itemRows[] = ['unit' => $u, 'net' => $net, 'fee' => $fee];
            }

            // Extras (parking, boissons…) : prix lus en base, jamais depuis le client.
            $extrasTotal = 0;
            $extraRows = [];
            foreach ($extras as $line) {
                $extra = \App\Models\EventExtra::query()
                    ->where('event_id', $payload['event_id'])
                    ->whereKey($line['id'])
                    ->lockForUpdate()
                    ->first();
                $qty = max(1, min(10, (int) ($line['quantity'] ?? 1)));
                if ($extra && $extra->stock - $extra->sold >= $qty) {
                    $extraRows[] = ['extra' => $extra, 'qty' => $qty];
                    $extrasTotal += $extra->price * $qty;
                }
            }

            $order = \App\Models\Order::create([
                'buyer_id' => $buyerId,
                'event_id' => $payload['event_id'],
                'status' => 'pending',
                'subtotal' => $subtotal,
                'service_fee' => $serviceFee,
                'extras_total' => $extrasTotal,
                'total' => $subtotal + $serviceFee + $extrasTotal,
                'hold_expires_at' => now()->addSeconds(config('ticketflow.hold_seconds')),
            ]);

            foreach ($itemRows as $row) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'ticket_unit_id' => $row['unit']->id,
                    'ticket_type_id' => $row['unit']->ticket_type_id,
                    'unit_price_net' => $row['net'],
                    'unit_fee' => $row['fee'],
                    'quantity' => 1,
                ]);
            }
            foreach ($extraRows as $row) {
                $row['extra']->increment('sold', $row['qty']);
                \App\Models\ExtrasOrder::create([
                    'order_id' => $order->id,
                    'event_extra_id' => $row['extra']->id,
                    'quantity' => $row['qty'],
                    'unit_price' => $row['extra']->price,
                ]);
            }

            return $order;
        });
    }

    /**
     * Confirmation post-paiement webhook : marks units sold, crée les tickets signés,
     * incrémente les compteurs, journalise la commission, notifie.
     * Idempotent : safe si le webhook est rejoué par l'opérateur.
     */
    public function finalizePaidOrder(\App\Models\Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Verrou de rangée sur la commande → un seul webhook gagne la finalisation.
            $fresh = \App\Models\Order::whereKey($order->id)->lockForUpdate()->first();
            if ($fresh->status === 'paid') {
                return; // déjà finalisée (rejeu webhook)
            }

            foreach ($fresh->items()->with('unit.ticketType')->get() as $item) {
                $unit = $item->unit;
                $unit->update(['status' => TicketUnit::STATUS_SOLD, 'held_until' => null]);
                $unit->ticketType->increment('sold');

                $uuid = (string) \Illuminate\Support\Str::uuid();
                \App\Models\Ticket::create([
                    'order_item_id' => $item->id,
                    'owner_id' => $fresh->buyer_id,
                    'ticket_unit_id' => $unit->id,
                    'code' => $uuid,
                    'qr_signature' => $this->qr->signatureFor($uuid),
                    'status' => 'active',
                ]);
            }

            $fresh->update(['status' => 'paid']);

            // Comptabilité : commission TicketFlow prélevée automatiquement sur la vente.
            $commissionRate = $this->commissionRateFor($fresh);
            \App\Models\LedgerEntry::create([
                'user_id' => $fresh->event->organizer_id,
                'kind' => 'commission',
                'amount_xof' => \App\Support\Money::applyRate((int) $fresh->subtotal, $commissionRate),
                'subject_type' => $fresh->getMorphClass(),
                'subject_id' => $fresh->id,
                'description' => "Commission vente {$fresh->reference}",
            ]);
            \App\Models\LedgerEntry::create([
                'user_id' => null,
                'kind' => 'service_fee',
                'amount_xof' => (int) $fresh->service_fee,
                'subject_type' => $fresh->getMorphClass(),
                'subject_id' => $fresh->id,
                'description' => "Frais de service {$fresh->reference}",
            ]);

            // Signal pour la tarification dynamique + temps réel (Reverb canal event.{id}).
            foreach ($fresh->items as $item) {
                $this->pricing->recordSale($item->ticketType);
            }
            broadcast_event(new \App\Events\SeatMapUpdated($fresh->event_id));
            $this->notifications->orderConfirmed($fresh);
        });
    }

    /** Commission effective = taux du plan organisateur, sinon taux global. */
    private function commissionRateFor(\App\Models\Order $order): float
    {
        $global = (float) config('ticketflow.commission_rate');
        $sub = $order->event->organizer?->subscription;
        return $sub && $sub->isUsable()
            ? $sub->plan->commissionRate($global)
            : $global;
    }
}
