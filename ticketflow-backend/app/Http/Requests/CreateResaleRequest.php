<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateResaleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'ticket_code' => ['required', 'uuid'],
            'ask_price' => ['required', 'integer', 'min:500', 'max:6500000'],
            'autolist' => ['sometimes', 'boolean'],
        ];
    }
}
