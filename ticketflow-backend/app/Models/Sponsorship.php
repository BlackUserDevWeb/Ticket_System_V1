<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Campagne de sponsoring d'un événement (mise en avant sur la page d'accueil). */
class Sponsorship extends Model
{
    protected $fillable = ['event_id', 'organizer_id', 'budget', 'starts_at', 'ends_at', 'status', 'impressions', 'clicks'];
    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function organizer(): BelongsTo { return $this->belongsTo(User::class, 'organizer_id'); }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->ends_at > now();
    }
}
