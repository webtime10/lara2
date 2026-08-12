<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Remove Bern Tourist / DataForSEO Business Listings tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('dfs_agg_run_categories');
        Schema::dropIfExists('dfs_agg_runs');
        Schema::dropIfExists('dfs_bl_poi_categories');
        Schema::dropIfExists('dfs_bl_pois');
        Schema::dropIfExists('dfs_bl_category_aggregations');
        Schema::dropIfExists('dfs_bl_location_candidates');
        Schema::dropIfExists('dfs_bl_tourist_category_matches');
        Schema::dropIfExists('dfs_bl_categories');
        Schema::dropIfExists('dfs_bl_raw_responses');
    }

    public function down(): void
    {
        // Irreversible cleanup — recreate from old migrations if needed.
    }
};
