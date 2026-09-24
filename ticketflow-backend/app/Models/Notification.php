<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Notification in-app (et relayée par e-mail via les Jobs de notification). */
class Notification extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'body', 'link', 'read_at'];
    protected function casts(): array { return ['read_at' => 'datetime']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
