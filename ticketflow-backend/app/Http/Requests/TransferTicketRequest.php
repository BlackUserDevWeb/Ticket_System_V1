<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferTicketRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'recipient' => ['required', 'string'], // e-mail OU téléphone +228 de l'acheteur receveur
        ];
    }
}
