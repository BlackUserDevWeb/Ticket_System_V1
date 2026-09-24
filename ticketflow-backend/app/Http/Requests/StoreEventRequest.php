<?php

namespace App\Http\Requests;

use App\Models\Event;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Étape 1 du formulaire multi-étapes de création d'événement (Angular organisateur) :
 * informations générales. Les étapes suivantes (lieu, plan, tarifs) passent par des PATCH dédiés.
 */
class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOrganizer() ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:160'],
            'category' => ['required', 'in:' . implode(',', Event::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:8000'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'sales_open_at' => ['nullable', 'date'],
            'sales_close_at' => ['nullable', 'date', 'before:starts_at'],
            'lineup' => ['nullable', 'array', 'max:30'],
            'lineup.*' => ['string', 'max:80'],
            'seated_viewing' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
