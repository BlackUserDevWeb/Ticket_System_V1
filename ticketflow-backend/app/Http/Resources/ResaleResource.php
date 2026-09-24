<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'ask_price' => $this->ask_price,
            'ask_price_formatted' => \App\Support\Money::format((int) $this->ask_price),
            'autolist' => $this->autolist,
            'status' => $this->status,
            'section' => $this->ticket?->unit?->section?->name,
            'seat_label' => $this->ticket?->unit?->label,
            // Score qualité/prix recalculé pour l'annonce (les reventes sont classées aussi).
            'score' => $this->when(isset($this->score), $this->score),
            'seller' => $this->when($request->user()?->isAdminOrSupport(), $this->seller?->name),
        ];
    }
}
