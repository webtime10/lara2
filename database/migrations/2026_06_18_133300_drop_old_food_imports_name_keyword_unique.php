<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OLD_INDEX = 'food_imports_region_id_name_keyword_unique';

    public function up(): void
    {
        if ($this->indexExists(self::OLD_INDEX)) {
            Schema::table('food_imports', function (Blueprint $table) {
                $table->dropUnique(self::OLD_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (! $this->indexExists(self::OLD_INDEX)) {
            Schema::table('food_imports', function (Blueprint $table) {
                $table->unique(['region_id', 'name', 'keyword'], self::OLD_INDEX);
            });
        }
    }

    private function indexExists(string $indexName): bool
    {
        $database = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, 'food_imports', $indexName]
        );

        return $rows !== [];
    }
};
