<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $fields = (array) config('ideal_region_category_fields', []);

        foreach ($fields as $field) {
            if (Schema::hasColumn('category_descriptions', $field)) {
                continue;
            }

            Schema::table('category_descriptions', function (Blueprint $table) use ($field) {
                if (str_ends_with($field, '_description')) {
                    $table->longText($field)->nullable();
                } else {
                    $table->string($field, 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $fields = (array) config('ideal_region_category_fields', []);
        $existing = array_values(array_filter(
            $fields,
            fn ($field) => Schema::hasColumn('category_descriptions', $field)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('category_descriptions', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }
};
