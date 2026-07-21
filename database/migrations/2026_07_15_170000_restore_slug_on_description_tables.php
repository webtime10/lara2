<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->restoreSlug('category_descriptions', 'category');
        $this->restoreSlug('product_descriptions', 'product');
    }

    public function down(): void
    {
        // Keep slug column; earlier drop migration is obsolete.
    }

    private function restoreSlug(string $table, string $fallbackPrefix): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $fkName = $table.'_language_id_foreign';
        $badUnique = $table.'_language_id_slug_unique';

        // Orphan unique index (language_id only) blocks multiple rows per language
        // and is also used by the language_id FK — drop FK first.
        if ($this->foreignKeyExists($table, $fkName)) {
            Schema::table($table, function (Blueprint $blueprint) use ($fkName) {
                $blueprint->dropForeign($fkName);
            });
        }

        $this->dropIndexIfExists($table, $badUnique);

        if (! Schema::hasColumn($table, 'slug')) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('slug', 255)->nullable()->after('name');
            });
        }

        $rows = DB::table($table)->select('id', 'language_id', 'name', 'slug')->get();
        $used = [];

        foreach ($rows as $row) {
            if (filled($row->slug)) {
                $used[(int) $row->language_id][$row->slug] = true;
                continue;
            }

            $base = Str::slug((string) $row->name);
            if ($base === '') {
                $base = $fallbackPrefix.'-'.$row->id;
            }

            $slug = $base;
            $n = 2;
            while (isset($used[(int) $row->language_id][$slug])) {
                $slug = $base.'-'.$n;
                $n++;
            }

            $used[(int) $row->language_id][$slug] = true;
            DB::table($table)->where('id', $row->id)->update(['slug' => $slug]);
        }

        $this->dropIndexIfExists($table, $badUnique);

        Schema::table($table, function (Blueprint $blueprint) use ($badUnique) {
            $blueprint->unique(['language_id', 'slug'], $badUnique);
        });

        if (! $this->foreignKeyExists($table, $fkName)) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreign('language_id')
                    ->references('id')
                    ->on('languages')
                    ->cascadeOnDelete();
            });
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        $rows = DB::select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            [$table, $constraint, 'FOREIGN KEY']
        );

        return count($rows) > 0;
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        $exists = collect(DB::select('SHOW INDEX FROM '.$table))
            ->contains(fn ($row) => $row->Key_name === $index);

        if ($exists) {
            Schema::table($table, function (Blueprint $blueprint) use ($index) {
                $blueprint->dropUnique($index);
            });
        }
    }
};
