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

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'organizer_id', 'category_id', 'venue_id', 'title', 'slug', 'artist',
        'description', 'cover_path', 'starts_at', 'ends_at', 'status',
        'is_sponsored', 'sponsored_until', 'views_count',
        'dynamic_pricing_enabled', 'seating_map',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sponsored_until' => 'datetime',
            'is_sponsored' => 'boolean',
            'dynamic_pricing_enabled' => 'boolean',
            'seating_map' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Event $event) {
            if (blank($event->slug)) {
                $base = Str::slug($event->title);
                $slug = $base;
                $i = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $event->slug = $slug;
            }
        });
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('sort_order');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    // ---- Scopes ----

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PUBLISHED);
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->published()->where('starts_at', '>=', now());
    }

    public function scopeTrending(Builder $q): Builder
    {
        return $q->upcoming()
            ->withCount(['orders' => fn ($o) => $o->where('payment_status', 'paid')])
            ->orderByDesc('orders_count')
            ->orderByDesc('views_count');
    }

    public function scopeSponsored(Builder $q): Builder
    {
        return $q->where('is_sponsored', true)
            ->where(fn ($w) => $w->whereNull('sponsored_until')->orWhere('sponsored_until', '>', now()));
    }

    public function scopeNearlyFull(Builder $q): Builder
    {
        return $q->upcoming()->with('ticketTypes')
            ->havingRaw('SUM(ticket_types.quantity_sold) / NULLIF(SUM(ticket_types.quantity_total),0) > 0.8');
    }

    // ---- Accesseurs métier ----

    public function getMinPriceAttribute(): ?int
    {
        return $this->relationLoaded('ticketTypes')
            ? optional($this->ticketTypes->where('is_active', true)->sortBy('price_displayed')->first())->price_displayed
            : $this->ticketTypes()->where('isActive', true)->min('price_displayed');
    }

    public function isSponsoredActive(): bool
    {
        return $this->is_sponsored && ($this->sponsored_until === null || $this->sponsored_until->isFuture());
    }

    public function totalCapacity(): int
    {
        return (int) $this->ticketTypes->sum('quantity_total');
    }

    public function fillRate(): float
    {
        $total = $this->totalCapacity();

        return $total > 0 ? round($this->ticketTypes->sum('quantity_sold') / $total, 2) : 0.0;
    }

    public function hasSeats(): bool
    {
        return $this->sections()->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
