<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dfs_agg_runs', function (Blueprint $table) {
            $table->id();
            $table->string('destination', 120)->default('Canton of Bern')->index();
            $table->string('destination_slug', 64)->default('bern')->index();
            $table->unsignedBigInteger('location_code')->nullable()->index();
            $table->string('location_coordinate', 64)->nullable();
            $table->string('endpoint', 255);
            $table->unsignedInteger('categories_selected')->default(0);
            $table->unsignedInteger('categories_processed')->default(0);
            $table->unsignedInteger('api_requests')->default(0);
            $table->unsignedInteger('total_objects_reported')->default(0);
            $table->decimal('api_cost', 12, 6)->nullable();
            $table->unsignedInteger('execution_time_ms')->nullable();
            $table->string('status', 32)->default('success')->index();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('collected_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('dfs_agg_run_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('dfs_agg_runs')->cascadeOnDelete();
            $table->string('destination', 120)->nullable();
            $table->unsignedBigInteger('location_code')->nullable()->index();
            $table->string('category_code', 120)->index();
            $table->string('category_name', 255)->nullable();
            $table->unsignedInteger('objects_count')->default(0);
            $table->decimal('api_cost', 12, 6)->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamp('collected_at')->nullable()->index();
            $table->timestamps();

            $table->index(['run_id', 'category_code'], 'dfs_agg_run_cat_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dfs_agg_run_categories');
        Schema::dropIfExists('dfs_agg_runs');
    }
};
