<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('swiss_regions')->cascadeOnDelete();

            $table->string('name', 255);
            $table->text('website')->nullable();
            $table->string('food_type', 50);

            $table->decimal('soup_price', 10, 2)->nullable();
            $table->decimal('salad_price', 10, 2)->nullable();
            $table->decimal('pizza_price', 10, 2)->nullable();
            $table->decimal('pasta_price', 10, 2)->nullable();
            $table->decimal('burger_price', 10, 2)->nullable();
            $table->decimal('main_course_price', 10, 2)->nullable();

            $table->decimal('bread_price', 10, 2)->nullable();
            $table->decimal('milk_price', 10, 2)->nullable();
            $table->decimal('eggs_price', 10, 2)->nullable();
            $table->decimal('chicken_price', 10, 2)->nullable();
            $table->decimal('rice_price', 10, 2)->nullable();
            $table->decimal('pasta_grocery_price', 10, 2)->nullable();
            $table->decimal('vegetables_price', 10, 2)->nullable();
            $table->decimal('fruits_price', 10, 2)->nullable();
            $table->decimal('coffee_price', 10, 2)->nullable();
            $table->decimal('water_price', 10, 2)->nullable();

            $table->string('currency', 10)->default('CHF')
            $table->timestamp('last_checked')->nullable();
            $table->timestamps();

            $table->index(['region_id', 'food_type']);
            $table->index('last_checked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_sources');
    }
};
