<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Япония / Ideal Region: зеркало categories + category_descriptions + category_paths.
 * Данные не сидируем.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('j_categories', function (Blueprint $table) {
            $table->id();
            $table->string('image', 255)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('j_categories')->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->boolean('top')->default(false);
            $table->unsignedInteger('column')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('j_category_descriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('j_category_id')->constrained('j_categories')->cascadeOnDelete();
            $table->foreignId('language_id')->constrained('languages')->cascadeOnDelete();
            $table->string('name', 255);
            $table->string('slug', 255);
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 255)->nullable();
            $table->string('meta_keyword', 255)->nullable();
            $table->timestamps();

            $table->unique(['j_category_id', 'language_id']);
            $table->unique(['language_id', 'slug']);
        });

        $fields = (array) config('ideal_region_category_fields.fields', []);
        foreach ($fields as $field) {
            if (! is_string($field) || $field === '') {
                continue;
            }

            Schema::table('j_category_descriptions', function (Blueprint $table) use ($field) {
                if (str_ends_with($field, '_description')) {
                    $table->longText($field)->nullable();
                } else {
                    $table->string($field, 255)->nullable();
                }
            });
        }

        Schema::create('j_category_paths', function (Blueprint $table) {
            $table->foreignId('j_category_id')->constrained('j_categories')->cascadeOnDelete();
            $table->foreignId('path_id')->constrained('j_categories')->cascadeOnDelete();
            $table->unsignedInteger('level');

            $table->primary(['j_category_id', 'path_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('j_category_paths');
        Schema::dropIfExists('j_category_descriptions');
        Schema::dropIfExists('j_categories');
    }
};
