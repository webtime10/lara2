<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swiss_apartments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('swiss_regions')->cascadeOnDelete();
            $table->string('title', 255);
            $table->decimal('rating', 3, 1)->nullable();
            $table->decimal('price_usd', 10, 2)->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'title']);
            $table->index(['region_id', 'price_usd']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swiss_apartments');
    }
};
