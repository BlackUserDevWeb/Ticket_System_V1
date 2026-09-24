<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Event extends Model
{
    use HasFactory;

    /** Catégories couvrant tout le marché togolais, sans limite de taille d'événement. */
    public const CATEGORIES = ['concert', 'sport', 'theatre', 'festival', 'conference', 'prive', 'nuit'];

    protected $fillable = [
        'organizer_id', 'venue_id', 'slug', 'title', 'category', 'description', 'image_path',
        'lineup', 'starts_at', 'ends_at', 'sales_open_at', 'sales_close_at', 'status',
        'sponsored_until', 'dynamic_pricing_enabled', 'branding', 'seated_viewing',
    ];

    protected function casts(): array
    {
        return [
            'lineup' => 'array',
            'branding' => 'array',
            'starts_at' => 'datetime', 'ends_at' => 'datetime',
            'sales_open_at' => 'datetime', 'sales_close_at' => 'datetime',
            'sponsored_until' => 'datetime',
            'dynamic_pricing_enabled' => 'boolean',
            'seated_viewing' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Slug SEO généré automatiquement (ex. "concert-aymric-lome-x7b2q").
        static::creating(function (Event $e) {
            if (empty($e->slug)) {
                $base = Str::slug(Str::limit($e->title, 60, ''));
                $e->slug = $base . '-' . Str::lower(Str::random(5));
            }
        });
    }

    // --- Relations ---
    public function organizer(): BelongsTo { return $this->belongsTo(User::class, 'organizer_id'); }
    public function venue(): BelongsTo { return $this->belongsTo(Venue::class); }
    public function sections(): HasMany { return $this->hasMany(Section::class); }
    public function ticketTypes(): HasMany { return $this->hasMany(TicketType::class); }
    public function units(): HasMany { return $this->hasMany(TicketUnit::class); }
    public function extras(): HasMany { return $this->hasMany(EventExtra::class); }
    public function orders(): HasMany { return $this->hasMany(Order::class); }
    public function resales(): HasMany { return $this->hasMany(Resale::class); }
    public function views(): HasMany { return $this->hasMany(EventView::class); }
    public function checkIns(): HasMany { return $this->hasMany(CheckIn::class); }

    /** Nombre de spectateurs déjà entrés aujourd'hui (dashboard temps réel). */
    public function checkInsToday(): int
    {
        return $this->checkIns()->whereDate('scanned_at', today())->count();
    }

    // --- Scopes réutilisables (EventController@index) ---
    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where('starts_at', '>=', now());
    }

    /** Événements sponsorisés actifs → triés en tête côté contrôleur, badge "Sponsorisé". */
    public function scopeSponsored(Builder $q): Builder
    {
        return $q->whereNotNull('sponsored_until')->where('sponsored_until', '>', now());
    }

    /** Section d'accueil "Bientôt complet" : >= 85 % des billets vendus. */
    public function scopeAlmostFull(Builder $q): Builder
    {
        return $q->whereExists(function ($sub) {
            $sub->selectRaw('1')
                ->from('ticket_types')
                ->whereColumn('ticket_types.event_id', 'events.id')
                ->groupBy('ticket_types.event_id')
                ->havingRaw('SUM(ticket_types.sold) >= 0.85 * SUM(ticket_types.stock)');
        });
    }

    // --- Accessors ---
    public function getIsSalesOpenAttribute(): bool
    {
        return $this->status === 'published'
            && (!$this->sales_open_at || $this->sales_open_at <= now())
            && (!$this->sales_close_at || $this->sales_close_at > now());
    }

    /** Taux de remplissage global (0..1) — sert au scoring ET à la tarification dynamique. */
    public function fillRatio(): float
    {
        $stock = (int) $this->ticketTypes()->sum('stock');
        return $stock > 0 ? min(1.0, ((int) $this->ticketTypes()->sum('sold')) / $stock) : 0.0;
    }
}
