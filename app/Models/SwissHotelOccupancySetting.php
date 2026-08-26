<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SwissHotelOccupancySetting extends Model
{
    protected $table = 'swiss_hotel_occupancy_settings';

    protected $fillable = [
        'selected_keys',
    ];

    protected $casts = [
        'selected_keys' => 'array',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], [
            'selected_keys' => null,
        ]);
    }
}
