<?php

namespace App\Services\BusinessListings;

use App\Models\DfsAggRun;
use App\Models\DfsAggRunCategory;
use App\Models\DfsBlCategory;
use App\Models\DfsBlTouristCategoryMatch;
use App\Services\DataForSeoClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Только Categories Aggregation API.
 * Запрещены Business Listings Search и любые операции с POI.
 */
class CategoriesAggregationOnlyService
{
    public function __construct(
        private DataForSeoClient $client
    ) {}

    /**
     * @return list<array{topic_group: string, category_code: string, category_name: string}>
     */
    public function resolveTouristCategories(): array
    {
        $fromMatches = $this->categoriesFromSavedMatches();
        if ($fromMatches !== []) {
            return $fromMatches;
        }

        return $this->categoriesFromCatalogKeywords();
    }

    /**
     * Планируемое число Aggregation API requests (без выполнения).
     */
    public function plannedApiRequests(?array $categories = null): int
    {
        $categories ??= $this->resolveTouristCategories();
        $codes = array_values(array_unique(array_map(
            fn ($row) => (string) $row['category_code'],
            $categories
        )));
        $chunk = max(1, min(10, (int) config('bern_tourist.category_chunk_size', 10)));

        return max(1, (int) ceil(count($codes) / $chunk));
    }

    /**
     * @return array{
     *   run: DfsAggRun,
     *   categories: list<array{category_code: string, category_name: string, objects_count: int}>,
     *   planned_requests: int,
     *   api_requests: int,
     *   api_cost: float|null,
     *   total_objects_reported: int,
     *   execution_time_ms: int
     * }
     */
    public function fetchAggregation(): array
    {
        $started = microtime(true);
        $collectedAt = Carbon::now();
        $endpoint = DataForSeoClient::BUSINESS_LISTINGS_CATEGORIES_AGGREGATION_URL;
        $this->assertAggregationEndpointOnly($endpoint);

        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN / DATAFORSEO_PASSWORD не заданы.');
        }

        $tourist = $this->resolveTouristCategories();
        if ($tourist === []) {
            throw new \RuntimeException(
                'Не найдены туристические категории. Сначала сохраните справочник Categories на существующей DataForSEO-странице или заполните dfs_bl_categories.'
            );
        }

        $categoryCodes = array_values(array_unique(array_map(
            fn ($row) => (string) $row['category_code'],
            $tourist
        )));
        $nameByCode = [];
        foreach ($tourist as $row) {
            $nameByCode[(string) $row['category_code']] = (string) $row['category_name'];
        }

        $destination = (string) config('bern_tourist.destination_label', 'Canton of Bern');
        $destinationSlug = (string) config('bern_tourist.destination_slug', 'bern');
        $locationCode = (int) config('bern_tourist.synthetic_location_code', 9100029);
        $locationCoordinate = (string) config('bern_tourist.location_coordinate');
        $chunkSize = max(1, min(10, (int) config('bern_tourist.category_chunk_size', 10)));
        $filters = $this->bernDatasetFilters();
        $planned = (int) ceil(count($categoryCodes) / $chunkSize);

        $run = DfsAggRun::query()->create([
            'destination' => $destination,
            'destination_slug' => $destinationSlug,
            'location_code' => $locationCode,
            'location_coordinate' => $locationCoordinate,
            'endpoint' => $endpoint,
            'categories_selected' => count($categoryCodes),
            'categories_processed' => 0,
            'api_requests' => 0,
            'total_objects_reported' => 0,
            'api_cost' => null,
            'execution_time_ms' => null,
            'status' => 'running',
            'error_message' => null,
            'meta' => [
                'planned_api_requests' => $planned,
                'category_codes' => $categoryCodes,
                'bbox' => config('bern_tourist.bbox'),
                'dataset_filters' => $filters,
                'note' => 'Aggregation only. No Business Listings Search. No POI.',
            ],
            'collected_at' => $collectedAt,
        ]);

        $countsByCode = [];
        $rawByCode = [];
        $apiRequests = 0;
        $apiCost = 0.0;
        $hasCost = false;

