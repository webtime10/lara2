<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodSource extends Model
{
    public const TYPE_RESTAURANT = 'restaurant';

    public const TYPE_FINE_RESTAURANT = 'fine_restaurant';

    public const TYPE_CAFE = 'cafe';

    public const TYPE_HOME_COOKING = 'home_cooking';

    /** @var list<string> */
    public const PRICE_LEVELS = [
        'budget',
        'mid_range',
        'premium',
        'luxury',
    ];

    /** @var list<string> */
    public const RESTAURANT_PRICE_FIELDS = [
        'soup_price',
        'salad_price',
        'pizza_price',
        'pasta_price',
        'burger_price',
        'main_course_price',
    ];

    /** @var list<string> */
    public const GROCERY_PRICE_FIELDS = [
        'bread_price',
        'milk_price',
        'eggs_price',
        'chicken_price',
        'rice_price',
        'pasta_grocery_price',
        'vegetables_price',
        'fruits_price',
        'coffee_price',
        'water_price',
    ];

    protected $fillable = [
        'region_id',
        'name',
        'website',
        'rating',
        'reviews_count',
        'address',
        'price_level',
        'food_type',
        'soup_price',
        'salad_price',
        'pizza_price',
        'pasta_price',
        'burger_price',
        'main_course_price',
        'bread_price',
        'milk_price',
        'eggs_price',
        'chicken_price',
        'rice_price',
        'pasta_grocery_price',
        'vegetables_price',
        'fruits_price',
        'coffee_price',
        'water_price',
        'currency',
        'last_checked',
    ];

    protected $casts = [
        'soup_price' => 'decimal:2',
        'rating' => 'decimal:1',
        'reviews_count' => 'integer',
        'salad_price' => 'decimal:2',
        'pizza_price' => 'decimal:2',
        'pasta_price' => 'decimal:2',
        'burger_price' => 'decimal:2',
        'main_course_price' => 'decimal:2',
        'bread_price' => 'decimal:2',
        'milk_price' => 'decimal:2',
        'eggs_price' => 'decimal:2',
        'chicken_price' => 'decimal:2',
        'rice_price' => 'decimal:2',
        'pasta_grocery_price' => 'decimal:2',
        'vegetables_price' => 'decimal:2',
        'fruits_price' => 'decimal:2',
        'coffee_price' => 'decimal:2',
        'water_price' => 'decimal:2',
        'last_checked' => 'datetime',
    ];

    /** @return array<string, string> */
    public static function typeLabels(): array
    {
        return [
            self::TYPE_RESTAURANT => 'Ресторан',
            self::TYPE_FINE_RESTAURANT => 'Фешенебельный ресторан',
            self::TYPE_CAFE => 'Кафе',
            self::TYPE_HOME_COOKING => 'Домашнее питание',
        ];
    }

    /** @return array<string, string> */
    public static function priceLabels(): array
    {
        return [
            'soup_price' => 'Суп',
            'salad_price' => 'Салат',
            'pizza_price' => 'Пицца',
            'pasta_price' => 'Паста',
            'burger_price' => 'Бургер',
            'main_course_price' => 'Основное блюдо',
            'bread_price' => 'Хлеб',
            'milk_price' => 'Молоко',
            'eggs_price' => 'Яйца',
            'chicken_price' => 'Курица',
            'rice_price' => 'Рис',
            'pasta_grocery_price' => 'Макароны',
            'vegetables_price' => 'Овощи',
            'fruits_price' => 'Фрукты',
            'coffee_price' => 'Кофе',
            'water_price' => 'Вода',
        ];
    }

    /** @return list<string> */
    public static function foodTypes(): array
    {
        return array_keys(self::typeLabels());
    }

    /** @return list<string> */
    public static function allPriceFields(): array
    {
        return array_merge(self::RESTAURANT_PRICE_FIELDS, self::GROCERY_PRICE_FIELDS);
    }

    /** @return list<string> */
    public function activePriceFields(): array
    {
        return self::activePriceFieldsForType((string) $this->food_type);
    }

    /** @return list<string> */
    public static function activePriceFieldsForType(string $foodType): array
    {
        return match ($foodType) {
            self::TYPE_RESTAURANT, self::TYPE_FINE_RESTAURANT, self::TYPE_CAFE => self::RESTAURANT_PRICE_FIELDS,
            self::TYPE_HOME_COOKING => self::GROCERY_PRICE_FIELDS,
            default => [],
        };
    }

    /** @return list<string> */
    public static function inactivePriceFieldsForType(string $foodType): array
    {
        return array_values(array_diff(self::allPriceFields(), self::activePriceFieldsForType($foodType)));
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(SwissRegion::class, 'region_id');
    }
}
