<?php

namespace App\Events;

use App\Models\Seat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusé via Laravel Reverb pour synchroniser l'état des sièges en temps réel
 * sur la carte interactive (channel : event.{id}).
 */
class SeatStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Seat $seat) {}

    public function broadcastOn(): array
    {
        return [new Channel("event.{$this->seat->section->event_id}")];
    }

    public function broadcastAs(): string
    {
        return 'seat.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'seat_id' => $this->seat->id,
            'status' => $this->seat->status,
            'held_until' => $this->seat->held_until?->toIso8601String(),
        ];
    }
}
