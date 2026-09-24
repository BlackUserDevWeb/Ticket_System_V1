<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventExtra extends Model
{
    protected $fillable = ['event_id', 'name', 'price', 'stock', 'sold'];

    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
}
