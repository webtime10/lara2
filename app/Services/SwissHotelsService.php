<?php

namespace App\Services;

use App\Models\SwissHotel;
use App\Models\SwissHotelSyncState;
use App\Models\SwissRegion;
use App\Support\ApartmentPriceLevel;
use App\Support\DataForSeoTitle;
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
     * @return list<array{title: string, stars: ?int, price: float}>
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
     * @return array{title: string, stars: ?int, price: float}|null
     */
    private function mapItem(array $item): ?array
    {
        $title = DataForSeoTitle::normalize($item['title'] ?? null);
        $stars = $item['stars'] ?? null;
        $prices = is_array($item['prices'] ?? null) ? $item['prices'] : [];
        $price = $prices['price'] ?? null;

        if ($title === null) {
            return null;
        }
        if ($price === null || (float) $price <= 0.0) {
            return null;
        }

        $starsInt = ($stars !== null && (int) $stars > 0) ? (int) $stars : null;

        return [
            'title' => $title,
            'stars' => $starsInt,
            'price' => (float) $price,
        ];
    }

    /**
     * DataForSEO иногда отдаёт один отель дважды — оставляем одну запись (минимальная цена).
     *
     * @param  list<array{title: string, stars: ?int, price: float}>  $items
     * @return list<array{title: string, stars: ?int, price: float}>
     */
    private function dedupeByTitle(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            $key = mb_strtolower($item['title']);
            if (! isset($unique[$key]) || $item['price'] < $unique[$key]['price']) {
                $unique[$key] = $item;
            }
        }

        return array_values($unique);
    }
}
