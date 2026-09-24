<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'provider', 'payer_phone', 'amount', 'fee', 'status',
        'provider_reference', 'failure_reason', 'webhook_payload', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return ['webhook_payload' => 'array', 'confirmed_at' => 'datetime', 'amount' => 'integer'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    /** Les statuts terminaux ne peuvent plus être transitionnés (idempotence webhook). */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['success', 'failed', 'expired', 'refunded'], true);
    }
}
