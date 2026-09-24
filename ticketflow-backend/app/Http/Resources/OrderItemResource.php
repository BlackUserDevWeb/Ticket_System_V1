<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'seat_label' => $this->unit?->label,
            'section' => $this->unit?->section?->name,
            'ticket_type' => $this->ticketType?->name,
            // Prix AFFICHÉ = net + frais intégrés (jamais détaillé à l'acheteur).
            'price_displayed' => $this->unit_price_net + $this->unit_fee,
            'tickets' => TicketResource::collection($this->whenLoaded('tickets')),
        ];
    }
}
