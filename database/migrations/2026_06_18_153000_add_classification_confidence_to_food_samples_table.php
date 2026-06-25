<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_samples', function (Blueprint $table) {
            $table->string('classification_confidence', 20)->nullable()->after('gpt_processed');
        });
    }

    public function down(): void
    {
        Schema::table('food_samples', function (Blueprint $table) {
            $table->dropColumn('classification_confidence');
        });
    }
};
