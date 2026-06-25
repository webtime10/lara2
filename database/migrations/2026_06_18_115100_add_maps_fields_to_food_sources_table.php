<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_sources', function (Blueprint $table) {
            $table->decimal('rating', 3, 1)->nullable()->after('website');
            $table->unsignedInteger('reviews_count')->nullable()->after('rating');
            $table->string('address', 512)->nullable()->after('reviews_count');
            $table->unsignedTinyInteger('price_level')->nullable()->after('address');

            $table->index(['region_id', 'price_level']);
            $table->index(['region_id', 'reviews_count']);
        });
    }

    public function down(): void
    {
        Schema::table('food_sources', function (Blueprint $table) {
            $table->dropIndex(['region_id', 'price_level']);
            $table->dropIndex(['region_id', 'reviews_count']);
            $table->dropColumn(['rating', 'reviews_count', 'address', 'price_level']);
        });
    }
};
