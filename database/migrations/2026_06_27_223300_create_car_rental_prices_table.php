<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_rental_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('swiss_regions')->cascadeOnDelete();
            $table->string('car_class', 50);
            $table->decimal('daily_price', 10, 2)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->string('ai_model', 50)->nullable();
            $table->timestamp('last_checked')->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'car_class']);
            $table->index('car_class');
            $table->index('last_checked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_rental_prices');
    }
};
