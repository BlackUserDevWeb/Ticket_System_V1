<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seat extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_HELD = 'held';
    public const STATUS_SOLD = 'sold';

    public const HOLD_MINUTES = 5;

    protected $fillable = [
        'section_id', 'row_number', 'col_number', 'ticket_type_id',
        'status', 'held_until', 'hold_token',
    ];

    protected function casts(): array
    {
        return [
            'held_until' => 'datetime',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * Un siège "tenu" dont le hold a expiré est redevenu disponible.
     */
    public function isAvailable(): bool
    {
        if ($this->status === self::STATUS_SOLD) {
            return false;
        }

        return $this->status === self::STATUS_AVAILABLE
            || ($this->status === self::STATUS_HELD && $this->held_until?->isPast());
    }

    public function scopeFree(Builder $q): Builder
    {
        return $q->where(function ($w) {
            $w->where('status', self::STATUS_AVAILABLE)
                ->orWhere(fn ($x) => $x->where('status', self::STATUS_HELD)->where('held_until', '<', now()));
        });
    }

    public function getLabelAttribute(): string
    {
        return chr(64 + $this->row_number).'-'.$this->col_number;
    }
}
