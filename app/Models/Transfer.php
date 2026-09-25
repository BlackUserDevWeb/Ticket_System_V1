<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transfer extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'from_user_id', 'to_user_id', 'token', 'status'];

    protected static function booted(): void
    {
        static::creating(function (Transfer $transfer) {
            $transfer->token ??= Str::random(48);
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
