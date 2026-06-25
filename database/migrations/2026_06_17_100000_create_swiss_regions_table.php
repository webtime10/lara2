<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swiss_regions', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('label', 128);
            $table->unsignedInteger('location_code')->unique();
            $table->timestamp('hotels_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swiss_regions');
    }
};
