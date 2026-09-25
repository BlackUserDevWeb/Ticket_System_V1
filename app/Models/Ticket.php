<?php

namespace App\Models;

use App\Services\QrCodeService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    public const STATUS_VALID = 'valid';
    public const STATUS_USED = 'used';
    public const STATUS_TRANSFERRED = 'transferred';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUND_REQUESTED = 'refund_requested';

    protected $fillable = [
        'order_id', 'ticket_type_id', 'seat_id', 'owner_id',
        'code', 'qr_hash', 'status', 'autolist', 'autolist_price',
    ];

    protected function casts(): array
    {
        return [
            'autolist' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            $ticket->code ??= (string) Str::uuid();
            $ticket->qr_hash ??= hash('sha256', $ticket.code.'|'.config('app.key'));
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function seat(): BelongsTo
    {
        return $this->belongsTo(Seat::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function resale(): HasMany
    {
        return $this->hasMany(Resale::class);
    }

    public function isValid(): bool
    {
        return $this->status === self::STATUS_VALID;
    }

    /**
     * QR code encodé en base64 pour affichage direct dans Blade/PDF.
     */
    protected function qrPng(): Attribute
    {
        return Attribute::get(fn () => 'data:image/png;base64,'.base64_encode(
            app(QrCodeService::class)->png($this->code)
        ));
    }

    /**
     * Signature vérifiable même hors ligne par le scanner organisateur.
     */
    public function signedPayload(): string
    {
        return json_encode([
            'id' => $this->id,
            'code' => $this->code,
            'hash' => substr($this->qr_hash, 0, 16),
        ]);
    }
}
