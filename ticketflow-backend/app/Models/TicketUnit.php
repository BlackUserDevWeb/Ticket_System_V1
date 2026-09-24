<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Unité vendable = siège numéroté (ou unité générique en placement libre).
 * Granularité du verrouillage anti-overselling : SELECT ... FOR UPDATE dans CheckoutService.
 */
class TicketUnit extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_HELD = 'held';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_SOLD = 'sold';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'event_id', 'ticket_type_id', 'section_id', 'label', 'row_number', 'seat_number',
        'pos_x', 'pos_y', 'view_score', 'price_override', 'status', 'held_until',
    ];

    protected function casts(): array
    {
        return ['pos_x' => 'float', 'pos_y' => 'float', 'held_until' => 'datetime', 'price_override' => 'integer'];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function ticketType(): BelongsTo { return $this->belongsTo(TicketType::class); }
    public function section(): BelongsTo { return $this->belongsTo(Section::class); }
    public function ticket(): HasOne { return $this->hasOne(Ticket::class); }

    public function scopeOpen(Builder $q): Builder { return $q->where('status', self::STATUS_OPEN); }

    /** Holds expirés : libérés par la commande planifiée ReleaseExpiredHolds. */
    public function scopeExpiredHold(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_HELD)->where('held_until', '<', now());
    }

    /** Prix NET effectif (override premium sinon tarif de base, hors tarification dynamique). */
    public function netPrice(): int
    {
        return $this->price_override ?? $this->ticketType->base_price;
    }
}
