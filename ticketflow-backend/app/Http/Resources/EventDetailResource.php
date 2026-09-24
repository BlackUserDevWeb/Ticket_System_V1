<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Page événement complète : détails + types de billets scorés + extras + branding orga. */
class EventDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'lineup' => $this->lineup ?? [],
            'image_url' => $this->image_path ? url('storage/' . $this->image_path) : null,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'sales_open' => $this->is_sales_open,
            'seated_viewing' => $this->seated_viewing,
            'venue' => $this->whenLoaded('venue', fn () => [
                'name' => $this->venue->name,
                'city' => $this->venue->city?->name,
                'address' => $this->venue->address,
                'lat' => $this->venue->lat,
                'lng' => $this->venue->lng,
            ]),
            'organizer' => [
                'id' => $this->organizer_id,
                'name' => $this->organizer?->name,
                // Charte de l'organisateur appliquée sur la page événement (plan Pro).
                'branding' => $this->branding,
            ],
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'extras' => EventExtraResource::collection($this->whenLoaded('extras')),
            // Offres notées qualité/prix, triées par score décroissant (Score Report à la TickPick).
            'offers' => $this->when(isset($this->scored_offers), $this->scored_offers),
            'resales_count' => $this->when(isset($this->resales_listed_count), (int) $this->resales_listed_count),
        ];
    }
}
