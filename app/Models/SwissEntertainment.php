<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwissEntertainment extends Model
{
    protected $fillable = [
        'region_id',
        'title',
        'category',
        'website',
        'rating',
        'reviews',
        'address',
        'place_id',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'reviews' => 'integer',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(SwissRegion::class, 'region_id');
    }
}
