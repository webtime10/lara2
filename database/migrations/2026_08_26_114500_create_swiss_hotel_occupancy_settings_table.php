<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swiss_hotel_occupancy_settings', function (Blueprint $table) {
            $table->id();
            $table->json('selected_keys')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swiss_hotel_occupancy_settings');
    }
};
