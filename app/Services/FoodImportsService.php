<?php

namespace App\Services;

use App\Models\FoodImport;
use App\Models\SwissRegion;
use App\Support\DataForSeoTitle;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FoodImportsService
{
    /** @var list<string> */
    public const CAFE_KEYWORDS = [
        'cafe',
        'coffee shop',
        'bakery',
    ];

    /** @var list<string> */
    public const RESTAURANT_KEYWORDS = [
        'restaurant',
        'pizza restaurant',
        'swiss restaurant',
        'vegetarian restaurant',
        'seafood restaurant',
        'steak house',
        'brunch restaurant',
        'family restaurant',
    ];

    /** @var list<string> */
    public const KEYWORDS = [
        ...self::RESTAURANT_KEYWORDS,
        ...self::CAFE_KEYWORDS,
    ];

    private const API_DEPTH = 100;

    /** @var list<string> */
    private const PREMIUM_NAME_PARTS = [
        'fine dining',
        'gourmet',
        'michelin',
        'luxury',
        'signature',
        'chef',
    ];

    /** @var list<string> */
    private const PREMIUM_DOMAIN_PARTS = [
        'michelin',
        'gaultmillau',
        'fine-dining',
        'finedining',
        'gourmet',
        'chef',
    ];

    /** @var list<string> */
    private const RESTAURANT_NAME_PARTS = [
        'restaurant',
        'ristorante',
        'pizzeria',
        'grill',
        'steakhouse',
        'brasserie',
        'sushi',
        'thai restaurant',
        'china restaurant',
        'indian restaurant',
        'lotusblume',
    ];

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    /** @return Collection<int, SwissRegion> */
    public function regions(): Collection
    {
        return SwissRegion::query()->withCount(['foodImports', 'foodSamples'])->orderBy('label')->get();
    }

    public function findRegion(string $slug): ?SwissRegion
    {
        return SwissRegion::query()->where('slug', $slug)->first();
    }

    public function paginateRegionImports(string $slug, Request $request): LengthAwarePaginator
    {
        $region = $this->findRegion($slug);
        if ($region === null) {
            throw new \InvalidArgumentException('Неизвестный регион: '.$slug);
        }

        return FoodImport::query()
            ->where('region_id', $region->id)
            ->when($request->filled('keyword'), fn ($query) => $query->where('keyword', $request->input('keyword')))
            ->orderByDesc('reviews_count')
            ->orderByDesc('rating')
            ->paginate(100)
            ->withQueryString();
    }

    /**
     * @return array{region: string, saved: int}
     */
    public function importRegion(string $slug): array
    {
        $region = $this->findRegion($slug);
        if ($region === null) {
            throw new \InvalidArgumentException('Неизвестный регион: '.$slug);
        }

        $items = $this->fetchRegionItems($region);
        $now = now();

        DB::transaction(function () use ($region, $items, $now): void {
            FoodImport::query()->where('region_id', $region->id)->delete();

            foreach ($items as $item) {
                $classification = $this->classificationFromItem($item);

                FoodImport::query()->create([
                    'region_id' => $region->id,
                    'keyword' => $item['keyword'],
                    'name' => $item['name'],
                    'website' => $item['website'] !== '' ? $item['website'] : null,
                    'rating' => $item['rating'],
                    'reviews_count' => $item['reviews_count'],
                    'address' => $item['address'] !== '' ? $item['address'] : null,
                    'price_level' => $item['price_level'],
                    'food_type' => $classification['food_type'],
                    'gpt_processed' => $classification['gpt_processed'],
                    'place_id' => $item['place_id'] !== '' ? $item['place_id'] : null,
                    'imported_at' => $now,
                ]);
            }
        });

        return ['region' => $region->label, 'saved' => count($items)];
    }

    /**
     * @return list<array{slug: string, label: string, saved: int}>
     */
    public function importAllRegions(): array
    {
        $result = [];

        foreach (SwissRegion::query()->orderBy('label')->get() as $region) {
            $imported = $this->importRegion($region->slug);
            $result[] = [
                'slug' => $region->slug,
                'label' => $imported['region'],
                'saved' => $imported['saved'],
            ];
        }

        return $result;
    }

    /**
     * @return list<array{
     *     keyword: string,
     *     name: string,
     *     website: string,
     *     rating: ?float,
     *     reviews_count: ?int,
     *     address: string,
     *     price_level: ?int,
     *     place_id: string
     * }>
     */
    public function fetchRegionItems(SwissRegion $region): array
    {
        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN или DATAFORSEO_PASSWORD не заданы в .env');
        }

        set_time_limit(300);
        $login = $this->client->login();
        $password = $this->client->password();

        $responses = Http::pool(function ($pool) use ($login, $password, $region) {
            foreach (self::KEYWORDS as $index => $keyword) {
                $pool->as((string) $index)
                    ->withBasicAuth($login, $password)
                    ->timeout(180)
                    ->acceptJson()
                    ->post(DataForSeoClient::GOOGLE_MAPS_URL, [[
                        'keyword' => $keyword,
                        'language_code' => 'en',
                        'location_code' => $region->location_code,
                        'depth' => self::API_DEPTH,
                        'device' => 'desktop',
                        'os' => 'windows',
                    ]]);
            }
        });

        $items = [];

        foreach ($responses as $index => $response) {
            if ($response->failed()) {
                continue;
            }

            $data = $response->json() ?? [];
            $taskStatus = $data['tasks'][0]['status_code'] ?? null;
            if ($taskStatus !== null && (int) $taskStatus !== 20000) {
                continue;
            }

            $keyword = self::KEYWORDS[(int) $index] ?? self::KEYWORDS[0];
            $rawItems = $data['tasks'][0]['result'][0]['items'] ?? [];

            foreach ($rawItems as $item) {
                if (! is_array($item) || ($item['type'] ?? '') !== 'maps_search') {
                    continue;
                }

                $mapped = $this->mapItem($item, $keyword);
                if ($mapped !== null) {
                    $items[] = $mapped;
                }
            }
        }

        return $this->dedupeByRegionSource($items);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     keyword: string,
     *     name: string,
     *     website: string,
     *     rating: ?float,
     *     reviews_count: ?int,
     *     address: string,
     *     price_level: ?int,
     *     place_id: string
     * }|null
     */
    private function mapItem(array $item, string $keyword): ?array
    {
        $name = DataForSeoTitle::normalize($item['title'] ?? null);
        if ($name === null) {
            return null;
        }

        $website = $this->websiteLabel($item);
        if ($website === '') {
            return null;
        }

        $rating = $this->parseRating($item);

        return [
            'keyword' => $keyword,
            'name' => $name,
            'website' => $website,
            'rating' => $rating['value'],
            'reviews_count' => $rating['reviews'],
            'address' => trim((string) ($item['address'] ?? $item['snippet'] ?? '')),
            'price_level' => $this->parsePriceLevel($item['price_level'] ?? null),
            'place_id' => trim((string) ($item['place_id'] ?? '')),
        ];
    }

    /**
     * @param  list<array{keyword: string, name: string, website: string, rating: ?float, reviews_count: ?int, address: string, price_level: ?int, place_id: string}>  $items
     * @return list<array{keyword: string, name: string, website: string, rating: ?float, reviews_count: ?int, address: string, price_level: ?int, place_id: string}>
     */
    private function dedupeByRegionSource(array $items): array
    {
        $unique = [];

        foreach ($items as $item) {
            $key = $item['place_id'] !== ''
                ? 'place:'.$item['place_id']
                : 'name_keyword:'.mb_strtolower($item['name']).'|'.$item['keyword'];

            if (! isset($unique[$key])) {
                $unique[$key] = $item;
                continue;
            }

            if (($item['reviews_count'] ?? 0) > ($unique[$key]['reviews_count'] ?? 0)) {
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
            $dollars = substr_count($priceLevel, '$');
            if ($dollars >= 1 && $dollars <= 4) {
                return $dollars;
            }
        }

        return null;
    }

    /**
     * @param  array{keyword: string, name: string, website: string, rating: ?float, reviews_count: ?int}  $item
     * @return array{food_type: string|null, gpt_processed: bool}
     */
    private function classificationFromItem(array $item): array
    {
        if (in_array($item['keyword'], self::CAFE_KEYWORDS, true)) {
            if ($this->hasRestaurantName($item['name'])) {
                return ['food_type' => 'restaurant', 'gpt_processed' => true];
            }

            return ['food_type' => 'cafe', 'gpt_processed' => true];
        }

        if (in_array($item['keyword'], self::RESTAURANT_KEYWORDS, true)) {
            if ($this->looksPremium($item)) {
                return ['food_type' => 'restaurant_candidate', 'gpt_processed' => false];
            }

            return ['food_type' => 'restaurant', 'gpt_processed' => true];
        }

        return ['food_type' => null, 'gpt_processed' => true];
    }

    private function hasRestaurantName(string $name): bool
    {
        $name = mb_strtolower($name);

        foreach (self::RESTAURANT_NAME_PARTS as $part) {
            if (str_contains($name, $part)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{name: string, website: string, rating: ?float, reviews_count: ?int}  $item
     */
    private function looksPremium(array $item): bool
    {
        if (($item['reviews_count'] ?? 0) > 1000 && ($item['rating'] ?? 0.0) >= 4.5) {
            return true;
        }

        $name = mb_strtolower($item['name']);
        foreach (self::PREMIUM_NAME_PARTS as $part) {
            if (str_contains($name, $part)) {
                return true;
            }
        }

        $website = mb_strtolower($item['website']);
        foreach (self::PREMIUM_DOMAIN_PARTS as $part) {
            if (str_contains($website, $part)) {
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
