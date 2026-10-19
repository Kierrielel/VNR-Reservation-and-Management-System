<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'hours',
        'image',
        'status',
    ];
    
    protected $casts = [
        'inclusions' => 'array',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
