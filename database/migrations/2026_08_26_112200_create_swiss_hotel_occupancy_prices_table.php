<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swiss_hotel_occupancy_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('region_id')->constrained('swiss_regions')->cascadeOnDelete();
            $table->string('hotel_identifier', 255);
            $table->string('occupancy_key', 32);
            $table->unsignedTinyInteger('adults');
            $table->json('children')->nullable();
            $table->decimal('price_usd', 10, 2)->nullable();
            $table->string('error', 500)->nullable();
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->decimal('api_cost', 10, 6)->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['region_id', 'hotel_identifier', 'occupancy_key'],
                'swiss_hotel_occ_unique'
            );
            $table->index(['hotel_identifier', 'occupancy_key'], 'swiss_hotel_occ_ident_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swiss_hotel_occupancy_prices');
    }
};
