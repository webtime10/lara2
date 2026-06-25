<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE food_samples MODIFY price_level VARCHAR(20) NULL');
        DB::table('food_samples')->where('price_level', '1')->update(['price_level' => 'budget']);
        DB::table('food_samples')->where('price_level', '2')->update(['price_level' => 'mid_range']);
        DB::table('food_samples')->where('price_level', '3')->update(['price_level' => 'premium']);
        DB::table('food_samples')->where('price_level', '4')->update(['price_level' => 'luxury']);
    }

    public function down(): void
    {
        DB::table('food_samples')->where('price_level', 'budget')->update(['price_level' => '1']);
        DB::table('food_samples')->where('price_level', 'mid_range')->update(['price_level' => '2']);
        DB::table('food_samples')->where('price_level', 'premium')->update(['price_level' => '3']);
        DB::table('food_samples')->where('price_level', 'luxury')->update(['price_level' => '4']);
        DB::statement('ALTER TABLE food_samples MODIFY price_level TINYINT UNSIGNED NULL');
    }
};
