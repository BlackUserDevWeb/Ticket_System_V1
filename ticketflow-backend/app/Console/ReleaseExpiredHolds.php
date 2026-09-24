<?php

namespace App\Console;

use App\Models\Order;
use App\Models\TicketUnit;
use Illuminate\Console\Command;

/**
 * Cron (chaque minute) : libère les sièges dont le hold de 5 min a expiré sans paiement.
 * Double sécurité avec le TTL Redis : la base reste la source de vérité.
 */
class ReleaseExpiredHolds extends Command
{
    protected $signature = 'ticketflow:release-holds';
    protected $description = 'Libère les holds de sièges expirés et expire les commandes pending associées';

    public function handle(): int
    {
        // 1) Sièges en hold expiré → open.
        $released = TicketUnit::expiredHold()
            ->update(['status' => TicketUnit::STATUS_OPEN, 'held_until' => null]);

        // 2) Commandes pending dont hold_expires_at est dépassé → expired.
        Order::where('status', 'pending')
            ->where('hold_expires_at', '<', now())
            ->chunkById(200, function ($orders) {
                foreach ($orders as $order) {
                    // Rend les sièges réservés à la vente.
                    TicketUnit::whereIn('id', $order->items()->pluck('ticket_unit_id'))
                        ->whereIn('status', [TicketUnit::STATUS_HELD, TicketUnit::STATUS_RESERVED])
                        ->update(['status' => TicketUnit::STATUS_OPEN, 'held_until' => null]);
                    $order->update(['status' => 'expired']);
                }
            });

        $this->info("Holds libérés : {$released}");
        return self::SUCCESS;
    }
}
