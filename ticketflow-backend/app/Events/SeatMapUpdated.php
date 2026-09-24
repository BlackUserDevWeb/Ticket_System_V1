<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusé sur le canal public `event.{id}` à chaque changement d'état de siège
 * (hold, vente, libération). Le plan de salle React s'y abonne pour la mise à jour
 * temps réel. ShouldBroadcastNow : pas de file, la latence prime sur le débit.
 */
class SeatMapUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $eventId) {}

    public function broadcastOn(): array
    {
        return [new Channel("event.{$this->eventId}")];
    }

    public function broadcastAs(): string
    {
        return 'seatmap.updated';
    }
}
