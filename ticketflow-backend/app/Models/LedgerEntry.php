<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registre comptable plateforme : commissions, abonnements, sponsoring, payouts, refunds. */
class LedgerEntry extends Model
{
    protected $fillable = ['user_id', 'kind', 'amount_xof', 'subject_type', 'subject_id', 'description'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function subject() { return $this->morphTo(); }
}
