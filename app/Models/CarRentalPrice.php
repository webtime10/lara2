<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarRentalPrice extends Model
{
    public const CLASS_ECONOMY = 'economy';

    public const CLASS_MEDIUM = 'medium';

    public const CLASS_LUXURY = 'luxury';

    protected $fillable = [
        'region_id',
        'car_class',
        'daily_price',
        'currency',
        'ai_model',
        'last_checked',
    ];

    protected $casts = [
        'daily_price' => 'decimal:2',
        'last_checked' => 'datetime',
    ];

    /** @return list<string> */
    public static function classes(): array
    {
        return [
            self::CLASS_ECONOMY,
            self::CLASS_MEDIUM,
            self::CLASS_LUXURY,
        ];
    }

    /** @return array<string, string> */
    public static function classLabels(): array
    {
        return [
            self::CLASS_ECONOMY => 'Эконом',
            self::CLASS_MEDIUM => 'Средний',
            self::CLASS_LUXURY => 'Люкс',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(SwissRegion::class, 'region_id');
    }
}
