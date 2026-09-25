<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Nouvelle vente confirmée — alimente les widgets temps réel du dashboard organisateur.
 */
class SaleConfirmed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Order $order) {}

    public function broadcastOn(): array
    {
        return [new Channel("organizer.{$this->order->event->organizer_id}")];
    }

    public function broadcastAs(): string
    {
        return 'sale.confirmed';
    }

    public function broadcastWith(): array
    {
        return [
            'order_reference' => $this->order->reference,
            'event_id' => $this->order->event_id,
            'total' => $this->order->total,
            'tickets' => $this->order->tickets()->count(),
        ];
    }
}
