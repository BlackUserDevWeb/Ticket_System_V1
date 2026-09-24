<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = ['name', 'region', 'lat', 'lng'];

    public function venues(): HasMany { return $this->hasMany(Venue::class); }
}
