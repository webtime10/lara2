<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('swiss_regions')->cascadeOnDelete();
            $table->string('keyword', 64);
            $table->string('name', 255);
            $table->string('website', 512)->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->unsignedInteger('reviews_count')->nullable();
            $table->string('address', 512)->nullable();
            $table->unsignedTinyInteger('price_level')->nullable();
            $table->string('food_type', 50)->nullable();
            $table->string('place_id', 255)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['region_id', 'keyword']);
            $table->index(['region_id', 'food_type']);
            $table->index(['region_id', 'reviews_count']);
            $table->unique(['region_id', 'name', 'keyword']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_imports');
    }
};
