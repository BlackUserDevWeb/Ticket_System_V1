<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtrasOrder extends Model
{
    protected $table = 'extras_order';
    protected $fillable = ['order_id', 'event_extra_id', 'quantity', 'unit_price'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function extra(): BelongsTo { return $this->belongsTo(EventExtra::class, 'event_extra_id'); }
}
