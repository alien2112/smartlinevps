<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentStateTransition extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'payment_transaction_id',
        'from_state',
        'to_state',
        'trigger',
        'context',
        'transitioned_by',
        'transitioned_at',
    ];

    protected $casts = [
        'context' => 'array',
        'transitioned_at' => 'datetime',
    ];

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
