<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('weather_sync_runs')) {
            return;
        }

        Schema::table('weather_sync_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('weather_sync_runs', 'source')) {
                $table->string('source', 20)->default('manual')->after('only_empty');
                $table->index('source');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('weather_sync_runs')) {
            return;
        }

        Schema::table('weather_sync_runs', function (Blueprint $table) {
            if (Schema::hasColumn('weather_sync_runs', 'source')) {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            }
        });
    }
};
