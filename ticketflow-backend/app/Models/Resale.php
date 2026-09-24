<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Revente — cœur du mécanisme Autolist : le vendeur fixe un prix, la transaction
 * s'exécute automatiquement dès qu'un acheteur se présente (ResaleService@purchase).
 */
class Resale extends Model
{
    protected $fillable = ['ticket_id', 'seller_id', 'event_id', 'ask_price', 'autolist', 'status', 'buyer_id', 'order_id'];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }
    public function event(): BelongsTo { return $this->belongsTo(Event::class); }
    public function buyer(): BelongsTo { return $this->belongsTo(User::class, 'buyer_id'); }
}
