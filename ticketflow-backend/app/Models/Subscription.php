<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = ['organizer_id', 'plan_id', 'status', 'renews_at', 'cancelled_at'];
    protected function casts(): array { return ['renews_at' => 'datetime', 'cancelled_at' => 'datetime']; }

    public function organizer(): BelongsTo { return $this->belongsTo(User::class, 'organizer_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(SubscriptionPlan::class, 'plan_id'); }

    public function isUsable(): bool
    {
        return in_array($this->status, ['trialing', 'active'], true);
    }
}
