<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTransfer extends Model
{
    protected $fillable = ['ticket_id', 'from_user_id', 'to_user_id', 'accepted_at'];
    protected function casts(): array { return ['accepted_at' => 'datetime']; }

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function from(): BelongsTo { return $this->belongsTo(User::class, 'from_user_id'); }
    public function to(): BelongsTo { return $this->belongsTo(User::class, 'to_user_id'); }
}
