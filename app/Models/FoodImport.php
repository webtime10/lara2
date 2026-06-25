<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodImport extends Model
{
    protected $fillable = [
        'region_id',
        'keyword',
        'name',
        'website',
        'rating',
        'reviews_count',
        'address',
        'price_level',
        'food_type',
        'gpt_processed',
        'place_id',
        'imported_at',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'reviews_count' => 'integer',
        'price_level' => 'integer',
        'gpt_processed' => 'boolean',
        'imported_at' => 'datetime',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(SwissRegion::class, 'region_id');
    }
}
