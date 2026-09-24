<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool { return true; } // endpoint public + rate-limited

    /** Validation stricte : téléphone togolais format +228XXXXXXXX (identifiant Mobile Money). */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^\+228\d{8}$/', 'unique:users,phone'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'role' => ['sometimes', 'in:buyer,organizer'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Le numéro doit être au format +228XXXXXXXX.',
            'phone.unique' => 'Ce numéro est déjà associé à un compte.',
        ];
    }
}
