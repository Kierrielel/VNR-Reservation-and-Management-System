<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cottage extends Model
{
    protected $fillable = [
        'name',
        'type',
        'capacity',
        'price_day',
        'price_overnight',
        'image',
        'status',
    ];

    use HasFactory;
}
