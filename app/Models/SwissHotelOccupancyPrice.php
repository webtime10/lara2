<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwissHotelOccupancyPrice extends Model
{
    protected $fillable = [
        'region_id',
        'hotel_identifier',
        'occupancy_key',
        'adults',
        'children',
        'price_usd',
        'error',
        'check_in',
        'check_out',
        'api_cost',
        'fetched_at',
    ];

    protected $casts = [
        'adults' => 'integer',
        'children' => 'array',
        'price_usd' => 'decimal:2',
        'api_cost' => 'decimal:6',
        'check_in' => 'date',
        'check_out' => 'date',
        'fetched_at' => 'datetime',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(SwissRegion::class, 'region_id');
    }
}
