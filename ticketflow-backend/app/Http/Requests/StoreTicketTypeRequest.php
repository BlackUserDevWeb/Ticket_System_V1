<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isOrganizer() ?? false;
    }

    public function rules(): array
    {
        return [
            'section_id' => ['nullable', 'exists:sections,id'],
            'name' => ['required', 'string', 'max:60'],
            // Montants entiers XOF ; bornes larges mais réalistes pour le marché togolais.
            'base_price' => ['required', 'integer', 'min:500', 'max:5000000'],
            'max_price' => ['nullable', 'integer', 'gt:base_price', 'max:6500000'],
            'stock' => ['required', 'integer', 'min:1', 'max:100000'],
            'per_user_limit' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'pricing_rules' => ['nullable', 'array'],
        ];
    }
}
