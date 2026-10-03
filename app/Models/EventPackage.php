<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventPackage extends Model
{
    protected $fillable = [
        'name',
        'price',
        'hours',
        'inclusions',
        'image',
        'status',
    ];

    use HasFactory;
}
