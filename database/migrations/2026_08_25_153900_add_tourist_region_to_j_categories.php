<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('j_categories')) {
            return;
        }

        Schema::table('j_categories', function (Blueprint $table) {
            if (! Schema::hasColumn('j_categories', 'tourist_region')) {
                $table->string('tourist_region', 64)->nullable()->after('manufacturer_id')->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('j_categories')) {
            return;
        }

        Schema::table('j_categories', function (Blueprint $table) {
            if (Schema::hasColumn('j_categories', 'tourist_region')) {
                $table->dropColumn('tourist_region');
            }
        });
    }
};
