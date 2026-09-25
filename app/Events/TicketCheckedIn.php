<?php

namespace App\Events;

use App\Models\Ticket;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Check-in effectué à l'entrée (en direct ou synchronisé après une session hors ligne).
 */
class TicketCheckedIn implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Ticket $ticket) {}

    public function broadcastOn(): array
    {
        return [new Channel("organizer.{$this->ticket->order->event->organizer_id}")];
    }

    public function broadcastAs(): string
    {
        return 'checkin.created';
    }

    public function broadcastWith(): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'event_id' => $this->ticket->order->event_id,
            'owner' => $this->ticket->owner->name,
        ];
    }
}
