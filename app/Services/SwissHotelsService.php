<?php

namespace App\Services;

use App\Models\SwissHotel;
use App\Models\SwissHotelOccupancyPrice;
use App\Models\SwissHotelOccupancySetting;
use App\Models\SwissHotelSyncState;
use App\Models\SwissRegion;
use App\Support\ApartmentPriceLevel;
use App\Support\DataForSeoTitle;
use App\Support\HotelOccupancyCatalog;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SwissHotelsService
{
    private const PER_PAGE = 100;

    public function __construct(
        private DataForSeoClient $client,
    ) {}

    /** @return Collection<int, SwissRegion> */
    public function regions(): Collection
    {
        return SwissRegion::query()->withCount('hotels')->orderBy('label')->get();
    }

    public function findRegion(string $slug): ?SwissRegion
    {
        return SwissRegion::query()->where('slug', $slug)->first();
    }

    public function apiHint(SwissRegion $region): string
    {
        return 'location_code='.$region->location_code.' ('.$region->label.', Switzerland)';
    }

    /** Загрузить из API и сохранить в swiss_hotels. */
    public function syncFromApi(string $slug): int
    {
        $region = $this->findRegion($slug);
        if ($region === null) {
            throw new \InvalidArgumentException('Неизвестный регион: '.$slug);
        }

        $items = ApartmentPriceLevel::assign($this->dedupeByTitle($this->fetchHotelsFromApi($region)), 'price');
        $now = now();

        DB::transaction(function () use ($region, $items, $now): void {
            SwissHotel::query()->where('region_id', $region->id)->delete();

            if ($items !== []) {
                $rows = [];
                foreach ($items as $item) {
                    $rows[] = [
                        'region_id' => $region->id,
                        'title' => $item['title'],
                        'hotel_identifier' => $item['hotel_identifier'] ?? null,
                        'level' => (int) $item['level'],
                        'stars' => $item['stars'],
                        'price_usd' => $item['price'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                SwissHotel::query()->insert($rows);
            }

            $region->update(['hotels_synced_at' => $now]);
        });

        return count($items);
    }

    public function paginateFromDb(string $slug, Request $request): LengthAwarePaginator
    {
        $region = $this->findRegion($slug);
        if ($region === null) {
            throw new \InvalidArgumentException('Неизвестный регион: '.$slug);
        }

        return SwissHotel::query()
            ->where('region_id', $region->id)
            ->orderBy('price_usd')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function lastFullSyncAt(): ?Carbon
    {
        return SwissHotelSyncState::current()->last_full_sync_at;
    }

    public function markFullSyncComplete(): Carbon
    {
        $state = SwissHotelSyncState::current();
        $now = now();
        $state->update(['last_full_sync_at' => $now]);

        return $now;
    }

    /**
     * @return list<array{title: string, hotel_identifier: ?string, stars: ?int, price: float}>
     */
    private function fetchHotelsFromApi(SwissRegion $region): array
    {
        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN или DATAFORSEO_PASSWORD не заданы в .env');
        }

        $response = $this->client->post(DataForSeoClient::HOTEL_SEARCHES_URL, [[
            'location_code' => $region->location_code,
            'keyword' => 'hotels',
            'currency' => 'USD',
            'language_code' => 'en',
            'depth' => 140,
        ]]);

        $rawItems = $response['tasks'][0]['result'][0]['items'] ?? [];
        $items = [];

        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mapped = $this->mapItem($item);
            if ($mapped !== null) {
                $items[] = $mapped;
            }
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{title: string, hotel_identifier: ?string, stars: ?int, price: float}|null
     */
    private function mapItem(array $item): ?array
    {
        $title = DataForSeoTitle::normalize($item['title'] ?? null);
        $stars = $item['stars'] ?? null;
        $prices = is_array($item['prices'] ?? null) ? $item['prices'] : [];
        $price = $prices['price'] ?? null;
        $identifier = trim((string) ($item['hotel_identifier'] ?? ''));

        if ($title === null) {
            return null;
        }
        if ($price === null || (float) $price <= 0.0) {
            return null;
        }

        $starsInt = ($stars !== null && (int) $stars > 0) ? (int) $stars : null;

        return [
            'title' => $title,
            'hotel_identifier' => $identifier !== '' ? $identifier : null,
            'stars' => $starsInt,
            'price' => (float) $price,
        ];
    }

    /**
     * Активная сетка occupancy из настроек (чекбоксы в «Отели — настройки»).
     *
     * @return list<array{key: string, label: string, adults: int, children: list<int>}>
     */
    public function occupancyGrid(): array
    {
        $setting = SwissHotelOccupancySetting::current();
        $enabled = HotelOccupancyCatalog::enabled($setting->selected_keys);

        return array_map(static fn (array $cell) => [
            'key' => $cell['key'],
            'label' => $cell['label'],
            'adults' => $cell['adults'],
            'children' => $cell['children'],
        ], $enabled);
    }

    /**
     * @return list<string>
     */
    public function selectedOccupancyKeys(): array
    {
        $setting = SwissHotelOccupancySetting::current();
        $keys = $setting->selected_keys;
        if (! is_array($keys) || $keys === []) {
            return HotelOccupancyCatalog::defaultSelectedKeys();
        }

        return array_values(array_map('strval', $keys));
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    public function saveOccupancySelection(array $keys): array
    {
        $allowed = array_fill_keys(
            array_column(HotelOccupancyCatalog::all(), 'key'),
            true
        );
        $clean = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            if (isset($allowed[$key])) {
                $clean[$key] = $key;
            }
        }
        $clean = array_values($clean);
        if ($clean === []) {
            $clean = HotelOccupancyCatalog::defaultSelectedKeys();
        }

        $setting = SwissHotelOccupancySetting::current();
        $setting->forceFill(['selected_keys' => $clean])->save();

        return $clean;
    }

    public function findHotel(string $slug, int $hotelId): ?SwissHotel
    {
        $region = $this->findRegion($slug);
        if ($region === null) {
            return null;
        }

        return SwissHotel::query()
            ->where('region_id', $region->id)
            ->where('id', $hotelId)
            ->first();
    }

    /**
     * Сохранённые ячейки occupancy из БД (по hotel_identifier).
     *
     * @return array{
     *     check_in: ?string,
     *     check_out: ?string,
     *     fetched_at: ?string,
     *     cells: list<array{key: string, label: string, adults: int, children: list<int>, price: ?float, error: ?string, cost: ?float}>
     * }|null
     */
    public function storedOccupancyPrices(SwissHotel $hotel): ?array
    {
        $identifier = trim((string) ($hotel->hotel_identifier ?? ''));
        if ($identifier === '') {
            return null;
        }

        $rows = SwissHotelOccupancyPrice::query()
            ->where('region_id', $hotel->region_id)
            ->where('hotel_identifier', $identifier)
            ->get()
            ->keyBy('occupancy_key');

        if ($rows->isEmpty()) {
            return null;
        }

        $cells = [];
        $checkIn = null;
        $checkOut = null;
        $fetchedAt = null;

        foreach ($this->occupancyGrid() as $cell) {
            $row = $rows->get($cell['key']);
            $cells[] = [
                'key' => $cell['key'],
                'label' => $cell['label'],
                'adults' => $cell['adults'],
                'children' => $cell['children'],
                'price' => $row && $row->price_usd !== null ? (float) $row->price_usd : null,
                'error' => $row?->error,
                'cost' => $row && $row->api_cost !== null ? (float) $row->api_cost : null,
            ];
            if ($row) {
                $checkIn = $row->check_in?->format('Y-m-d') ?? $checkIn;
                $checkOut = $row->check_out?->format('Y-m-d') ?? $checkOut;
                if ($row->fetched_at && ($fetchedAt === null || $row->fetched_at->gt($fetchedAt))) {
                    $fetchedAt = $row->fetched_at;
                }
            }
        }

        return [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'fetched_at' => $fetchedAt?->format('d.m.Y H:i'),
            'cells' => $cells,
        ];
    }

    /**
     * Одна ячейка occupancy через DataForSEO + сохранение.
     *
     * @return array{check_in: string, check_out: string, fetched_at: string, skipped?: bool, cell: array{key: string, label: string, adults: int, children: list<int>, price: ?float, error: ?string, cost: ?float}}
     */
    public function fetchOccupancyCell(SwissHotel $hotel, SwissRegion $region, string $key, bool $skipIfFilled = false): array
    {
        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN или DATAFORSEO_PASSWORD не заданы в .env');
        }

        $identifier = trim((string) ($hotel->hotel_identifier ?? ''));
        if ($identifier === '') {
            throw new \RuntimeException(
                'У отеля нет hotel_identifier. Обновите кантон из API, затем откройте отель снова.'
            );
        }

        $cell = $this->resolveOccupancyCell($key);
        if ($cell === null) {
            throw new \InvalidArgumentException('Неизвестная ячейка occupancy: '.$key);
        }

        if ($skipIfFilled) {
            $existing = SwissHotelOccupancyPrice::query()
                ->where('region_id', $region->id)
                ->where('hotel_identifier', $identifier)
                ->where('occupancy_key', $cell['key'])
                ->whereNotNull('price_usd')
                ->where('price_usd', '>', 0)
                ->first();
            if ($existing) {
                return [
                    'check_in' => $existing->check_in?->format('Y-m-d') ?? '',
                    'check_out' => $existing->check_out?->format('Y-m-d') ?? '',
                    'fetched_at' => $existing->fetched_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i'),
                    'skipped' => true,
                    'cell' => [
                        'key' => $cell['key'],
                        'label' => $cell['label'],
                        'adults' => $cell['adults'],
                        'children' => $cell['children'],
                        'price' => (float) $existing->price_usd,
                        'error' => null,
                        'cost' => $existing->api_cost !== null ? (float) $existing->api_cost : null,
                    ],
                ];
            }
        }

        $datePairs = [
            [now()->addDays(7)->format('Y-m-d'), now()->addDays(8)->format('Y-m-d')],
            [now()->addDays(14)->format('Y-m-d'), now()->addDays(15)->format('Y-m-d')],
            [now()->addDays(30)->format('Y-m-d'), now()->addDays(31)->format('Y-m-d')],
            [now()->addDays(60)->format('Y-m-d'), now()->addDays(61)->format('Y-m-d')],
        ];

        $fetchedAt = now();
        $price = null;
        $error = null;
        $cost = null;
        $checkIn = $datePairs[0][0];
        $checkOut = $datePairs[0][1];
        $lastApiError = null;

        foreach ($datePairs as [$tryIn, $tryOut]) {
            try {
                $payload = [
                    'hotel_identifier' => $identifier,
                    'location_code' => (int) $region->location_code,
                    'language_code' => 'en',
                    'currency' => 'USD',
                    'check_in' => $tryIn,
                    'check_out' => $tryOut,
                    'adults' => $cell['adults'],
                ];
                if ($cell['children'] !== []) {
                    $payload['children'] = $cell['children'];
                }

                $response = $this->client->post(DataForSeoClient::HOTEL_INFO_URL, [$payload], 90);
                $result = $response['tasks'][0]['result'][0] ?? null;
                $prices = is_array($result) && is_array($result['prices'] ?? null) ? $result['prices'] : [];
                $rawPrice = $prices['price'] ?? null;
                $cost = isset($response['tasks'][0]['cost']) ? (float) $response['tasks'][0]['cost'] : null;
                $checkIn = $tryIn;
                $checkOut = $tryOut;

                if ($rawPrice !== null && (float) $rawPrice > 0) {
                    $price = (float) $rawPrice;
                    $error = null;
                    break;
                }

                $error = 'Нет цены в ответе API';
            } catch (\Throwable $e) {
                $lastApiError = $e->getMessage();
                $error = $lastApiError;
            }
        }

        if ($price === null && $error === null) {
            $error = $lastApiError ?: 'Нет цены в ответе API';
        }

        SwissHotelOccupancyPrice::query()->updateOrCreate(
            [
                'region_id' => $region->id,
                'hotel_identifier' => $identifier,
                'occupancy_key' => $cell['key'],
            ],
            [
                'adults' => $cell['adults'],
                'children' => $cell['children'],
                'price_usd' => $price,
                'error' => $error,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'api_cost' => $cost,
                'fetched_at' => $fetchedAt,
            ]
        );

        return [
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'fetched_at' => $fetchedAt->format('d.m.Y H:i'),
            'skipped' => false,
            'cell' => [
                'key' => $cell['key'],
                'label' => $cell['label'],
                'adults' => $cell['adults'],
                'children' => $cell['children'],
                'price' => $price,
                'error' => $error,
                'cost' => $cost,
            ],
        ];
    }

    /**
     * Отели кантона для пакетного прогона occupancy.
     *
     * @return list<array{id: int, title: string, hotel_identifier: ?string}>
     */
    public function hotelsForOccupancyBatch(string $slug): array
    {
        $region = $this->findRegion($slug);
        if ($region === null) {
            throw new \InvalidArgumentException('Неизвестный регион: '.$slug);
        }

        return SwissHotel::query()
            ->where('region_id', $region->id)
            ->whereNotNull('hotel_identifier')
            ->where('hotel_identifier', '!=', '')
            ->orderBy('id')
            ->get(['id', 'title', 'hotel_identifier'])
            ->map(static fn (SwissHotel $h) => [
                'id' => (int) $h->id,
                'title' => (string) $h->title,
                'hotel_identifier' => (string) $h->hotel_identifier,
            ])
            ->all();
    }

    /**
     * План пакетного прогона: только незалитые ячейки по галочкам «по посетителям».
     *
     * @param  string|null  $slug  null = все кантоны
     * @return array{
     *   keys: list<string>,
     *   key_labels: array<string, string>,
     *   jobs: list<array{region_slug: string, region_label: string, hotel_id: int, title: string, key: string}>,
     *   stats: array{hotels: int, total: int, done: int, pending: int}
     * }
     */
    public function occupancyBatchPlan(?string $slug = null): array
    {
        $keys = $this->selectedOccupancyKeys();
        $keyLabels = [];
        foreach (HotelOccupancyCatalog::all() as $cell) {
            if (in_array($cell['key'], $keys, true)) {
                $keyLabels[$cell['key']] = $cell['label'];
            }
        }

        $regionsQuery = SwissRegion::query()->orderBy('label');
        if ($slug !== null) {
            $regionsQuery->where('slug', $slug);
        }
        $regions = $regionsQuery->get();

        $jobs = [];
        $hotelsCount = 0;
        $done = 0;
        $total = 0;

        foreach ($regions as $region) {
            $hotels = SwissHotel::query()
                ->where('region_id', $region->id)
                ->whereNotNull('hotel_identifier')
                ->where('hotel_identifier', '!=', '')
                ->orderBy('id')
                ->get(['id', 'title', 'hotel_identifier']);

            if ($hotels->isEmpty() || $keys === []) {
                continue;
            }

            $hotelsCount += $hotels->count();
            $total += $hotels->count() * count($keys);

            $filled = SwissHotelOccupancyPrice::query()
                ->where('region_id', $region->id)
                ->whereIn('hotel_identifier', $hotels->pluck('hotel_identifier')->all())
                ->whereIn('occupancy_key', $keys)
                ->whereNotNull('price_usd')
                ->where('price_usd', '>', 0)
                ->get(['hotel_identifier', 'occupancy_key']);

            $filledMap = [];
            foreach ($filled as $row) {
                $filledMap[$row->hotel_identifier.'|'.$row->occupancy_key] = true;
            }

            foreach ($hotels as $hotel) {
                $identifier = (string) $hotel->hotel_identifier;
                foreach ($keys as $key) {
                    if (isset($filledMap[$identifier.'|'.$key])) {
                        $done++;
                        continue;
                    }
                    $jobs[] = [
                        'region_slug' => (string) $region->slug,
                        'region_label' => (string) $region->label,
                        'hotel_id' => (int) $hotel->id,
                        'title' => (string) $hotel->title,
                        'key' => (string) $key,
                    ];
                }
            }
        }

        return [
            'keys' => $keys,
            'key_labels' => $keyLabels,
            'jobs' => $jobs,
            'stats' => [
                'hotels' => $hotelsCount,
                'total' => $total,
                'done' => $done,
                'pending' => count($jobs),
            ],
        ];
    }

    /**
     * @return array{key: string, label: string, adults: int, children: list<int>}|null
     */
    private function resolveOccupancyCell(string $key): ?array
    {
        foreach (HotelOccupancyCatalog::all() as $cell) {
            if ($cell['key'] === $key) {
                return [
                    'key' => $cell['key'],
                    'label' => $cell['label'],
                    'adults' => $cell['adults'],
                    'children' => $cell['children'],
                ];
            }
        }

        return null;
    }

    /**
     * DataForSEO иногда отдаёт один отель дважды — оставляем одну запись (минимальная цена).
     * Ключ как у MySQL unicode_ci: Hôtel ≈ Hotel, иначе падает unique(region_id, title).
     *
     * @param  list<array{title: string, hotel_identifier: ?string, stars: ?int, price: float}>  $items
     * @return list<array{title: string, hotel_identifier: ?string, stars: ?int, price: float}>
     */
    private function dedupeByTitle(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            $key = $this->titleDedupeKey($item['title']);
            if (! isset($unique[$key])) {
                $unique[$key] = $item;
                continue;
            }

            $current = $unique[$key];
            if ($item['price'] < $current['price']) {
                $unique[$key] = $item;
                continue;
            }

            // При равной цене предпочитаем запись с hotel_identifier.
            if (
                $item['price'] === $current['price']
                && empty($current['hotel_identifier'])
                && ! empty($item['hotel_identifier'])
            ) {
                $unique[$key] = $item;
            }
        }

        return array_values($unique);
    }

    private function titleDedupeKey(string $title): string
    {
        $title = mb_strtolower(trim($title));
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($title, \Normalizer::FORM_D);
            if (is_string($decomposed)) {
                $title = preg_replace('/\p{Mn}/u', '', $decomposed) ?? $title;
            }
        }
        // Тире/дефисы и пробелы — как в unicode_ci часто «слипаются» у близких названий.
        $title = preg_replace('/[\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}\-‧·]+/u', '-', $title) ?? $title;
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;

        return $title;
    }
}
