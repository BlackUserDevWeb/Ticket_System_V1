<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vue de page événement — alimente l'entonnoir de conversion du dashboard organisateur. */
class EventView extends Model
{
    protected $fillable = ['event_id', 'user_id', 'source', 'viewed_at'];
    protected function casts(): array { return ['viewed_at' => 'datetime']; }

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
}
