<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    protected $fillable = ['slug', 'name', 'price_monthly', 'max_events', 'features', 'is_active'];
    protected function casts(): array { return ['features' => 'array', 'is_active' => 'boolean']; }

    public function subscriptions(): HasMany { return $this->hasMany(Subscription::class, 'plan_id'); }

    /** Feature flag typé : $plan->has('dynamic_pricing'). */
    public function has(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    /** Commission négociée par plan, sinon taux global config. */
    public function commissionRate(float $default): float
    {
        return (float) ($this->features['commission_rate'] ?? $default);
    }
}
