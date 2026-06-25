<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_samples', function (Blueprint $table) {
            $table->boolean('gpt_processed')->default(true)->after('food_type');
            $table->index(['region_id', 'food_type', 'gpt_processed']);
        });
    }

    public function down(): void
    {
        Schema::table('food_samples', function (Blueprint $table) {
            $table->dropIndex('food_samples_region_id_food_type_gpt_processed_index');
            $table->dropColumn('gpt_processed');
        });
    }
};
