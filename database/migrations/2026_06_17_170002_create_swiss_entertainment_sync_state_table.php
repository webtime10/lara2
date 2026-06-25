<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swiss_entertainment_sync_state', function (Blueprint $table) {
            $table->id();
            $table->timestamp('last_full_sync_at')->nullable();
            $table->timestamps();
        });

        DB::table('swiss_entertainment_sync_state')->insert([
            'last_full_sync_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('swiss_entertainment_sync_state');
    }
};
