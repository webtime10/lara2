<?php

namespace App\Services;

use App\Support\DataForSeoTitle;
use App\Support\EntertainmentBrand;
use App\Support\EntertainmentCategory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ZurichEntertainmentService
{
    public const LOCATION_CODE = 20151;

    private const CACHE_KEY = 'zurich_entertainment_items_v14';

    private const CACHE_TTL_MINUTES = 60;

    private const PER_PAGE = 100;

    /**
     * @var list<array{
     *     name: string,
     *     category: string,
     *     website: string,
     *     rating: ?float,
     *     reviews: ?int,
     *     address: string,
     *     place_id: string
     * }>|null
     */
    private ?array $itemsCache = null;

    /** @var list<string> */
    private const KEYWORDS = [
        'museums attractions',
        'things to do',
        'zoo aquarium',
        'theater cinema',
        'amusement park',
        'escape room',
        'boat tour',
        'water park',
        'ski resort',
        'kids activities',
    ];

    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    public function paginate(Request $request): LengthAwarePaginator
    {
        $items = $this->fetchAllItems();
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
     * @return list<array{
     *     name: string,
     *     category: string,
     *     website: string,
     *     rating: ?float,
     *     reviews: ?int,
     *     address: string,
     *     place_id: string
     * }>
     */
    public function fetchAllItems(): array
    {
        if ($this->itemsCache !== null) {
            return $this->itemsCache;
        }

        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $this->itemsCache = $cached;
        }

        if (! $this->client->credentialsConfigured()) {
            throw new \RuntimeException('DATAFORSEO_LOGIN или DATAFORSEO_PASSWORD не заданы в .env');
        }

        $rawItems = $this->fetchFromAllKeywords();
        $unique = [];

        foreach ($rawItems as $item) {
            $title = DataForSeoTitle::normalize($item['title'] ?? null);
            if ($title === null) {
                continue;
            }

            $key = mb_strtolower($title);
            $item['title'] = $title;

            if (! isset($unique[$key])) {
                $unique[$key] = $item;
                continue;
            }

            $unique[$key] = $this->mergeItems($unique[$key], $item);
        }

        $items = array_values($unique);

        $mapped = [];
        foreach ($items as $item) {
            $row = $this->mapItem($item);
            if ($row !== null) {
                $mapped[] = $row;
            }
        }

        usort($mapped, static function (array $a, array $b): int {
            $reviewsA = $a['reviews'] ?? -1;
            $reviewsB = $b['reviews'] ?? -1;
            if ($reviewsB !== $reviewsA) {
                return $reviewsB <=> $reviewsA;
            }

            $ratingA = $a['rating'] ?? -1.0;
            $ratingB = $b['rating'] ?? -1.0;

            return $ratingB <=> $ratingA;
        });

        Cache::put(self::CACHE_KEY, $mapped, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $this->itemsCache = $mapped;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     name: string,
     *     category: string,
     *     website: string,
     *     rating: ?float,
     *     reviews: ?int,
     *     address: string,
     *     place_id: string
     * }|null
     */
    private function mapItem(array $item): ?array
    {
        if (! EntertainmentCategory::shouldIncludeItem($item)) {
            return null;
        }

        $category = EntertainmentCategory::resolveFromItem($item);
        if ($category === null) {
            return null;
        }

        $parsedRating = EntertainmentCategory::parseRating($item);

        return [
            'name' => (string) $item['title'],
            'category' => $category,
            'website' => $this->websiteLabel($item),
            'rating' => $parsedRating['value'],
            'reviews' => $parsedRating['reviews'],
            'address' => trim((string) ($item['address'] ?? $item['snippet'] ?? '')),
            'place_id' => trim((string) ($item['place_id'] ?? '')),
        ];
    }

    /**
     * @param  list<array{name: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}>  $items
     * @return array{total: int}&array<string, int>
     */
    public function categorySummary(array $items): array
    {
        $summary = ['total' => count($items)];

        foreach (EntertainmentCategory::ALL as $category) {
            $summary[$category] = 0;
        }

        foreach ($items as $item) {
            $category = $item['category'] ?? '';
            if (array_key_exists($category, $summary)) {
                $summary[$category]++;
            }
        }

        return $summary;
    }

    /**
     * @param  list<array{name: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}>  $items
     * @return array{
     *     total_objects: int,
     *     total_brands: int,
     *     categories: array<string, array{objects: int, brands: int}>
     * }
     */
    public function regionSummary(array $items): array
    {
        return EntertainmentBrand::regionSummary($items);
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     * @return array<string, mixed>
     */
    private function mergeItems(array $a, array $b): array
    {
        if (empty($a['category']) && ! empty($b['category'])) {
            $a['category'] = $b['category'];
        }

        if (empty($a['rating']) && ! empty($b['rating'])) {
            $a['rating'] = $b['rating'];
        }

        if (empty($a['address']) && ! empty($b['address'])) {
            $a['address'] = $b['address'];
        }

        if (empty($a['url']) && ! empty($b['url'])) {
            $a['url'] = $b['url'];
        }

        if (empty($a['domain']) && ! empty($b['domain'])) {
            $a['domain'] = $b['domain'];
        }

        if (empty($a['place_id']) && ! empty($b['place_id'])) {
            $a['place_id'] = $b['place_id'];
        }

        return $a;
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

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchFromAllKeywords(): array
    {
        set_time_limit(300);
        $login = $this->client->login();
        $password = $this->client->password();

        $responses = Http::pool(function ($pool) use ($login, $password) {
            foreach (self::KEYWORDS as $index => $keyword) {
                $pool->as((string) $index)
                    ->withBasicAuth($login, $password)
                    ->timeout(180)
                    ->acceptJson()
                    ->post(DataForSeoClient::GOOGLE_MAPS_URL, [[
                        'keyword' => $keyword,
                        'language_code' => 'en',
                        'location_code' => self::LOCATION_CODE,
                        'depth' => 100,
                        'device' => 'desktop',
                        'os' => 'windows',
                    ]]);
            }
        });

        $items = [];

        foreach ($responses as $response) {
            if ($response->failed()) {
                continue;
            }

            $data = $response->json() ?? [];
            $taskStatus = $data['tasks'][0]['status_code'] ?? null;

            if ($taskStatus !== null && (int) $taskStatus !== 20000) {
                continue;
            }

            $rawItems = $data['tasks'][0]['result'][0]['items'] ?? [];

            foreach ($rawItems as $item) {
                if (is_array($item) && ($item['type'] ?? '') === 'maps_search') {
                    $items[] = $item;
                }
            }
        }

        if ($items === [] && count($responses) > 0) {
            $first = $responses[array_key_first($responses)];
            if ($first->failed()) {
                throw new \RuntimeException('DataForSEO HTTP '.$first->status().': '.$first->body());
            }
        }

        return $items;
    }
}
