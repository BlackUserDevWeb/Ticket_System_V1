<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

class Ticket extends Model
{
    protected $fillable = ['order_item_id', 'owner_id', 'ticket_unit_id', 'code', 'qr_signature', 'status', 'transfer_depth'];

    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function unit(): BelongsTo { return $this->belongsTo(TicketUnit::class, 'ticket_unit_id'); }
    public function transfers(): HasMany { return $this->hasMany(TicketTransfer::class); }
    public function resales(): HasMany { return $this->hasMany(Resale::class); }

    /** Contenu exact du QR : TICKFLOW1.<uuid>.<hmac tronqué>.
     *  HMAC serveur → QR infalsifiable ; le scanner offline utilise une liste blanche
     *  signée téléchargée depuis l'API (aucune clé secrète exposée côté client). */
    public function qrPayload(): string
    {
        return sprintf('%s.%s.%s', Config::get('ticketflow.qr.payload_prefix'), $this->code, $this->qr_signature);
    }

    public function canBeTransferred(): bool
    {
        return $this->status === 'active'
            && $this->transfer_depth < Config::get('ticketflow.max_transfer_depth');
    }
}
