<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_month_stats', function (Blueprint $table) {
            $table->id();
            $table->string('region_slug', 80);
            $table->string('region_name_ru', 120);
            $table->unsignedTinyInteger('month');
            $table->string('average_temperature', 120)->nullable();
            $table->string('precipitation', 80)->nullable();
            $table->string('sunny_days', 120)->nullable();
            $table->string('season', 40)->nullable();
            $table->string('ai_model', 50)->nullable();
            $table->timestamp('last_checked')->nullable();
            $table->timestamps();

            $table->unique(['region_slug', 'month']);
            $table->index('region_slug');
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_month_stats');
    }
};
