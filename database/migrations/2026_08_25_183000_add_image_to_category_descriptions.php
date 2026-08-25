<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Картинка Ideal Region — на языке (description), не одна на всю категорию.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('j_category_descriptions') && ! Schema::hasColumn('j_category_descriptions', 'image')) {
            Schema::table('j_category_descriptions', function (Blueprint $table) {
                $table->string('image', 255)->nullable()->after('slug');
            });
        }

        if (Schema::hasTable('category_descriptions') && ! Schema::hasColumn('category_descriptions', 'image')) {
            Schema::table('category_descriptions', function (Blueprint $table) {
                $table->string('image', 255)->nullable()->after('slug');
            });
        }

        // Япония: текущие фото категории → язык he
        if (Schema::hasTable('j_category_descriptions') && Schema::hasColumn('j_category_descriptions', 'image')) {
            $heId = DB::table('languages')->where('code', 'he')->value('id');
            if ($heId) {
                $rows = DB::table('j_categories')->whereNotNull('image')->where('image', '<>', '')->get(['id', 'image']);
                foreach ($rows as $row) {
                    DB::table('j_category_descriptions')
                        ->where('j_category_id', $row->id)
                        ->where('language_id', $heId)
                        ->update(['image' => $row->image]);
                }
            }
        }

        // Швейцария: фото категории → язык ar (и все существующие description)
        if (Schema::hasTable('category_descriptions') && Schema::hasColumn('category_descriptions', 'image')) {
            $arId = DB::table('languages')->where('code', 'ar')->value('id');
            $cats = DB::table('categories')->whereNotNull('image')->where('image', '<>', '')->get(['id', 'image']);
            foreach ($cats as $row) {
                $q = DB::table('category_descriptions')->where('category_id', $row->id);
                if ($arId) {
                    $q->where('language_id', $arId);
                }
                $q->update(['image' => $row->image]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('j_category_descriptions', 'image')) {
            Schema::table('j_category_descriptions', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
        if (Schema::hasColumn('category_descriptions', 'image')) {
            Schema::table('category_descriptions', function (Blueprint $table) {
                $table->dropColumn('image');
            });
        }
    }
};