        try {
            foreach (array_chunk($categoryCodes, $chunkSize) as $chunkIndex => $chunk) {
                $this->assertAggregationEndpointOnly($endpoint);

                $payload = [[
                    'categories' => array_values($chunk),
                    'location_coordinate' => $locationCoordinate,
                    'initial_dataset_filters' => $filters,
                    'internal_list_limit' => 10,
                    'limit' => 1000,
                ]];

                $response = $this->client->post($endpoint, $payload, 300);
                $apiRequests++;

                if (isset($response['cost'])) {
                    $apiCost += (float) $response['cost'];
                    $hasCost = true;
                }

                $items = $this->extractAggregationItems($response);
                $requestedSet = array_fill_keys($chunk, true);

                foreach ($items as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $found = [];
                    $cats = $row['categories'] ?? $row['categoiries'] ?? null;
                    if (is_array($cats) && isset($cats[0]) && is_string($cats[0])) {
                        $code = trim($cats[0]);
                        if (isset($requestedSet[$code])) {
                            $agg = is_array($row['aggregation'] ?? null) ? $row['aggregation'] : [];
                            $found[$code] = (int) ($agg['count'] ?? $row['count'] ?? 0);
                        }
                    }

                    $top = $row['aggregation']['top_categories'] ?? null;
                    if (is_array($top)) {
                        foreach ($top as $topCode => $topCount) {
                            $topCode = trim((string) $topCode);
                            if ($topCode !== '' && isset($requestedSet[$topCode])) {
                                $found[$topCode] = max($found[$topCode] ?? 0, (int) $topCount);
                            }
                        }
                    }

                    foreach ($found as $code => $count) {
                        if (! isset($countsByCode[$code]) || $count > $countsByCode[$code]) {
                            $countsByCode[$code] = $count;
                            $rawByCode[$code] = $row;
                        }
                    }
                }

                // категории без ответа → 0
                foreach ($chunk as $code) {
                    if (! isset($countsByCode[$code])) {
                        $countsByCode[$code] = 0;
                        $rawByCode[$code] = ['missing_in_response' => true, 'chunk' => $chunkIndex];
                    }
                }
            }

            $rows = [];
            $totalObjects = 0;
            DB::transaction(function () use (
                $run,
                $categoryCodes,
                $countsByCode,
                $rawByCode,
                $nameByCode,
                $destination,
                $locationCode,
                $collectedAt,
                $hasCost,
                $apiCost,
                $apiRequests,
                &$rows,
                &$totalObjects
            ) {
                foreach ($categoryCodes as $code) {
                    $count = (int) ($countsByCode[$code] ?? 0);
                    $totalObjects += $count;
                    $rows[] = [
                        'category_code' => $code,
                        'category_name' => $nameByCode[$code] ?? $code,
                        'objects_count' => $count,
                    ];

                    DfsAggRunCategory::query()->create([
                        'run_id' => $run->id,
                        'destination' => $destination,
                        'location_code' => $locationCode,
                        'category_code' => $code,
                        'category_name' => $nameByCode[$code] ?? $code,
                        'objects_count' => $count,
                        'api_cost' => $hasCost ? $apiCost : null,
                        'raw_data' => $rawByCode[$code] ?? null,
                        'collected_at' => $collectedAt,
                    ]);
                }
            });

            $elapsedMs = (int) round((microtime(true) - $started) * 1000);
            $run->fill([
                'categories_processed' => count($rows),
                'api_requests' => $apiRequests,
                'total_objects_reported' => $totalObjects,
                'api_cost' => $hasCost ? round($apiCost, 6) : null,
                'execution_time_ms' => $elapsedMs,
                'status' => 'success',
            ])->save();

            return [
                'run' => $run->fresh('categories'),
                'categories' => $rows,
                'planned_requests' => $planned,
                'api_requests' => $apiRequests,
                'api_cost' => $hasCost ? round($apiCost, 6) : null,
                'total_objects_reported' => $totalObjects,
                'execution_time_ms' => $elapsedMs,
            ];
        } catch (\Throwable $e) {
            $run->fill([
                'api_requests' => $apiRequests,
                'api_cost' => $hasCost ? round($apiCost, 6) : null,
                'execution_time_ms' => (int) round((microtime(true) - $started) * 1000),
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    /**
     * Жёсткая проверка: разрешён только Categories Aggregation endpoint.
     */
    public function assertAggregationEndpointOnly(string $url): void
    {
        $allowed = DataForSeoClient::BUSINESS_LISTINGS_CATEGORIES_AGGREGATION_URL;
        $normalized = rtrim(strtolower(trim($url)), '/');
        $allowedNorm = rtrim(strtolower(trim($allowed)), '/');

        if ($normalized !== $allowedNorm) {
            throw new \RuntimeException(
                'Blocked: разрешён только Categories Aggregation API. Получен URL: '.$url
            );
        }

        if (
            str_contains($normalized, '/business_listings/search')
            || str_contains($normalized, '/listings/search')
            || str_contains($normalized, '/hotel_searches')
            || str_contains($normalized, '/google/maps')
        ) {
            throw new \RuntimeException(
                'Blocked: Business Listings Search / Listings endpoints запрещены на странице Aggregation.'
            );
        }
    }

    /**
     * @return list<array{topic_group: string, category_code: string, category_name: string}>
     */
    private function categoriesFromSavedMatches(): array
    {
        $latestAt = DfsBlTouristCategoryMatch::query()
            ->where('matched', true)
            ->whereNotNull('category_code')
            ->max('collected_at');

        if (! $latestAt) {
            return [];
        }

        $rows = DfsBlTouristCategoryMatch::query()
            ->where('matched', true)
            ->where('collected_at', $latestAt)
            ->whereNotNull('category_code')
            ->orderBy('topic_group')
            ->get();

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $code = trim((string) $row->category_code);
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $out[] = [
                'topic_group' => (string) $row->topic_group,
                'category_code' => $code,
                'category_name' => (string) ($row->category_name ?: $code),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{topic_group: string, category_code: string, category_name: string}>
     */
    private function categoriesFromCatalogKeywords(): array
    {
        $latestAt = DfsBlCategory::query()->max('collected_at');
        if (! $latestAt) {
            return [];
        }

        $catalog = DfsBlCategory::query()
            ->where('collected_at', $latestAt)
            ->get(['category_code', 'category_name']);

        if ($catalog->isEmpty()) {
            return [];
        }

        $topics = (array) config('bern_tourist.tourist_topic_keywords', []);
        $out = [];
        $seen = [];

        foreach ($topics as $group => $keywords) {
            $best = null;
            $bestScore = 0;

            foreach ($catalog as $cat) {
                $hay = mb_strtolower(trim((string) ($cat->category_name ?: $cat->category_code)));
                if ($hay === '') {
                    continue;
                }
                foreach ((array) $keywords as $kw) {
                    $needle = mb_strtolower(trim(str_replace(' ', '_', (string) $kw)));
                    if ($needle === '') {
                        continue;
                    }
                    $score = 0;
                    if ($hay === $needle) {
                        $score = 100;
                    } elseif (str_starts_with($hay, $needle.'_') || str_ends_with($hay, '_'.$needle)) {
                        $score = 90;
                    } elseif (str_contains($hay, $needle)) {
                        $score = 50 + min(30, mb_strlen($needle));
                    }
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = $cat;
                    }
                }
            }

            if ($best === null || $bestScore < 70) {
                continue;
            }

            $code = trim((string) ($best->category_code ?: $best->category_name));
            if ($code === '' || isset($seen[$code])) {
                continue;
            }
            $seen[$code] = true;
            $out[] = [
                'topic_group' => (string) $group,
                'category_code' => $code,
                'category_name' => (string) ($best->category_name ?: $code),
            ];
        }

        return $out;
    }

    /**
     * @return list<mixed>
     */
    private function bernDatasetFilters(): array
    {
        $bbox = (array) config('bern_tourist.bbox', []);

        return [
            ['address_info.country_code', '=', 'CH'],
            'and',
            ['latitude', '>=', (float) ($bbox['min_lat'] ?? 46.35)],
            'and',
            ['latitude', '<=', (float) ($bbox['max_lat'] ?? 47.28)],
            'and',
            ['longitude', '>=', (float) ($bbox['min_lng'] ?? 7.17)],
            'and',
            ['longitude', '<=', (float) ($bbox['max_lng'] ?? 8.35)],
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function extractAggregationItems(array $response): array
    {
        $tasks = $response['tasks'] ?? [];
        if (! is_array($tasks) || $tasks === []) {
            return [];
        }
        $result = $tasks[0]['result'] ?? null;
        if (! is_array($result) || $result === []) {
            return [];
        }
        $first = $result[0] ?? null;
        if (is_array($first) && isset($first['items']) && is_array($first['items'])) {
            return $first['items'];
        }

        return is_array($first) ? [$first] : [];
    }
}
