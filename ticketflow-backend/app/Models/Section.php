<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    protected $fillable = ['event_id', 'name', 'quality_score', 'shape', 'panorama_360_path'];
    protected function casts(): array { return ['shape' => 'array', 'quality_score' => 'integer']; }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function units(): HasMany { return $this->hasMany(TicketUnit::class); }
}
