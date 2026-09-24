<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    protected $fillable = ['user_id', 'favoritable_type', 'favoritable_id', 'name'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function favoritable() { return $this->morphTo(); }
}
