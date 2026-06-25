<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE food_sources MODIFY food_type VARCHAR(50) NULL');
    }

    public function down(): void
    {
        DB::statement("UPDATE food_sources SET food_type = 'restaurant' WHERE food_type IS NULL");
        DB::statement('ALTER TABLE food_sources MODIFY food_type VARCHAR(50) NOT NULL');
    }
};
