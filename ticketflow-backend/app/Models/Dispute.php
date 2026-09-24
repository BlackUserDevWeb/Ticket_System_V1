<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dispute extends Model
{
    protected $fillable = ['order_id', 'opened_by', 'type', 'description', 'status', 'handled_by', 'admin_note'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function opener(): BelongsTo { return $this->belongsTo(User::class, 'opened_by'); }
    public function handler(): BelongsTo { return $this->belongsTo(User::class, 'handled_by'); }
}
