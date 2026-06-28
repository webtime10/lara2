<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_visit_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('swiss_regions')->cascadeOnDelete();
            $table->string('food_type', 50);
            $table->decimal('adult_avg_price', 10, 2)->nullable();
            $table->decimal('child_avg_price', 10, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->string('ai_model', 50)->nullable();
            $table->unsignedInteger('places_count')->default(0);
            $table->timestamp('last_checked')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'food_type']);
            $table->index('food_type');
            $table->index('last_checked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_visit_prices');
    }
};
