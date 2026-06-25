<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ZurichPlacesRawService
{
    public const GOOGLE_MAPS_URL = 'https://api.dataforseo.com/v3/serp/google/maps/live/advanced';

    /** Zurich,Switzerland (в SERP/Maps API; 1025955 = Copperhill, US). */
    public const LOCATION_CODE = 20151;

    private const PER_PAGE = 100;

    private const API_DEPTH = 100;

    /** @var list<array<string, mixed>>|null */
    private ?array $entertainmentCache = null;

    /** @var list<array<string, mixed>>|null */
    private ?array $foodCache = null;

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    public function paginateEntertainment(Request $request): LengthAwarePaginator
    {
        return $this->paginateCollection(
            $this->entertainmentItems(),
            max(1, (int) $request->query('entertainment_page', 1)),
            $request->url(),
            $request->query(),
            'entertainment_page',
        );
    }

    public function paginateFood(Request $request): LengthAwarePaginator
    {
        return $this->paginateCollection(
            $this->foodItems(),
            max(1, (int) $request->query('food_page', 1)),
            $request->url(),
            $request->query(),
            'food_page',
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entertainmentItems(): array
    {
        if ($this->entertainmentCache === null) {
            $this->entertainmentCache = $this->fetchMapsSearchItems('museums attractions');
        }

        return $this->entertainmentCache;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function foodItems(): array
    {
        if ($this->foodCache === null) {
            $this->foodCache = $this->fetchMapsSearchItems('restaurants');
        }

        return $this->foodCache;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $query
     */
    private function paginateCollection(
        array $items,
        int $page,
        string $path,
        array $query,
        string $pageName,
    ): LengthAwarePaginator {
        $offset = ($page - 1) * self::PER_PAGE;

        return new LengthAwarePaginator(
            array_slice($items, $offset, self::PER_PAGE),
            count($items),
            self::PER_PAGE,
            $page,
            [
                'path' => $path,
                'pageName' => $pageName,
                'query' => $query,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchMapsSearchItems(string $keyword): array
    {
        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN или DATAFORSEO_PASSWORD не заданы в .env');
        }

        $response = $this->client->post(self::GOOGLE_MAPS_URL, [[
            'keyword' => $keyword,
            'language_code' => 'en',
            'location_code' => self::LOCATION_CODE,
            'depth' => self::API_DEPTH,
            'device' => 'desktop',
            'os' => 'windows',
        ]]);

        $rawItems = $response['tasks'][0]['result'][0]['items'] ?? [];

        return Collection::make($rawItems)
            ->filter(fn ($item) => is_array($item) && ($item['type'] ?? '') === 'maps_search')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function entertainmentCategory(array $item): string
    {
        $category = trim((string) ($item['category'] ?? ''));
        $additional = $item['additional_categories'] ?? [];

        if (is_array($additional) && $additional !== []) {
            $extra = implode(', ', array_map('strval', $additional));

            return $category !== '' ? $category.' | '.$extra : $extra;
        }

        return $category !== '' ? $category : '—';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function rawRating(array $item): string
    {
        if (! isset($item['rating'])) {
            return '—';
        }

        $encoded = json_encode($item['rating'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '—';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function rawPriceLevel(array $item): string
    {
        if (! array_key_exists('price_level', $item) || $item['price_level'] === null) {
            return '—';
        }

        return is_scalar($item['price_level'])
            ? (string) $item['price_level']
            : json_encode($item['price_level'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function rawPriceDescriptionExtras(array $item): string
    {
        $parts = [];

        foreach ($item as $key => $value) {
            if (! is_string($key) || ! preg_match('/price|snippet|description/i', $key)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $parts[] = $key.': '.($value === null ? 'null' : (string) $value);
            }
        }

        return $parts === [] ? '—' : implode(' | ', $parts);
    }
}
