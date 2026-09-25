<?php

namespace App\Models;

use App\Services\PricingEngine;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id', 'name', 'kind', 'base_price', 'price_displayed',
        'quantity_total', 'quantity_sold', 'quantity_available', 'is_active',
        'autolist_enabled', 'autolist_min_price', 'sales_start_at', 'sales_end_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'autolist_enabled' => 'boolean',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function isOnSale(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->sales_start_at && $this->sales_start_at->isFuture()) {
            return false;
        }
        if ($this->sales_end_at && $this->sales_end_at->isPast()) {
            return false;
        }

        return $this->quantity_available > 0;
    }

    /**
     * Prix affiché à l'acheteur (frais de service inclus, non détaillés).
     * Applique la tarification dynamique si activée sur l'événement.
     */
    public function effectivePrice(): int
    {
        if ($this->event?->dynamic_pricing_enabled) {
            return app(PricingEngine::class)->dynamicPrice($this);
        }

        return (int) $this->price_displayed;
    }

    /**
     * Score qualité/prix inspiré du Score Report de TickPick (0 à 10).
     * Bas sur le rapport prix / confort perçu (kind) et le remplissage restant.
     */
    public function valueScore(): float
    {
        return app(PricingEngine::class)->valueScore($this);
    }
}
