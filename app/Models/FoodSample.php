<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodSample extends Model
{
    protected $fillable = [
        'region_id',
        'food_import_id',
        'food_type',
        'gpt_processed',
        'classification_confidence',
        'name',
        'website',
        'rating',
        'reviews_count',
        'address',
        'price_level',
        'place_id',
        'sample_rank',
        'selected_at',
    ];

    protected $casts = [
        'rating' => 'decimal:1',
        'gpt_processed' => 'boolean',
        'reviews_count' => 'integer',
        'sample_rank' => 'integer',
        'selected_at' => 'datetime',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(SwissRegion::class, 'region_id');
    }

    public function foodImport(): BelongsTo
    {
        return $this->belongsTo(FoodImport::class, 'food_import_id');
    }
}
