<?php

namespace App\Services;

use App\Models\FoodImport;
use App\Models\SwissRegion;
use App\Support\DataForSeoTitle;
use Illuminate\Support\Facades\DB;

class ZurichRestaurantsService
{
    public const LOCATION_CODE = 20151;

    private const LIMIT = 30;

    /** @var list<string> */
    private const FAST_FOOD_NAME_PARTS = [
        'burger king',
        'kfc',
        'mcdonald',
        'mc donald',
        'subway',
        'taco bell',
        'five guys',
        'starbucks',
        'domino',
        'pizza hut',
    ];

    /** @var list<string> */
    private const EXCLUDED_CATEGORY_PARTS = [
        'fast food',
        'food court',
    ];

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    /**
     * @return list<array{
     *     name: string,
     *     website: string,
     *     rating: ?float,
     *     reviews_count: ?int,
     *     address: string,
     *     price_level: ?int,
     * }>
     */
    public function fetchAndSave(): array
    {
        $region = $this->zurichRegion();
        $items = $this->fetchItems();
        $now = now();

        DB::transaction(function () use ($region, $items, $now): void {
            foreach ($items as $item) {
                $lookup = $item['place_id'] !== ''
                    ? [
                        'region_id' => $region->id,
                        'place_id' => $item['place_id'],
                    ]
                    : [
                        'region_id' => $region->id,
                        'name' => $item['name'],
                        'keyword' => 'restaurant',
                    ];

                $row = FoodImport::query()->firstOrNew($lookup);
                $row->fill([
                    'region_id' => $region->id,
                    'keyword' => 'restaurant',
                    'name' => $item['name'],
                    'website' => $item['website'],
                    'rating' => $item['rating'],
                    'reviews_count' => $item['reviews_count'],
                    'address' => $item['address'] !== '' ? $item['address'] : null,
                    'price_level' => $item['price_level'],
                    'food_type' => null,
                    'place_id' => $item['place_id'] !== '' ? $item['place_id'] : null,
                    'imported_at' => $now,
                ]);
                $row->save();
            }
        });

        return $items;
    }

    /**
     * @return list<array{
     *     name: string,
     *     website: string,
     *     rating: ?float,
     *     reviews_count: ?int,
     *     address: string,
     *     price_level: ?int,
     *     place_id: string
     * }>
     */
    private function fetchItems(): array
    {
        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN или DATAFORSEO_PASSWORD не заданы в .env');
        }

        $response = $this->client->post(DataForSeoClient::GOOGLE_MAPS_URL, [[
            'keyword' => 'restaurant',
            'language_code' => 'en',
            'location_code' => self::LOCATION_CODE,
            'depth' => 100,
            'device' => 'desktop',
            'os' => 'windows',
        ]], 180);

        $rawItems = $response['tasks'][0]['result'][0]['items'] ?? [];
        $mapped = [];

        foreach ($rawItems as $item) {
            if (! is_array($item) || ($item['type'] ?? '') !== 'maps_search') {
                continue;
            }

            $row = $this->mapItem($item);
            if ($row !== null) {
                $mapped[] = $row;
            }
        }

        usort($mapped, static function (array $a, array $b): int {
            $reviewsA = $a['reviews_count'] ?? -1;
            $reviewsB = $b['reviews_count'] ?? -1;
            if ($reviewsB !== $reviewsA) {
                return $reviewsB <=> $reviewsA;
            }

            return ($b['rating'] ?? -1.0) <=> ($a['rating'] ?? -1.0);
        });

