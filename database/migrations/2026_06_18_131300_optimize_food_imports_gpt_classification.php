<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_imports', function (Blueprint $table) {
            $table->boolean('gpt_processed')->default(false)->after('food_type');
        });

        DB::table('food_imports')
            ->whereIn('keyword', ['cafe', 'coffee shop', 'bakery'])
            ->update(['food_type' => 'cafe', 'gpt_processed' => true]);

        DB::table('food_imports')
            ->whereIn('keyword', [
                'restaurant',
                'pizza restaurant',
                'swiss restaurant',
                'vegetarian restaurant',
                'seafood restaurant',
                'steak house',
                'brunch restaurant',
            ])
            ->update(['food_type' => 'restaurant_candidate', 'gpt_processed' => false]);

        DB::statement(
            'DELETE fi1 FROM food_imports fi1
             INNER JOIN food_imports fi2
                ON fi1.region_id = fi2.region_id
               AND fi1.place_id = fi2.place_id
               AND fi1.id > fi2.id
             WHERE fi1.place_id IS NOT NULL'
        );

        Schema::table('food_imports', function (Blueprint $table) {
            $table->unique(['region_id', 'place_id'], 'food_imports_region_place_unique');
            $table->index(['region_id', 'food_type', 'gpt_processed'], 'food_imports_region_type_gpt_index');
        });
    }

    public function down(): void
    {
        Schema::table('food_imports', function (Blueprint $table) {
            $table->dropUnique('food_imports_region_place_unique');
            $table->dropIndex('food_imports_region_type_gpt_index');
            $table->dropColumn('gpt_processed');
        });
    }
};
