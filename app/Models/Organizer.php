<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organizer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'company_name', 'phone', 'city', 'description',
        'logo_path', 'brand_color', 'status', 'subscription_plan', 'subscription_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'subscription_expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Limites du plan d'abonnement (nombre max d'événements actifs).
     */
    public function eventLimit(): int
    {
        return match ($this->subscription_plan) {
            'starter' => 2,
            'pro' => 15,
            'enterprise' => PHP_INT_MAX,
            default => 2,
        };
    }

    public function hasDynamicPricing(): bool
    {
        return in_array($this->subscription_plan, ['pro', 'enterprise'], true);
    }

    public function canCreateEvent(): bool
    {
        return $this->events()->whereIn('status', ['draft', 'pending', 'published'])->count() < $this->eventLimit();
    }
}
