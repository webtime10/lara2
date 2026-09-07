<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('weather_month_stats') && ! Schema::hasColumn('weather_month_stats', 'country')) {
            Schema::table('weather_month_stats', function (Blueprint $table) {
                $table->string('country', 8)->default('ch')->after('id');
            });

            DB::table('weather_month_stats')->update(['country' => 'ch']);

            Schema::table('weather_month_stats', function (Blueprint $table) {
                $table->dropUnique(['region_slug', 'month']);
                $table->unique(['country', 'region_slug', 'month'], 'weather_month_stats_country_region_month_unique');
                $table->index('country');
            });
        }

        if (Schema::hasTable('weather_sync_runs') && ! Schema::hasColumn('weather_sync_runs', 'country')) {
            Schema::table('weather_sync_runs', function (Blueprint $table) {
                $table->string('country', 8)->default('ch')->after('uuid');
                $table->index('country');
            });

            DB::table('weather_sync_runs')->update(['country' => 'ch']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('weather_month_stats') && Schema::hasColumn('weather_month_stats', 'country')) {
            Schema::table('weather_month_stats', function (Blueprint $table) {
                $table->dropUnique('weather_month_stats_country_region_month_unique');
                $table->dropIndex(['country']);
                $table->dropColumn('country');
                $table->unique(['region_slug', 'month']);
            });
        }

        if (Schema::hasTable('weather_sync_runs') && Schema::hasColumn('weather_sync_runs', 'country')) {
            Schema::table('weather_sync_runs', function (Blueprint $table) {
                $table->dropIndex(['country']);
                $table->dropColumn('country');
            });
        }
    }
};
