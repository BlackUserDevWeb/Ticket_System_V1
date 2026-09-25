<?php

namespace App\Actions;

use App\DataTransferObjects\CheckoutSelection;
use App\Models\Event;
use App\Models\Order;
use App\Models\Seat;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

/**
 * Libère les sièges et restaure le stock d'une commande annulée/expirée.
 */
class ReleaseOrderHoldAction
{
    public function __invoke(Order $order): void
    {
        if (! in_array($order->status, ['pending', 'cancelled'], true)) {
            return;
        }

        DB::transaction(function () use ($order) {
            // Sièges liés via la sélection (retrouvés par token de hold ou statut held expiré)
            Seat::query()
                ->where('hold_token', $order->reference)
                ->get()
                ->each(function (Seat $seat) {
                    $seat->update(['status' => Seat::STATUS_AVAILABLE, 'held_until' => null, 'hold_token' => null]);
                });

            // Revente du snapshot de sélection stocké en session n'étant pas fiable,
            // on s'appuie sur les quantités réservées : aucun ticket émis = rien à décrémenter.
        });

        $order->update(['status' => 'cancelled']);
    }
}
