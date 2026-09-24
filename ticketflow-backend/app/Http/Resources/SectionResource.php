<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quality_score' => $this->quality_score,
            'shape' => $this->shape,
            // URL du panorama 360° consommé par le viewer Vanilla JS.
            'panorama_360_url' => $this->panorama_360_path ? url('storage/' . $this->panorama_360_path) : null,
        ];
    }
}
