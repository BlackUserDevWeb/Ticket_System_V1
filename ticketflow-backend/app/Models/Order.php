<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    protected $fillable = [
        'reference', 'buyer_id', 'event_id', 'status',
        'subtotal', 'service_fee', 'extras_total', 'total', 'hold_expires_at',
    ];

    protected function casts(): array
    {
        return ['hold_expires_at' => 'datetime', 'subtotal' => 'integer', 'total' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $o) {
            if (empty($o->reference)) {
                // Référence lisible non devinable : TF-26-K3AB9Z.
                $o->reference = 'TF-' . now()->format('y') . '-' . Str::upper(Str::random(6));
            }
        });
    }

    public function buyer(): BelongsTo { return $this->belongsTo(User::class, 'buyer_id'); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function extras(): HasMany { return $this->hasMany(ExtrasOrder::class); }

    public function latestPayment(): ?Payment
    {
        return $this->payments()->latest()->first();
    }
}
