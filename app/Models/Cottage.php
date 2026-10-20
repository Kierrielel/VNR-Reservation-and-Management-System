<?php

namespace App\Models;

use App\Models\Booking;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cottage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'capacity',
        'price_day',
        'price_overnight',
        'image',
        'status',
    ];

    public function bookings()
    {
        return $this->belongsToMany(Booking::class, 'booking_cottage');
    }
}