        return array_slice($this->dedupeByWebsite($mapped), 0, self::LIMIT);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     name: string,
     *     website: string,
     *     rating: ?float,
     *     reviews_count: ?int,
     *     address: string,
     *     price_level: ?int,
     *     place_id: string
     * }|null
     */
    private function mapItem(array $item): ?array
    {
        $name = DataForSeoTitle::normalize($item['title'] ?? null);
        if ($name === null) {
            return null;
        }

        $website = $this->websiteLabel($item);
        if ($website === '') {
            return null;
        }

        if ($this->isExcludedByName($name) || $this->isExcludedByCategory($item)) {
            return null;
        }

        $rating = $this->parseRating($item);
        $priceLevel = $this->parsePriceLevel($item['price_level'] ?? null);

        return [
            'name' => $name,
            'website' => $website,
            'rating' => $rating['value'],
            'reviews_count' => $rating['reviews'],
            'address' => trim((string) ($item['address'] ?? $item['snippet'] ?? '')),
            'price_level' => $priceLevel,
            'place_id' => trim((string) ($item['place_id'] ?? '')),
        ];
    }

    private function zurichRegion(): SwissRegion
    {
        $region = SwissRegion::query()
            ->where('location_code', self::LOCATION_CODE)
            ->orWhere('slug', 'zurich')
            ->first();

        if ($region === null) {
            throw new \RuntimeException('Регион Zürich не найден в swiss_regions.');
        }

        return $region;
    }

    /**
     * @param  list<array{name: string, website: string, rating: ?float, reviews_count: ?int, address: string, price_level: ?int, place_id: string}>  $items
     * @return list<array{name: string, website: string, rating: ?float, reviews_count: ?int, address: string, price_level: ?int, place_id: string}>
     */
    private function dedupeByWebsite(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            $key = mb_strtolower($item['website']);
            if (! isset($unique[$key])) {
                $unique[$key] = $item;
            }
        }

        return array_values($unique);
    }

    /**
     * @return array{value: ?float, reviews: ?int}
     */
    private function parseRating(array $item): array
    {
        if (! isset($item['rating']) || ! is_array($item['rating'])) {
            return ['value' => null, 'reviews' => null];
        }

        return [
            'value' => isset($item['rating']['value']) ? (float) $item['rating']['value'] : null,
            'reviews' => isset($item['rating']['votes_count']) ? (int) $item['rating']['votes_count'] : null,
        ];
    }

    private function parsePriceLevel(mixed $priceLevel): ?int
    {
        if ($priceLevel === null || $priceLevel === '') {
            return null;
        }

        if (is_numeric($priceLevel)) {
            $level = (int) $priceLevel;

            return $level >= 1 && $level <= 4 ? $level : null;
        }

        if (is_string($priceLevel)) {
            if (preg_match('/[1-4]/', $priceLevel, $match) === 1) {
                return (int) $match[0];
            }

            $dollars = substr_count($priceLevel, '$');
            if ($dollars >= 1 && $dollars <= 4) {
                return $dollars;
            }
        }

        return null;
    }

    private function isExcludedByName(string $name): bool
    {
        $normalized = mb_strtolower($name);

        foreach (self::FAST_FOOD_NAME_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return str_contains($normalized, 'food court');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isExcludedByCategory(array $item): bool
    {
        $categories = [mb_strtolower(trim((string) ($item['category'] ?? '')))];
        $additional = $item['additional_categories'] ?? [];
        if (is_array($additional)) {
            foreach ($additional as $category) {
                $categories[] = mb_strtolower(trim((string) $category));
            }
        }

        $primary = $categories[0] ?? '';
        if (in_array($primary, ['bar', 'pub', 'cocktail bar', 'wine bar', 'night club'], true)) {
            return true;
        }

        $flat = implode(' | ', $categories);
        foreach (self::EXCLUDED_CATEGORY_PARTS as $part) {
            if (str_contains($flat, $part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function websiteLabel(array $item): string
    {
        $url = trim((string) ($item['url'] ?? ''));

        if ($url !== '') {
            return $url;
        }

        $domain = trim((string) ($item['domain'] ?? ''));
        if ($domain === '') {
            return '';
        }

        return str_starts_with($domain, 'http') ? $domain : 'https://'.$domain;
    }
}
