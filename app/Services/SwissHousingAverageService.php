<?php

namespace App\Services;

use App\Models\SwissApartment;
use App\Models\SwissHotel;
use App\Models\SwissRegion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class SwissHousingAverageService
{
    /** @var list<int> */
    public const LEVELS = [1, 2, 3];

    /** @var array<int, string> */
    public const LEVEL_LABELS = [
        1 => 'Эконом',
        2 => 'Средний',
        3 => 'Премиум',
    ];

    /** @return Collection<int, array<string, mixed>> */
    public function hotelRows(): Collection
    {
        return $this->rowsFor(SwissHotel::class, 'hotels_synced_at');
    }

    /** @return Collection<int, array<string, mixed>> */
    public function apartmentRows(): Collection
    {
        return $this->rowsFor(SwissApartment::class, 'apartments_synced_at');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function averageForHotel(SwissRegion $region, int $level): ?array
    {
        return $this->averageFor(SwissHotel::class, $region, $level);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function averageForApartment(SwissRegion $region, int $level): ?array
    {
        return $this->averageFor(SwissApartment::class, $region, $level);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return Collection<int, array<string, mixed>>
     */
    private function rowsFor(string $modelClass, string $syncColumn): Collection
    {
        $regions = SwissRegion::query()->orderBy('label')->get();
        $stats = $this->aggregateStats($modelClass);
        $totals = $this->totalsByRegion($modelClass);

        return $regions->map(function (SwissRegion $region) use ($stats, $syncColumn, $totals): array {
            $levels = [];
            foreach (self::LEVELS as $level) {
                $levels[$level] = $this->mapStatRow($stats->get($region->id.'_'.$level));
            }

            $filledLevels = count(array_filter($levels));

            return [
                'region' => $region,
                'levels' => $levels,
                'synced_at' => $region->{$syncColumn},
                'total_count' => (int) ($totals[$region->id] ?? 0),
                'filled_levels' => $filledLevels,
                'has_averages' => $filledLevels === count(self::LEVELS),
            ];
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<string, mixed>|null
     */
    private function averageFor(string $modelClass, SwissRegion $region, int $level): ?array
    {
        $row = $modelClass::query()
            ->where('region_id', $region->id)
            ->where('level', $level)
            ->selectRaw('AVG(price_usd) as avg_price, MIN(price_usd) as min_price, MAX(price_usd) as max_price, COUNT(*) as items_count')
            ->first();

        return $this->mapStatRow($row);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return Collection<string, object>
     */
    private function aggregateStats(string $modelClass): Collection
    {
        return $modelClass::query()
            ->selectRaw('region_id, level, AVG(price_usd) as avg_price, MIN(price_usd) as min_price, MAX(price_usd) as max_price, COUNT(*) as items_count')
            ->groupBy('region_id', 'level')
            ->get()
            ->keyBy(fn ($row): string => $row->region_id.'_'.$row->level);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, int>
     */
    private function totalsByRegion(string $modelClass): array
    {
        return $modelClass::query()
            ->selectRaw('region_id, COUNT(*) as items_count')
            ->groupBy('region_id')
            ->pluck('items_count', 'region_id')
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapStatRow(?object $row): ?array
    {
        if ($row === null || (int) ($row->items_count ?? 0) <= 0) {
            return null;
        }

        $avg = (float) ($row->avg_price ?? 0);
        if ($avg <= 0.0) {
            return null;
        }

        return [
            'avg' => round($avg, 2),
            'min' => round((float) ($row->min_price ?? 0), 2),
            'max' => round((float) ($row->max_price ?? 0), 2),
            'count' => (int) $row->items_count,
        ];
    }
}
