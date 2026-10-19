<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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
    ];

    protected $casts = [
        'check_in' => 'date',
        'check_out' => 'date',
        'total_price' => 'decimal:2',
        'reservation_fee' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function eventPackage()
    {
        return $this->belongsTo(EventPackage::class);
    }

    public function cottages()
    {
        return $this->belongsToMany(Cottage::class, 'booking_cottage');
    }
}