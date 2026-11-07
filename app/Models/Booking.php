<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'guest_name',
        'guest_contact',
        'guest_email',
        'booked_by',
        'event_package_id',
        'reservation_type',
        'stay_type',
        'check_in',
        'check_out',
        'guest_count',
        'total_price',
        'reservation_fee',
        'balance',
        'status',
        'payment_status',
        'payment_method',
        'reference_code',
        'additional_message',
        'expires_at',
        'checked_in_at',
        'checked_in_by',
        'cancelled_at',
        'cancelled_by',
        'refund_status',
        'refund_amount',
        'refund_deadline_at',
        'cancellation_reason',
        'refund_decided_at',
        'refund_decided_by',
        'refund_rejection_reason',
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'total_price' => 'decimal:2',
        'reservation_fee' => 'decimal:2',
        'balance' => 'decimal:2',
        'expires_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'refund_amount' => 'decimal:2',
        'refund_deadline_at' => 'datetime',
        'refund_decided_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function refundDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refund_decided_by');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }

    public function getGuestDisplayNameAttribute(): string
    {
        return $this->guest_name ?? $this->user?->name ?? 'Guest';
    }

    public function getGuestDisplayEmailAttribute(): ?string
    {
        return $this->guest_email ?? $this->user?->email;
    }

    public function eventPackage()
    {
        return $this->belongsTo(EventPackage::class);
    }

    public function cottages()
    {
        return $this->belongsToMany(Cottage::class, 'booking_cottage');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
