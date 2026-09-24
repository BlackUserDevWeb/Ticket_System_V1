<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'city_id', 'capacity', 'address', 'lat', 'lng', 'layout'];
    protected function casts(): array { return ['layout' => 'array']; }

    public function city(): BelongsTo { return $this->belongsTo(City::class); }
    public function events(): HasMany { return $this->hasMany(Event::class); }
}
