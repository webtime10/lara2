<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwissApartment extends Model
{
    protected $fillable = [
        'region_id',
        'title',
        'level',
        'rating',
        'price_usd',
    ];

    protected $casts = [
        'level' => 'integer',
        'rating' => 'decimal:1',
        'price_usd' => 'decimal:2',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(SwissRegion::class, 'region_id');
    }
}
