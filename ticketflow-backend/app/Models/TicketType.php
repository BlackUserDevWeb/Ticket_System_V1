<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    protected $fillable = [
        'event_id', 'section_id', 'name', 'base_price', 'max_price',
        'stock', 'sold', 'per_user_limit', 'pricing_rules', 'is_active',
    ];

    protected function casts(): array
    {
        return ['pricing_rules' => 'array', 'is_active' => 'boolean', 'base_price' => 'integer'];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function section(): BelongsTo { return $this->belongsTo(Section::class); }
    public function units(): HasMany { return $this->hasMany(TicketUnit::class); }

    public function availableCount(): int
    {
        return max(0, $this->stock - $this->sold);
    }
}
