<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HoldSeatsRequest extends FormRequest
{
    public function authorize(): bool { return true; } // policy vérifiée dans le contrôleur

    public function rules(): array
    {
        return [
            'unit_ids' => ['required', 'array', 'min:1', 'max:12'],
            'unit_ids.*' => ['integer', 'distinct'],
        ];
    }
}
