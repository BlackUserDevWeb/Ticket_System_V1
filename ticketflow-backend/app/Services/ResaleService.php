<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Resale;
use App\Models\Ticket;
use App\Models\TicketUnit;
use App\Services\QrCode\QrCodeService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ResaleService — revente classique et Autolist (modèle TickPick).
 *
 * Autolist : le vendeur fixe un prix une seule fois ; quand un acheteur se présente,
 * purchase() transfère le ticket ET déclenche le débit Mobile Money automatiquement —
 * le vendeur n'a plus aucune intervention à faire. Le ticket est marqué "transferred"
 * côté vendeur, "active" côté acheteur, avec traçabilité complète (ticket_transfers).
 */
class ResaleService
{
    public function __construct(private QrCodeService $qr, private NotificationService $notifications) {}

    /** Met un ticket en vente (ou en Autolist). Empêche la revente au-dessus de +30 % du prix d'origine. */
    public function list(Ticket $ticket, int $askPrice, bool $autolist): Resale
    {
        if ($ticket->status !== 'active') {
            throw new RuntimeException('Ce ticket ne peut pas être revendu.');
        }
        if ($ticket->resales()->where('status', 'listed')->exists()) {
            throw new RuntimeException('Ce ticket est déjà en vente.');
        }

        // Plafond anti-spéculation : +30 % max du prix d'achat affiché initialement.
        $cap = (int) round($ticket->orderItem->unit_price_net * (1 + config('ticketflow.service_rate')) * 1.3);
        if ($askPrice > $cap) {
            throw new RuntimeException('Prix de revente trop élevé (max +30 % du prix d\'achat).');
        }

        $resale = Resale::create([
            'ticket_id' => $ticket->id,
            'seller_id' => $ticket->owner_id,
            'event_id' => $ticket->unit->event_id,
            'ask_price' => $askPrice,
            'autolist' => $autolist,
            'status' => 'listed',
        ]);

        // Notifie les abonnés "alerte revente" de cet événement.
        $listeners = $resale->event->organizer ? [] : []; // (les favoris sont résolus dans le Job dédié)
        \App\Jobs\NotifyResaleListed::dispatch($resale->id)->onQueue('notifications');

        return $resale;
    }

    /**
     * Achat d'une annonce de revente — transaction atomique :
     * verrou resale → verrou ticket → transfert de propriété → commande miroir pending.
     * Le paiement Mobile Money suit le même parcours qu'un achat normal (webhook finalize).
     */
    public function purchase(Resale $resale, int $buyerId, string $provider, string $phone): Order
    {
        return DB::transaction(function () use ($resale, $buyerId, $provider, $phone) {
            $locked = Resale::whereKey($resale->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'listed') {
                throw new RuntimeException('Cette place vient d\'être vendue.');
            }
            if ($locked->seller_id === $buyerId) {
                throw new RuntimeException('Vous ne pouvez pas acheter votre propre annonce.');
            }

            $ticket = Ticket::whereKey($locked->ticket_id)->lockForUpdate()->firstOrFail();
            if ($ticket->status !== 'active') {
                $locked->update(['status' => 'cancelled']);
                throw new RuntimeException('Ticket non disponible.');
            }

            // --- Création de la commande miroir (montant = prix demandé, frais intégrés offerts
            // par TicketFlow sur la revente P2P : la commission se prend sur le vendeur).
            $commission = (int) round($locked->ask_price * config('ticketflow.commission_rate'));
            $order = Order::create([
                'buyer_id' => $buyerId,
                'event_id' => $locked->event_id,
                'status' => 'pending',
                'subtotal' => $locked->ask_price - $commission,
                'service_fee' => $commission,
                'extras_total' => 0,
                'total' => $locked->ask_price,
                'hold_expires_at' => now()->addSeconds(config('ticketflow.hold_seconds')),
            ]);

            // Ligne de commande miroir : unit_id conservé (le siège suit le ticket),
            // meta = {ticket_id, resale_id} pour la finalisation post-webhook.
            \App\Models\OrderItem::create([
                'order_id' => $order->id,
                'ticket_unit_id' => $ticket->ticket_unit_id,
                'ticket_type_id' => $ticket->unit->ticket_type_id,
                'unit_price_net' => $locked->ask_price - $commission,
                'unit_fee' => $commission,
                'quantity' => 1,
                'meta' => ['ticket_id' => $ticket->id, 'resale_id' => $locked->id],
            ]);

            $payment = Payment::create([
                'order_id' => $order->id,
                'provider' => $provider,
                'payer_phone' => $phone,
                'amount' => $order->total,
                'fee' => $commission,
                'status' => 'initiated',
            ]);

            $locked->update(['buyer_id' => $buyerId, 'order_id' => $order->id]);

            // Le ticket est "gelé" pendant le paiement : ni utilisable, ni revendable.
            $ticket->update(['status' => 'transferred']);

            \App\Jobs\StartResalePayment::dispatch($payment->id)->onQueue('payments');

            return $order;
        });
    }

    /** Finalisation après webhook success : attribue réellement le ticket à l'acheteur. */
    public function finalizePurchase(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $fresh = Order::whereKey($order->id)->lockForUpdate()->first();
            if ($fresh->status === 'paid') {
                return;
            }
            $item = $fresh->items()->lockForUpdate()->first();
            $ticket = Ticket::whereKey($item->meta['ticket_id'])->lockForUpdate()->first();
            $resale = Resale::whereKey($item->meta['resale_id'])->lockForUpdate()->first();

            // Transfert de propriété tracé.
            \App\Models\TicketTransfer::create([
                'ticket_id' => $ticket->id,
                'from_user_id' => $resale->seller_id,
                'to_user_id' => $fresh->buyer_id,
                'accepted_at' => now(),
            ]);
            $ticket->update([
                'owner_id' => $fresh->buyer_id,
                'status' => 'active',
                'transfer_depth' => $ticket->transfer_depth + 1,
            ]);
            $resale->update(['status' => 'sold']);
            $fresh->update(['status' => 'paid']);

            // Comptabilité : commission revente prélevée sur le vendeur.
            \App\Models\LedgerEntry::create([
                'user_id' => $resale->seller_id,
                'kind' => 'commission',
                'amount_xof' => (int) $fresh->service_fee,
                'subject_type' => $resale->getMorphClass(),
                'subject_id' => $resale->id,
                'description' => "Commission revente Autolist #{$resale->id}",
            ]);
            // Payout vendeur (virement Flooz/Yas planifié par le cron PayoutOrganizers).
            \App\Models\LedgerEntry::create([
                'user_id' => $resale->seller_id,
                'kind' => 'payout',
                'amount_xof' => -(int) ($fresh->total - $fresh->service_fee),
                'subject_type' => $resale->getMorphClass(),
                'subject_id' => $resale->id,
                'description' => "Reversement revente #{$resale->id}",
            ]);

            $this->notifications->inApp(
                $resale->seller,
                'resale_sold',
                'Votre ticket a été vendu',
                \App\Support\Money::format($resale->ask_price - $fresh->service_fee) . ' seront versés sur votre compte Mobile Money.'
            );
        });
    }
}
