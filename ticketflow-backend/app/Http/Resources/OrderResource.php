<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status,
            'total' => $this->total,
            'total_formatted' => \App\Support\Money::format((int) $this->total),
            'created_at' => $this->created_at->toIso8601String(),
            'event' => new EventSummaryResource($this->whenLoaded('event')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payment' => $this->whenLoaded('payments', fn () => optional($this->latestPayment())->only(
                ['provider', 'status', 'payer_phone']
            )),
        ];
    }
}
