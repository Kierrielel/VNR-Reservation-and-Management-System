<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value', 'resort_name', 'contact_number', 'email', 'address', 'logo'
    ];

    public static function getValue(string $key, $default = null)
    {
        return static::where('key', $key)->value('value') ?? $default;
    }
}
