<?php

use App\Models\SwissApartment;
use App\Models\SwissRegion;
use App\Support\ApartmentPriceLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('swiss_apartments', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(2)->after('title');
            $table->index(['region_id', 'level']);
        });

        SwissRegion::query()->each(function (SwissRegion $region): void {
            $items = SwissApartment::query()
                ->where('region_id', $region->id)
                ->orderBy('price_usd')
                ->get(['id', 'price_usd'])
                ->map(static fn (SwissApartment $a) => [
                    'id' => $a->id,
                    'price' => (float) $a->price_usd,
                ])
                ->all();

            foreach (ApartmentPriceLevel::assign($items, 'price') as $item) {
                SwissApartment::query()
                    ->whereKey($item['id'])
                    ->update(['level' => (int) $item['level']]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('swiss_apartments', function (Blueprint $table) {
            $table->dropIndex(['region_id', 'level']);
            $table->dropColumn('level');
        });
    }
};
