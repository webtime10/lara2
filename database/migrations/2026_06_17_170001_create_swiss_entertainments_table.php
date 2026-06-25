<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swiss_entertainments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('swiss_regions')->cascadeOnDelete();
            $table->string('title', 255);
            $table->string('category', 64);
            $table->string('website', 512)->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->unsignedInteger('reviews')->nullable();
            $table->string('address', 512)->nullable();
            $table->string('place_id', 255)->nullable();
            $table->timestamps();

            $table->unique(['region_id', 'title']);
            $table->index(['region_id', 'category']);
            $table->index(['region_id', 'reviews']);
            $table->index(['region_id', 'place_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swiss_entertainments');
    }
};
