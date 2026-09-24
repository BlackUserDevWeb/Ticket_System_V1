<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'ticket_unit_id', 'ticket_type_id', 'unit_price_net', 'unit_fee', 'quantity', 'meta'];

    protected function casts(): array
    {
        // meta : contexte revente/Autolist {ticket_id, resale_id} — cf. ResaleService.
        return ['meta' => 'array'];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function unit(): BelongsTo { return $this->belongsTo(TicketUnit::class, 'ticket_unit_id'); }
    public function ticketType(): BelongsTo { return $this->belongsTo(TicketType::class); }
    public function tickets(): HasMany { return $this->hasMany(Ticket::class); }
}
