<?php

namespace App\Services;

use App\Models\SwissApartment;
use App\Models\SwissApartmentSyncState;
use App\Models\SwissRegion;
use App\Support\ApartmentPriceLevel;
use App\Support\DataForSeoTitle;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SwissApartmentsService
{
    private const PER_PAGE = 100;

    public function __construct(
        private DataForSeoClient $client,
    ) {}

    /** @return Collection<int, SwissRegion> */
    public function regions(): Collection
    {
        return SwissRegion::query()->withCount('apartments')->orderBy('label')->get();
    }

    public function findRegion(string $slug): ?SwissRegion
    {
        return SwissRegion::query()->where('slug', $slug)->first();
    }

    public function apiHint(SwissRegion $region): string
    {
        return 'location_code='.$region->location_code.' ('.$region->label.', Switzerland), keyword=vacation rentals, search_param=hba=1';
    }

    public function syncFromApi(string $slug): int
    {
        $region = $this->findRegion($slug);
        if ($region === null) {
            throw new \InvalidArgumentException('Неизвестный регион: '.$slug);
        }

        $items = ApartmentPriceLevel::assign($this->dedupeByTitle($this->fetchApartmentsFromApi($region)), 'price');
        $now = now();

        DB::transaction(function () use ($region, $items, $now): void {
            SwissApartment::query()->where('region_id', $region->id)->delete();

            if ($items !== []) {
                $rows = [];
                foreach ($items as $item) {
                    $rows[] = [
                        'region_id' => $region->id,
                        'title' => $item['title'],
                        'level' => (int) $item['level'],
                        'rating' => null,
                        'price_usd' => $item['price'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                SwissApartment::query()->insert($rows);
            }

            $region->update(['apartments_synced_at' => $now]);
        });

        return count($items);
    }

    public function paginateFromDb(string $slug, Request $request): LengthAwarePaginator
    {
        $region = $this->findRegion($slug);
        if ($region === null) {
            throw new \InvalidArgumentException('Неизвестный регион: '.$slug);
        }

        return SwissApartment::query()
            ->where('region_id', $region->id)
            ->orderBy('price_usd')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function lastFullSyncAt(): ?Carbon
    {
        return SwissApartmentSyncState::current()->last_full_sync_at;
    }

    public function markFullSyncComplete(): Carbon
    {
        $state = SwissApartmentSyncState::current();
        $now = now();
        $state->update(['last_full_sync_at' => $now]);

        return $now;
    }

    /**
     * @return list<array{title: string, price: float}>
     */
    private function fetchApartmentsFromApi(SwissRegion $region): array
    {
        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN или DATAFORSEO_PASSWORD не заданы в .env');
        }

        $response = $this->client->post(DataForSeoClient::HOTEL_SEARCHES_URL, [[
            'location_code' => $region->location_code,
            'keyword' => 'vacation rentals',
            'search_param' => 'hba=1',
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
     * @return array{title: string, price: float}|null
     */
    private function mapItem(array $item): ?array
    {
        $title = DataForSeoTitle::normalize($item['title'] ?? null);
        if ($title === null) {
            return null;
        }

        $prices = is_array($item['prices'] ?? null) ? $item['prices'] : [];
        $price = isset($prices['price']) ? (float) $prices['price'] : null;

        if ($price === null || $price <= 0.0) {
            return null;
        }

        return [
            'title' => $title,
            'price' => $price,
        ];
    }

    /**
     * @param  list<array{title: string, price: float}>  $items
     * @return list<array{title: string, price: float}>
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
