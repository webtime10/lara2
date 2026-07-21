<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dfs_bl_raw_responses', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint', 120);
            $table->string('destination_slug', 64)->nullable()->index();
            $table->string('request_key', 190)->nullable()->index();
            $table->longText('payload_json')->nullable();
            $table->longText('response_json');
            $table->unsignedInteger('http_cost')->nullable();
            $table->timestamp('collected_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('dfs_bl_categories', function (Blueprint $table) {
            $table->id();
            $table->string('category_code', 120)->index();
            $table->string('category_name', 255)->nullable();
            $table->unsignedInteger('business_count')->nullable();
            $table->string('source', 64)->default('dataforseo_business_listings');
            $table->json('raw_data')->nullable();
            $table->timestamp('collected_at')->nullable()->index();
            $table->timestamps();

            $table->index(['category_code', 'collected_at'], 'dfs_bl_categories_code_collected_idx');
        });

        Schema::create('dfs_bl_tourist_category_matches', function (Blueprint $table) {
            $table->id();
            $table->string('topic_group', 120)->index();
            $table->string('category_code', 120)->nullable()->index();
            $table->string('category_name', 255)->nullable();
            $table->boolean('matched')->default(false);
            $table->string('match_reason', 255)->nullable();
            $table->timestamp('collected_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('dfs_bl_location_candidates', function (Blueprint $table) {
            $table->id();
            $table->string('destination_slug', 64)->default('bern')->index();
            $table->unsignedBigInteger('location_code')->index();
            $table->string('location_name', 255)->nullable();
            $table->string('location_type', 120)->nullable();
            $table->string('country_iso_code', 8)->nullable();
            $table->boolean('is_selected')->default(false);
            $table->text('selection_reason')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('collected_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('dfs_bl_category_aggregations', function (Blueprint $table) {
            $table->id();
            $table->string('destination_slug', 64)->default('bern')->index();
            $table->unsignedBigInteger('location_code')->index();
            $table->string('category_code', 120)->index();
            $table->string('category_name', 255)->nullable();
            $table->unsignedInteger('objects_count')->default(0);
            $table->string('source', 64)->default('dataforseo_categories_aggregation');
            $table->json('raw_data')->nullable();
            $table->timestamp('collected_at')->nullable()->index();
            $table->timestamps();

            $table->index(
                ['destination_slug', 'location_code', 'category_code'],
                'dfs_bl_agg_lookup_idx'
            );
        });

        Schema::create('dfs_bl_pois', function (Blueprint $table) {
            $table->id();
            $table->string('destination_slug', 64)->default('bern')->index();
            $table->unsignedBigInteger('location_code')->nullable()->index();
            $table->string('external_id', 190)->index();
            $table->string('dedup_hash', 64)->index();
            $table->string('name', 255)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('primary_category', 255)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('country_code', 8)->nullable();
            $table->decimal('rating', 4, 2)->nullable();
            $table->unsignedInteger('reviews_count')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('website', 500)->nullable();
            $table->json('working_hours')->nullable();
            $table->string('source', 64)->default('dataforseo_business_listings');
            $table->json('raw_data')->nullable();
            $table->timestamp('collected_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['destination_slug', 'external_id'], 'dfs_bl_pois_dest_ext_unique');
        });

        Schema::create('dfs_bl_poi_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poi_id')->constrained('dfs_bl_pois')->cascadeOnDelete();
            $table->string('category_code', 120)->nullable()->index();
            $table->string('category_name', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['poi_id', 'category_code'], 'dfs_bl_poi_cat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dfs_bl_poi_categories');
        Schema::dropIfExists('dfs_bl_pois');
        Schema::dropIfExists('dfs_bl_category_aggregations');
        Schema::dropIfExists('dfs_bl_location_candidates');
        Schema::dropIfExists('dfs_bl_tourist_category_matches');
        Schema::dropIfExists('dfs_bl_categories');
        Schema::dropIfExists('dfs_bl_raw_responses');
    }
};
