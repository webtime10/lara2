<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodVisitPrice extends Model
{
    public const TYPE_CAFE = 'cafe';

    public const TYPE_RESTAURANT = 'restaurant';

    protected $fillable = [
        'region_id',
        'food_type',
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

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_CAFE => 'Кафе',
            self::TYPE_RESTAURANT => 'Рестораны',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(SwissRegion::class, 'region_id');
    }
}
