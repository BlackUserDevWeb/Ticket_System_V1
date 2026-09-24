<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'preferences'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',          // hachage automatique par Laravel 11
            'preferences' => 'array',        // thème UI, ville préférée, réglages notifications…
            'email_verified_at' => 'datetime',
        ];
    }

    // --- Rôles (un seul rôle par compte ; autorisation via Gate/middleware) ---
    public function isOrganizer(): bool { return $this->role === 'organizer'; }
    public function isAdminOrSupport(): bool { return in_array($this->role, ['admin', 'support'], true); }

    public function events(): HasMany { return $this->hasMany(Event::class, 'organizer_id'); }
    public function orders(): HasMany { return $this->hasMany(Order::class, 'buyer_id'); }
    public function tickets(): HasMany { return $this->hasMany(Ticket::class, 'owner_id'); }
    public function favorites(): HasMany { return $this->hasMany(Favorite::class); }
    public function priceAlerts(): HasMany { return $this->hasMany(PriceAlert::class); }
    public function resales(): HasMany { return $this->hasMany(Resale::class, 'seller_id'); }
    public function subscription(): HasOne { return $this->hasOne(Subscription::class, 'organizer_id'); }
}
