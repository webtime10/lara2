<?php

namespace App\Services;

use App\Support\ApartmentPriceLevel;
use App\Support\DataForSeoTitle;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ZurichAccommodationService
{
    public const LOCATION_CODE = 20151;

    private const PER_PAGE = 40;

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    public function paginateApartments(Request $request): LengthAwarePaginator
    {
        $items = $this->fetchApartmentItems();
        $page = max(1, (int) $request->query('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        return new LengthAwarePaginator(
            array_slice($items, $offset, self::PER_PAGE),
            count($items),
            self::PER_PAGE,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    /**
     * @return list<array{name: string, level: int, price_usd: float}>
     */
    private function fetchApartmentItems(): array
    {
        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN или DATAFORSEO_PASSWORD не заданы в .env');
        }

        $response = $this->client->post(DataForSeoClient::HOTEL_SEARCHES_URL, [[
            'location_code' => self::LOCATION_CODE,
            'keyword' => 'vacation rentals',
            'search_param' => 'hba=1',
            'currency' => 'USD',
            'language_code' => 'en',
            'depth' => 140,
        ]]);

        $rawItems = $response['tasks'][0]['result'][0]['items'] ?? [];

        $items = Collection::make($rawItems)
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => $this->mapItem($item))
            ->filter()
            ->values()
            ->all();

        $items = $this->dedupeByTitle($items);

        $mapped = [];
        foreach (ApartmentPriceLevel::assign($items, 'price_usd') as $item) {
            $mapped[] = [
                'name' => $item['name'],
                'level' => (int) $item['level'],
                'price_usd' => (float) $item['price_usd'],
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{name: string, price_usd: float}|null
     */
    private function mapItem(array $item): ?array
    {
        $name = DataForSeoTitle::normalize($item['title'] ?? null);
        if ($name === null) {
            return null;
        }

        $prices = is_array($item['prices'] ?? null) ? $item['prices'] : [];
        $price = isset($prices['price']) ? (float) $prices['price'] : null;

        if ($price === null || $price <= 0.0) {
            return null;
        }

        return [
            'name' => $name,
            'price_usd' => $price,
        ];
    }

    /**
     * @param  list<array{name: string, price_usd: float}>  $items
     * @return list<array{name: string, price_usd: float}>
     */
    private function dedupeByTitle(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            $key = mb_strtolower($item['name']);
            if (! isset($unique[$key]) || $item['price_usd'] < $unique[$key]['price_usd']) {
                $unique[$key] = $item;
            }
        }

        return array_values($unique);
    }
}
