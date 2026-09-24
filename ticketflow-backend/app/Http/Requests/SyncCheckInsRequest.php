<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Payload envoyé par le scanner offline quand la connexion revient. */
class SyncCheckInsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdminOrSupport()
            || $this->user()?->isOrganizer();
    }

    public function rules(): array
    {
        return [
            'event_id' => ['required', 'exists:events,id'],
            'entries' => ['required', 'array', 'min:1', 'max:500'],
            'entries.*.code' => ['required', 'uuid'],
            'entries.*.gate' => ['nullable', 'string', 'max:40'],
            'entries.*.scanned_at' => ['required', 'date'],
        ];
    }
}
