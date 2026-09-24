<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceAlert extends Model
{
    protected $fillable = ['user_id', 'event_id', 'target_price', 'is_active', 'triggered_at'];
    protected function casts(): array { return ['is_active' => 'boolean', 'triggered_at' => 'datetime']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
}
