<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InitiatePaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', Rule::exists('orders', 'id')->where('buyer_id', $this->user()->id)],
            'phone' => ['required', 'regex:/^\+228\d{8}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.exists' => 'Commande introuvable ou appartenant à un autre compte.',
            'phone.regex' => 'Numéro Mobile Money invalide (+228XXXXXXXX).',
        ];
    }
}
