<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'status' => $this->status,
            'seat_label' => $this->unit?->label,
            'section' => $this->unit?->section?->name,
            'event_title' => $this->unit?->event?->title,
            'event_slug' => $this->unit?->event?->slug,
            'event_starts_at' => $this->unit?->event?->starts_at?->toIso8601String(),
            // Le QR complet n'est exposé qu'à son propriétaire légitime (policy côté contrôleur).
            'qr_payload' => $this->when($request->user()?->id === $this->owner_id, $this->qrPayload()),
            'qr_image_url' => $this->when($request->user()?->id === $this->owner_id,
                url("storage/tickets/{$this->code}.png")),
            'transfer_depth' => $this->transfer_depth,
            'can_transfer' => $this->canBeTransferred(),
            'listed_for_resale' => $this->resales()->where('status', 'listed')->exists(),
        ];
    }
}
