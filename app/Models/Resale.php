<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Resale extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 'seller_id', 'price', 'autolist', 'status', 'buyer_id', 'order_id',
    ];

    protected function casts(): array
    {
        return ['autolist' => 'boolean'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }
}
