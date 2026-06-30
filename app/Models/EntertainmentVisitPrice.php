<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntertainmentVisitPrice extends Model
{
    protected $fillable = [
        'region_id',
        'category',
        'adult_avg_price',
        'child_avg_price',
        'currency',
        'ai_model',
        'places_count',
        'last_checked',
    ];

    protected $casts = [
        'adult_avg_price' => 'decimal:2',
        'child_avg_price' => 'decimal:2',
        'places_count' => 'integer',
        'last_checked' => 'datetime',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(SwissRegion::class, 'region_id');
    }
}
