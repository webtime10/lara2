<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE_NAME = 'Продуктовая корзина';

    public function up(): void
    {
        $now = now();

        $pricesBySlug = [
            'zurich' => [
                'bread_price' => 4.20,
                'milk_price' => 2.30,
                'eggs_price' => 6.80,
                'chicken_price' => 23.00,
                'rice_price' => 4.20,
                'pasta_grocery_price' => 3.20,
                'vegetables_price' => 8.50,
                'fruits_price' => 7.50,
                'coffee_price' => 9.50,
                'water_price' => 1.60,
                'currency' => 'USD',
            ],
            'geneva' => [
                'bread_price' => 4.40,
                'milk_price' => 2.40,
                'eggs_price' => 7.00,
                'chicken_price' => 24.00,
                'rice_price' => 4.30,
                'pasta_grocery_price' => 3.30,
                'vegetables_price' => 8.80,
                'fruits_price' => 7.80,
                'coffee_price' => 9.80,
                'water_price' => 1.70,
                'currency' => 'USD',
            ],
            'basel-stadt' => [
                'bread_price' => 4.10,
                'milk_price' => 2.20,
                'eggs_price' => 6.60,
                'chicken_price' => 22.50,
                'rice_price' => 4.10,
                'pasta_grocery_price' => 3.10,
                'vegetables_price' => 8.20,
                'fruits_price' => 7.20,
                'coffee_price' => 9.20,
                'water_price' => 1.50,
                'currency' => 'USD',
            ],
        ];

        $defaultPrices = [
            'bread_price' => 4.00,
            'milk_price' => 2.20,
            'eggs_price' => 6.50,
            'chicken_price' => 22.00,
            'rice_price' => 4.00,
            'pasta_grocery_price' => 3.00,
            'vegetables_price' => 8.00,
            'fruits_price' => 7.00,
            'coffee_price' => 9.00,
            'water_price' => 1.50,
            'currency' => 'USD',
        ];

        DB::table('swiss_regions')
            ->orderBy('id')
            ->select(['id', 'slug'])
            ->get()
            ->each(function ($region) use ($pricesBySlug, $defaultPrices, $now): void {
                $prices = $pricesBySlug[$region->slug] ?? $defaultPrices;

                DB::table('food_sources')->updateOrInsert(
                    [
                        'region_id' => $region->id,
                        'name' => self::SOURCE_NAME,
                        'food_type' => 'home_cooking',
                    ],
                    array_merge([
                        'website' => null,
                        'soup_price' => null,
                        'salad_price' => null,
                        'pizza_price' => null,
                        'pasta_price' => null,
                        'burger_price' => null,
                        'main_course_price' => null,
                        'last_checked' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ], $prices)
                );
            });
    }

    public function down(): void
    {
        DB::table('food_sources')
            ->where('name', self::SOURCE_NAME)
            ->where('food_type', 'home_cooking')
            ->delete();
    }
};
