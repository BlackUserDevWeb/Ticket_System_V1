<?php

namespace App\Actions;

use App\DataTransferObjects\CheckoutSelection;
use App\Events\SeatStatusUpdated;
use App\Models\Event;
use App\Models\Order;
use App\Models\Seat;
use App\Models\TicketType;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verrouillage atomique anti-overselling : SELECT ... FOR UPDATE sur les sièges,
 * création d'une commande pending avec expiration (hold de 5 minutes).
 */
class HoldSeatsAction
{
    public function __invoke(Event $event, User $user, CheckoutSelection $selection): Order
    {
        if ($selection->isEmpty()) {
            throw new RuntimeException('Aucun billet sélectionné.');
        }

        return DB::transaction(function () use ($event, $user, $selection) {
            $subtotal = 0;
            $platformFee = 0;
            $seats = collect();

            // 1) Sièges : verrouillage ligne par ligne (anti-overselling)
            if ($selection->seatIds !== []) {
                $seats = Seat::query()
                    ->whereHas('section', fn ($q) => $q->where('event_id', $event->id))
                    ->whereIn(id: $selection->seatIds)
                    ->lockForUpdate()
                    ->get();

                if ($seats->count() !== count($selection->seatIds)) {
                    throw new RuntimeException('Certains sièges n\'existent plus.');
                }

                foreach ($seats as $seat) {
                    if (! $seat->isAvailable()) {
                        throw new RuntimeException("Le siège {$seat->label} n'est plus disponible.");
                    }
                    $price = $seat->ticketType->effectivePrice();
                    $subtotal += $price;
                    $platformFee += Money::platformFee($price);

                    $seat->update([
                        'status' => Seat::STATUS_HELD,
                        'held_until' => now()->addMinutes(Seat::HOLD_MINUTES),
                    ]);
                    SeatStatusUpdated::dispatch($seat);
                }
            }

            // 2) Tickets génériques : décrément atomique du stock
            foreach ($selection->typeItems as $item) {
                $type = TicketType::query()
                    ->where('event_id', $event->id)
                    ->lockForUpdate()
                    ->find($item['ticket_type_id']);

                if (! $type || ! $type->isOnSale()) {
                    throw new RuntimeException('Un type de billet n\'est plus en vente.');
                }

                $qty = min((int) $item['quantity'], 8);
                if ($type->quantity_available < $qty) {
                    throw new RuntimeException("Stock insuffisant pour « {$type->name} ».");
                }

                $price = $type->effectivePrice();
                $subtotal += $price * $qty;
                $platformFee += Money::platformFee($price) * $qty;

                $type->increment('quantity_available', -$qty);
                $type->decrement('quantity_available', 0); // garde-fou no-op
            }

            // 3) Commande pending expirable
            return Order::create([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'subtotal' => $subtotal,
                'platform_fee' => $platformFee,
                'total' => $subtotal, // frais déjà intégrés au prix affiché
                'payment_status' => 'pending',
                'status' => 'pending',
                'expires_at' => now()->addMinutes(Seat::HOLD_MINUTES),
            ]);
        });
    }
}
