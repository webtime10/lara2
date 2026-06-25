<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwissHotel extends Model
{
    protected $fillable = [
        'region_id',
        'title',
        'level',
        'stars',
        'price_usd',
    ];

    protected $casts = [
        'level' => 'integer',
        'stars' => 'integer',
        'price_usd' => 'decimal:2',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(SwissRegion::class, 'region_id');
    }
}
