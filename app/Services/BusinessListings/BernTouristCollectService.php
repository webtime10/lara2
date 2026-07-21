<?php

namespace App\Services\BusinessListings;

use App\Models\DfsBlCategory;
use App\Models\DfsBlCategoryAggregation;
use App\Models\DfsBlLocationCandidate;
use App\Models\DfsBlPoi;
use App\Models\DfsBlPoiCategory;
use App\Models\DfsBlRawResponse;
use App\Models\DfsBlTouristCategoryMatch;
use App\Services\DataForSeoClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BernTouristCollectService
{
    private int $apiRequests = 0;

    private float $apiCost = 0.0;

    public function __construct(
        private DataForSeoClient $client
    ) {}

    /**
     * Полный пайплайн сбора по кантону Bern.
     *
     * @return array<string, mixed>
     */
    public function collect(bool $skipListings = false, int $probeCategoryLimit = 3): array
    {
        $started = microtime(true);
        $collectedAt = Carbon::now();
        $destination = (string) config('bern_tourist.destination_slug', 'bern');
        $this->apiRequests = 0;
        $this->apiCost = 0.0;

        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN / DATAFORSEO_PASSWORD не заданы.');
        }

        $categories = $this->fetchAndStoreCategories($collectedAt);
        $matches = $this->matchTouristCategories($categories, $collectedAt);
        $matchedCategories = array_values(array_filter($matches, fn ($m) => $m['matched'] && ! empty($m['category_code'])));
        $categoryNames = array_values(array_unique(array_map(fn ($m) => (string) $m['category_code'], $matchedCategories)));

        $location = $this->resolveBernCantonLocation($collectedAt);
        $locationCode = (int) $location['location_code'];
        $locationCoordinate = (string) $location['location_coordinate'];

        $probeCats = array_slice($categoryNames, 0, max(1, $probeCategoryLimit));
        $probeAgg = $this->runCategoriesAggregation(
            $destination,
            $locationCode,
            $locationCoordinate,
            $probeCats,
            $collectedAt,
            'probe'
        );

        $fullAgg = $this->runCategoriesAggregation(
            $destination,
            $locationCode,
            $locationCoordinate,
            $categoryNames,
            $collectedAt,
            'full'
        );

        $poiStats = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'duplicates' => 0,
            'without_coords' => 0,
            'without_rating' => 0,
            'without_reviews' => 0,
        ];

        if (! $skipListings && $categoryNames !== []) {
            $poiStats = $this->fetchAndStoreListings(
                $destination,
                $locationCode,
                $locationCoordinate,
                $categoryNames,
                $collectedAt
            );
        }

        return [
            'destination_slug' => $destination,
            'location_code' => $locationCode,
            'location_coordinate' => $locationCoordinate,
            'location' => $location,
            'categories_total' => count($categories),
            'tourist_matches' => $matches,
            'tourist_matched_count' => count($matchedCategories),
            'tourist_unmatched_groups' => array_values(array_map(
                fn ($m) => $m['topic_group'],
                array_filter($matches, fn ($m) => ! $m['matched'])
            )),
            'probe_aggregation' => $probeAgg,
            'aggregation_counts' => $fullAgg,
            'poi' => $poiStats,
            'api_requests' => $this->apiRequests,
            'api_cost_estimate' => $this->apiCost,
            'elapsed_seconds' => round(microtime(true) - $started, 2),
            'collected_at' => $collectedAt->toDateTimeString(),
            'listings_skipped' => $skipListings,
        ];
    }

    /**
     * @return list<array{category_code: string, category_name: string, business_count: int|null, raw: array<string, mixed>}>
     */
    public function fetchAndStoreCategories(Carbon $collectedAt): array
    {
        $response = $this->client->get(DataForSeoClient::BUSINESS_LISTINGS_CATEGORIES_URL, 180);
        $this->trackCost($response);
        $this->storeRaw('business_listings/categories', 'bern', null, null, $response, $collectedAt);

        $items = $this->extractResultItems($response);
        $out = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['category_name'] ?? $item['title'] ?? $item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $code = trim((string) ($item['category_code'] ?? $item['code'] ?? $name));
            $count = isset($item['business_count']) ? (int) $item['business_count'] : null;

            DfsBlCategory::query()->create([
                'category_code' => $code,
                'category_name' => $name,
                'business_count' => $count,
                'source' => 'dataforseo_business_listings',
                'raw_data' => $item,
                'collected_at' => $collectedAt,
            ]);

            $out[] = [
                'category_code' => $code,
                'category_name' => $name,
                'business_count' => $count,
                'raw' => $item,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{category_code: string, category_name: string}>  $categories
     * @return list<array{topic_group: string, matched: bool, category_code: ?string, category_name: ?string, match_reason: ?string}>
     */
    public function matchTouristCategories(array $categories, Carbon $collectedAt): array
    {
        $topics = (array) config('bern_tourist.tourist_topic_keywords', []);
        $results = [];

        foreach ($topics as $group => $keywords) {
            $keywords = array_values(array_filter(array_map('strval', (array) $keywords)));
            $best = null;
            $bestScore = 0;
            $reason = null;

            foreach ($categories as $cat) {
                $hay = mb_strtolower(trim((string) ($cat['category_name'] ?? '')));
                if ($hay === '') {
                    continue;
                }
                foreach ($keywords as $kw) {
                    $needle = mb_strtolower(trim(str_replace(' ', '_', $kw)));
                    if ($needle === '') {
                        continue;
                    }
                    $score = 0;
                    if ($hay === $needle) {
                        $score = 100;
                    } elseif (str_starts_with($hay, $needle.'_') || str_ends_with($hay, '_'.$needle)) {
                        $score = 90;
                    } elseif (str_contains($hay, '_'.$needle.'_') || str_contains($hay, $needle)) {
                        // Prefer longer needles to avoid "tour" → tourist_attraction noise
                        $score = 50 + min(30, mb_strlen($needle));
                    }
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $best = $cat;
                        $reason = "keyword «{$kw}» matched «{$cat['category_name']}» (score {$score})";
                    }
                }
            }

            // discard weak fuzzy noise
            if ($best !== null && $bestScore < 70) {
                $best = null;
                $reason = 'no confident match in DataForSEO categories';
            }

            $row = [
                'topic_group' => (string) $group,
                'matched' => $best !== null,
                'category_code' => $best['category_code'] ?? null,
                'category_name' => $best['category_name'] ?? null,
                'match_reason' => $best !== null ? $reason : ($reason ?: 'no match in DataForSEO categories'),
            ];

            DfsBlTouristCategoryMatch::query()->create([
                'topic_group' => $row['topic_group'],
                'category_code' => $row['category_code'],
                'category_name' => $row['category_name'],
                'matched' => $row['matched'],
                'match_reason' => $row['match_reason'],
                'collected_at' => $collectedAt,
            ]);

            $results[] = $row;
        }

        return $results;
    }

    /**
     * @return array{
     *   location_code: int,
     *   location_name: string,
     *   location_type: ?string,
     *   location_coordinate: string,
     *   selection_reason: string,
     *   candidates: list<array<string, mixed>>
     * }
     */
    public function resolveBernCantonLocation(Carbon $collectedAt): array
    {
        $destination = (string) config('bern_tourist.destination_slug', 'bern');

        // BL locations API отдаёт только страны (path /ch не поддерживается → 40402).
        $response = $this->client->get(DataForSeoClient::BUSINESS_LISTINGS_LOCATIONS_URL, 180);
        $this->trackCost($response);
        $this->storeRaw('business_listings/locations', $destination, 'all', null, $response, $collectedAt);

        $items = $this->extractResultItems($response);
        $candidates = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $name = (string) ($item['location_name'] ?? '');
            $iso = strtoupper((string) ($item['country_iso_code'] ?? ''));
            if ($name === '') {
                continue;
            }
            // интересны CH и любые записи с Bern в имени (если появятся)
            if ($iso !== 'CH' && ! str_contains(mb_strtolower($name), 'bern')) {
                continue;
            }

            $candidates[] = [
                'location_code' => 0,
                'location_name' => $name,
                'location_type' => 'country',
                'country_iso_code' => $iso !== '' ? $iso : 'CH',
                'location_coordinate' => null,
                'raw' => $item,
                'is_selected' => false,
            ];
        }

        $coordinate = (string) config('bern_tourist.location_coordinate');
        $bbox = (array) config('bern_tourist.bbox', []);
        $syntheticCode = (int) config('bern_tourist.synthetic_location_code', 9100029);
        $filters = $this->bernDatasetFilters();
        $selected = [
            'location_code' => $syntheticCode,
            'location_name' => (string) config('bern_tourist.location_name', 'Canton of Bern'),
            'location_type' => (string) config('bern_tourist.location_type', 'canton_bbox'),
            'country_iso_code' => 'CH',
            'location_coordinate' => $coordinate,
            'raw' => [
                'strategy' => 'location_coordinate_plus_bbox',
                'location_coordinate' => $coordinate,
                'bbox' => $bbox,
                'dataset_filters' => $filters,
                'note' => 'Business Listings /locations = только страны. Для кантона Bern выбран bbox (не city location_code 20129).',
            ],
            'is_selected' => true,
        ];

        array_unshift($candidates, $selected);

        $reason = sprintf(
            'BL /locations: только country-level (Switzerland/CH). Canton location_code отсутствует. Выбран кантонный bbox lat[%.2f..%.2f] lng[%.2f..%.2f] + coordinate %s. Городской Google location_code=20129 не используется.',
            (float) ($bbox['min_lat'] ?? 0),
            (float) ($bbox['max_lat'] ?? 0),
            (float) ($bbox['min_lng'] ?? 0),
            (float) ($bbox['max_lng'] ?? 0),
            $coordinate
        );

        DfsBlLocationCandidate::query()
            ->where('destination_slug', $destination)
            ->delete();

        foreach ($candidates as $i => $cand) {
            DfsBlLocationCandidate::query()->create([
                'destination_slug' => $destination,
                'location_code' => (int) ($cand['location_code'] ?? 0),
                'location_name' => $cand['location_name'],
                'location_type' => $cand['location_type'] ?? null,
                'country_iso_code' => $cand['country_iso_code'] ?? null,
                'is_selected' => ! empty($cand['is_selected']) || $i === 0,
                'selection_reason' => ($i === 0) ? $reason : null,
                'raw_data' => $cand['raw'] ?? null,
                'collected_at' => $collectedAt,
            ]);
        }

        return [
            'location_code' => $syntheticCode,
            'location_name' => (string) $selected['location_name'],
            'location_type' => (string) $selected['location_type'],
            'location_coordinate' => $coordinate,
            'selection_reason' => $reason,
            'candidates' => $candidates,
        ];
    }

    /**
     * @param  list<string>  $categoryNames
     * @return list<array{category_code: string, category_name: string, objects_count: int}>
     */
    public function runCategoriesAggregation(
        string $destination,
        int $locationCode,
        string $locationCoordinate,
        array $categoryNames,
        Carbon $collectedAt,
        string $phase = 'full'
    ): array {
        $categoryNames = array_values(array_unique(array_filter(array_map('strval', $categoryNames))));
        if ($categoryNames === []) {
            return [];
        }

        $chunkSize = max(1, min(10, (int) config('bern_tourist.category_chunk_size', 10)));
        $filters = $this->bernDatasetFilters();
        $counts = [];

        foreach (array_chunk($categoryNames, $chunkSize) as $chunkIndex => $chunk) {
            $payload = [[
                'categories' => array_values($chunk),
                'location_coordinate' => $locationCoordinate,
                'initial_dataset_filters' => $filters,
                'internal_list_limit' => 10,
                'limit' => 1000,
            ]];

            $response = $this->client->post(
                DataForSeoClient::BUSINESS_LISTINGS_CATEGORIES_AGGREGATION_URL,
                $payload,
                300
            );
            $this->trackCost($response);
            $this->storeRaw(
                'business_listings/categories_aggregation/'.$phase,
                $destination,
                $locationCoordinate.':'.$chunkIndex,
                $payload,
                $response,
                $collectedAt
            );

            $items = $this->extractListingItems($response);
            if ($items === []) {
                // fallback: result itself may be flat list
                $items = $this->extractResultItems($response);
            }

            foreach ($items as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $requestedSet = array_fill_keys($chunk, true);
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
                        if ($topCode !== '' && isset($requestedSet[$topCode]) && ! isset($found[$topCode])) {
                            $found[$topCode] = (int) $topCount;
                        }
                    }
                }

                foreach ($found as $code => $count) {
                    $counts[] = [
                        'category_code' => $code,
                        'category_name' => $code,
                        'objects_count' => $count,
                    ];

                    if ($phase === 'full') {
                        DfsBlCategoryAggregation::query()->create([
                            'destination_slug' => $destination,
                            'location_code' => $locationCode,
                            'category_code' => $code,
                            'category_name' => $code,
                            'objects_count' => $count,
                            'source' => 'dataforseo_categories_aggregation',
                            'raw_data' => $row,
                            'collected_at' => $collectedAt,
                        ]);
                    }
                }
            }
        }

        // merge max count per requested category for summary
        $merged = [];
        foreach ($counts as $row) {
            $code = $row['category_code'];
            if (! isset($merged[$code]) || $row['objects_count'] > $merged[$code]['objects_count']) {
                $merged[$code] = $row;
            }
        }

        return array_values($merged);
    }

    /**
     * @param  list<string>  $categoryNames
     * @return array{fetched: int, created: int, updated: int, duplicates: int, without_coords: int, without_rating: int, without_reviews: int}
     */
    public function fetchAndStoreListings(
        string $destination,
        int $locationCode,
        string $locationCoordinate,
        array $categoryNames,
        Carbon $collectedAt
    ): array {
        $stats = [
            'fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'duplicates' => 0,
            'without_coords' => 0,
            'without_rating' => 0,
            'without_reviews' => 0,
        ];

        $seenExternal = [];
        $limit = 100;
        $filters = $this->bernDatasetFilters();

        foreach ($categoryNames as $category) {
            $offset = 0;
            $page = 0;
            $offsetToken = null;

            while (true) {
                $page++;
                $task = [
                    'categories' => [$category],
                    'location_coordinate' => $locationCoordinate,
                    'filters' => $filters,
                    'limit' => $limit,
                    'offset' => $offset,
                ];
                if ($offsetToken) {
                    $task['offset_token'] = $offsetToken;
                }

                $payload = [$task];

                $response = $this->client->post(
                    DataForSeoClient::BUSINESS_LISTINGS_SEARCH_URL,
                    $payload,
                    300
                );
                $this->trackCost($response);
                $this->storeRaw(
                    'business_listings/search',
                    $destination,
                    $category.':'.$offset,
                    $payload,
                    $response,
                    $collectedAt
                );

                $items = $this->extractListingItems($response);
                if ($items === []) {
                    break;
                }

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $stats['fetched']++;
                    $result = $this->upsertPoi($destination, $locationCode, $item, $category, $collectedAt);

                    if ($result['status'] === 'created') {
                        $stats['created']++;
                    } elseif ($result['status'] === 'updated') {
                        $stats['updated']++;
                    }

                    $ext = $result['external_id'];
                    if (isset($seenExternal[$ext])) {
                        $stats['duplicates']++;
                    }
                    $seenExternal[$ext] = true;

                    if ($result['without_coords']) {
                        $stats['without_coords']++;
                    }
                    if ($result['without_rating']) {
                        $stats['without_rating']++;
                    }
                    if ($result['without_reviews']) {
                        $stats['without_reviews']++;
                    }
                }

                $offsetToken = $this->extractOffsetToken($response);
                if (count($items) < $limit) {
                    break;
                }
                $offset += $limit;
                if ($page >= 100) {
                    break;
                }
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractOffsetToken(array $response): ?string
    {
        $tasks = $response['tasks'] ?? [];
        if (! is_array($tasks) || $tasks === []) {
            return null;
        }
        $result = $tasks[0]['result'][0] ?? null;
        if (! is_array($result)) {
            return null;
        }
        $token = $result['offset_token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{status: string, external_id: string, without_coords: bool, without_rating: bool, without_reviews: bool}
     */
    private function upsertPoi(
        string $destination,
        int $locationCode,
        array $item,
        string $requestCategory,
        Carbon $collectedAt
    ): array {
        $externalId = $this->resolveExternalId($item);
        $dedupHash = hash('sha256', $destination.'|'.$externalId);

        $lat = $this->nullableFloat($item['latitude'] ?? $item['gps_coordinates']['latitude'] ?? null);
        $lng = $this->nullableFloat($item['longitude'] ?? $item['gps_coordinates']['longitude'] ?? null);
        $rating = $this->nullableFloat($item['rating']['value'] ?? $item['rating'] ?? null);
        $reviews = isset($item['rating']['votes_count'])
            ? (int) $item['rating']['votes_count']
            : (isset($item['reviews_count']) ? (int) $item['reviews_count'] : null);

        $addressInfo = is_array($item['address_info'] ?? null) ? $item['address_info'] : [];
        $name = (string) ($item['title'] ?? $item['name'] ?? '');
        $primary = (string) ($item['category'] ?? $item['main_category'] ?? $requestCategory);

        $attrs = [
            'destination_slug' => $destination,
            'location_code' => $locationCode,
            'external_id' => $externalId,
            'dedup_hash' => $dedupHash,
            'name' => $name !== '' ? Str::limit($name, 250, '') : null,
            'title' => isset($item['title']) ? Str::limit((string) $item['title'], 250, '') : null,
            'primary_category' => $primary !== '' ? Str::limit($primary, 250, '') : null,
            'latitude' => $lat,
            'longitude' => $lng,
            'address' => isset($item['address']) ? Str::limit((string) $item['address'], 490, '') : (isset($addressInfo['address']) ? Str::limit((string) $addressInfo['address'], 490, '') : null),
            'city' => isset($addressInfo['city']) ? (string) $addressInfo['city'] : (isset($item['city']) ? (string) $item['city'] : null),
            'region' => isset($addressInfo['region']) ? (string) $addressInfo['region'] : (isset($item['region']) ? (string) $item['region'] : null),
            'postal_code' => isset($addressInfo['zip']) ? (string) $addressInfo['zip'] : (isset($item['postal_code']) ? (string) $item['postal_code'] : null),
            'country_code' => isset($addressInfo['country_code']) ? (string) $addressInfo['country_code'] : (isset($item['country_code']) ? (string) $item['country_code'] : 'CH'),
            'rating' => $rating,
            'reviews_count' => $reviews,
            'phone' => isset($item['phone']) ? Str::limit((string) $item['phone'], 60, '') : null,
            'website' => isset($item['url']) ? Str::limit((string) $item['url'], 490, '') : (isset($item['website']) ? Str::limit((string) $item['website'], 490, '') : null),
            'working_hours' => $item['work_hours'] ?? $item['working_hours'] ?? null,
            'source' => 'dataforseo_business_listings',
            'raw_data' => $item,
            'collected_at' => $collectedAt,
        ];

        /** @var DfsBlPoi|null $existing */
        $existing = DfsBlPoi::query()
            ->where('destination_slug', $destination)
            ->where('external_id', $externalId)
            ->first();

        $status = 'created';
        if ($existing) {
            $existing->fill($attrs)->save();
            $poi = $existing;
            $status = 'updated';
            $poi->categories()->delete();
        } else {
            $poi = DfsBlPoi::query()->create($attrs);
        }

        $categoryNames = [];
        if ($primary !== '') {
            $categoryNames[] = $primary;
        }
        foreach ((array) ($item['additional_categories'] ?? $item['categories'] ?? []) as $extra) {
            if (is_string($extra) && trim($extra) !== '') {
                $categoryNames[] = trim($extra);
            } elseif (is_array($extra)) {
                $n = trim((string) ($extra['category_name'] ?? $extra['name'] ?? ''));
                if ($n !== '') {
                    $categoryNames[] = $n;
                }
            }
        }
        if ($requestCategory !== '' && ! in_array($requestCategory, $categoryNames, true)) {
            $categoryNames[] = $requestCategory;
        }
        $categoryNames = array_values(array_unique($categoryNames));

        foreach ($categoryNames as $i => $catName) {
            DfsBlPoiCategory::query()->create([
                'poi_id' => $poi->id,
                'category_code' => $catName,
                'category_name' => $catName,
                'is_primary' => $i === 0,
            ]);
        }

        return [
            'status' => $status,
            'external_id' => $externalId,
            'without_coords' => $lat === null || $lng === null,
            'without_rating' => $rating === null,
            'without_reviews' => $reviews === null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function resolveExternalId(array $item): string
    {
        foreach (['place_id', 'feature_id', 'cid', 'id'] as $key) {
            if (! empty($item[$key]) && is_scalar($item[$key])) {
                return Str::limit((string) $item[$key], 180, '');
            }
        }

        $name = (string) ($item['title'] ?? $item['name'] ?? '');
        $lat = (string) ($item['latitude'] ?? $item['gps_coordinates']['latitude'] ?? '');
        $lng = (string) ($item['longitude'] ?? $item['gps_coordinates']['longitude'] ?? '');
        $address = (string) ($item['address'] ?? '');

        return 'hash:'.substr(hash('sha256', mb_strtolower($name.'|'.$lat.'|'.$lng.'|'.$address)), 0, 40);
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
     * @param  array{location_name?: string, location_type?: string}  $cand
     */
    private function bernLocationScore(array $cand): int
    {
        // legacy helper kept for BC; canton selection uses bbox strategy
        $name = mb_strtolower((string) ($cand['location_name'] ?? ''));
        $type = mb_strtolower((string) ($cand['location_type'] ?? ''));
        $score = 0;

        if (str_contains($name, 'bern')) {
            $score += 10;
        }
        if (str_contains($name, 'canton') || str_contains($name, 'kanton')) {
            $score += 50;
        }
        if (str_contains($type, 'canton') || str_contains($type, 'region') || str_contains($type, 'state') || str_contains($type, 'province')) {
            $score += 40;
        }
        if (str_contains($type, 'city') || str_contains($type, 'municipality') || str_contains($name, 'city of')) {
            $score -= 30;
        }
        if ($name === 'bern' || $name === 'berne') {
            $score -= 5;
        }

        return $score;
    }

    /**
     * @param  array{location_name: string, location_type?: string|null, location_code: int}  $selected
     * @param  list<array{location_name: string, location_type?: string|null, location_code: int}>  $candidates
     */
    private function describeBernSelection(array $selected, array $candidates): string
    {
        $parts = [
            'Выбран location_code='.$selected['location_code'].' «'.$selected['location_name'].'»',
            'type='.($selected['location_type'] ?? 'n/a'),
            'Приоритет: canton/region над city. Найдено кандидатов с «Bern»: '.count($candidates).'.',
        ];

        return implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>  $response
     */
    private function storeRaw(
        string $endpoint,
        ?string $destination,
        ?string $requestKey,
        ?array $payload,
        array $response,
        Carbon $collectedAt
    ): void {
        DfsBlRawResponse::query()->create([
            'endpoint' => $endpoint,
            'destination_slug' => $destination,
            'request_key' => $requestKey,
            'payload_json' => $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'response_json' => json_encode($response, JSON_UNESCAPED_UNICODE),
            'http_cost' => isset($response['cost']) ? (int) round(((float) $response['cost']) * 1000000) : null,
            'collected_at' => $collectedAt,
        ]);

        // also keep a file copy
        $dir = storage_path('app/dataforseo/bern/'.$collectedAt->format('Ymd_His'));
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $safe = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $endpoint.'__'.($requestKey ?? 'main')) ?: 'raw';
        file_put_contents($dir.'/'.$safe.'.json', json_encode([
            'payload' => $payload,
            'response' => $response,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function trackCost(array $response): void
    {
        $this->apiRequests++;
        if (isset($response['cost'])) {
            $this->apiCost += (float) $response['cost'];
        }
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<mixed>
     */
    private function extractResultItems(array $response): array
    {
        $tasks = $response['tasks'] ?? [];
        if (! is_array($tasks) || $tasks === []) {
            return [];
        }
        $result = $tasks[0]['result'] ?? null;
        if (! is_array($result)) {
            return [];
        }
        // sometimes result is list of rows; sometimes [{items:[...]}]
        if (isset($result[0]) && is_array($result[0]) && isset($result[0]['items']) && is_array($result[0]['items'])) {
            return $result[0]['items'];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return list<array<string, mixed>>
     */
    private function extractListingItems(array $response): array
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
            return array_values(array_filter($first['items'], 'is_array'));
        }

        return array_values(array_filter($result, 'is_array'));
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
