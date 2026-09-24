<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    protected $fillable = ['ticket_id', 'event_id', 'scanned_by', 'gate', 'synced', 'scanned_at'];
    protected function casts(): array { return ['scanned_at' => 'datetime', 'synced' => 'boolean']; }

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function scanner(): BelongsTo { return $this->belongsTo(User::class, 'scanned_by'); }
}
