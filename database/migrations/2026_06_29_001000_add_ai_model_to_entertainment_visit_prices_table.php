<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entertainment_visit_prices', function (Blueprint $table) {
            if (! Schema::hasColumn('entertainment_visit_prices', 'ai_model')) {
                $table->string('ai_model', 50)->nullable()->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entertainment_visit_prices', function (Blueprint $table) {
            if (Schema::hasColumn('entertainment_visit_prices', 'ai_model')) {
                $table->dropColumn('ai_model');
            }
        });
    }
};
