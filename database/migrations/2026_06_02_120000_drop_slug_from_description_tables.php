<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('category_descriptions') && Schema::hasColumn('category_descriptions', 'slug')) {
            Schema::table('category_descriptions', function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }

        if (Schema::hasTable('product_descriptions') && Schema::hasColumn('product_descriptions', 'slug')) {
            Schema::table('product_descriptions', function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('category_descriptions') && ! Schema::hasColumn('category_descriptions', 'slug')) {
            Schema::table('category_descriptions', function (Blueprint $table) {
                $table->string('slug', 255)->nullable()->after('name');
                $table->unique(['language_id', 'slug']);
            });
        }

        if (Schema::hasTable('product_descriptions') && ! Schema::hasColumn('product_descriptions', 'slug')) {
            Schema::table('product_descriptions', function (Blueprint $table) {
                $table->string('slug', 255)->nullable()->after('name');
                $table->unique(['language_id', 'slug']);
            });
        }
    }
};
