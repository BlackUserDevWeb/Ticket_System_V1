<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Payload minimal des listes marketplace (accueil, recherche) — pensé pour les cartes React. */
class EventSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'category' => $this->category,
            'image_url' => $this->image_path ? url('storage/' . $this->image_path) : null,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'city' => $this->venue?->city?->name,
            'venue' => $this->venue?->name,
            'price_from' => $this->when(
                isset($this->min_price_displayed),
                (int) $this->min_price_displayed
            ),
            // Badge sponsorisé discret (modèle économique n°2).
            'sponsored' => $this->sponsored_until && $this->sponsored_until->isFuture(),
            'almost_full' => (bool) ($this->fill_ratio ?? false),
            'organizer' => ['id' => $this->organizer_id, 'name' => $this->organizer?->name],
        ];
    }
}
