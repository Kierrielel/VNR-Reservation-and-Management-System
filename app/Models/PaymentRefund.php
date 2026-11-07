<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    protected $fillable = [
        'booking_id', 'payment_id', 'gateway_payment_id', 'gateway_refund_id',
        'idempotency_key', 'notes', 'amount', 'status', 'error', 'attempted_at', 'refunded_at',
    ];

    protected $casts = ['amount' => 'decimal:2', 'attempted_at' => 'datetime', 'refunded_at' => 'datetime'];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
