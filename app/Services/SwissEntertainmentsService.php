<?php

namespace App\Services;

use App\Models\SwissEntertainment;
use App\Models\SwissEntertainmentSyncState;
use App\Models\SwissRegion;
use App\Support\DataForSeoTitle;
use App\Support\EntertainmentBrand;
use App\Support\EntertainmentCategory;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SwissEntertainmentsService
{
    private const PER_PAGE = 100;

    private const MIN_RATING = 4.0;

    private const MIN_POPULAR_REVIEWS = 50;

    /** @var array<string, int> */
    private const CATEGORY_LIMITS = [
        EntertainmentCategory::ZOO => 2,
        EntertainmentCategory::MUSEUM => 2,
        EntertainmentCategory::CINEMA => 2,
        EntertainmentCategory::ESCAPE_ROOM => 2,
        EntertainmentCategory::BOAT_TOUR => 2,
        EntertainmentCategory::AMUSEMENT_PARK => 2,
        EntertainmentCategory::SKI_RESORT => 2,
        EntertainmentCategory::WATER_PARK => 1,
        EntertainmentCategory::THEME_PARK => 1,
        EntertainmentCategory::AQUARIUM => 1,
    ];

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

    /** @var list<string> */
    private const FOREIGN_ADDRESS_PARTS = [
        'germany',
        'deutschland',
        'austria',
        'österreich',
        'liechtenstein',
        'france',
        'italy',
        'italia',
    ];

    /** @var array<string, list<string>> */
    private const CANTON_CITY_PARTS = [
        'aargau' => [
            'aarau', 'baden', 'brugg', 'bremgarten', 'lenzburg', 'birrwil', 'seengen', 'wettingen',
            'wohlen', 'zofingen', 'rheinfelden', 'frick', 'muri', 'spreitenbach', 'suhr', 'reinach',
            'sarmenstorf', 'lupfig', 'büttikon', 'buettikon', 'hausen', 'fislisbach', 'habsburg',
            'seon',
        ],
        'appenzell-ausserrhoden' => ['herisau', 'teufen', 'heiden', 'trogen', 'gais', 'speicher'],
        'appenzell-innerrhoden' => ['appenzell', 'schwende', 'rüte', 'oberegg'],
        'basel-landschaft' => ['liestal', 'pratteln', 'muttenz', 'reinach', 'allschwil', 'sissach', 'birsfelden'],
        'basel-stadt' => ['basel', 'riehen', 'bettingen'],
        'bern' => [
            'bern', 'biel', 'bienne', 'thun', 'interlaken', 'langenthal', 'burgdorf', 'spiez',
            'grindelwald', 'gstaad', 'adelboden', 'wengen',
        ],
        'fribourg' => ['fribourg', 'freiburg', 'bulle', 'murten', 'morat', 'estavayer', 'romont'],
        'geneva' => ['genève', 'geneva', 'carouge', 'meyrin', 'vernier', 'lancy', 'nyon'],
        'glarus' => ['glarus', 'näfels', 'netstal', 'schwanden', 'linthal'],
        'graubunden' => ['chur', 'davos', 'st. moritz', 'stmoritz', 'arosa', 'flims', 'laax', 'lenzerheide', 'scuol'],
        'jura' => ['delémont', 'delemont', 'porrentruy', 'saignelégier', 'saignelegier'],
        'lucerne' => ['luzern', 'lucerne', 'sursee', 'sempach', 'hochdorf', 'willisau', 'emmen', 'kriens'],
        'neuchatel' => ['neuchâtel', 'neuchatel', 'la chaux de fonds', 'le locle', 'boudry'],
        'nidwalden' => ['stans', 'buochs', 'hergiswil', 'beckenried', 'ennetbürgen'],
        'obwalden' => ['sarnen', 'engelberg', 'giswil', 'sachseln', 'alpnach'],
        'schaffhausen' => ['schaffhausen', 'stein am rhein', 'neuhausen', 'thayngen'],
        'schwyz' => ['schwyz', 'einsiedeln', 'brunnen', 'wollerau', 'pfäffikon', 'kuessnacht', 'küssnacht'],
        'solothurn' => ['solothurn', 'olten', 'grenchen', 'dornach', 'balsthal', 'zuchwil', 'schönenwerd', 'schoenenwerd'],
        'st-gallen' => ['st. gallen', 'st gallen', 'rapperswil', 'wil', 'buchs', 'sargans', 'bad ragaz', 'uzwil'],
        'thurgau' => ['frauenfeld', 'kreuzlingen', 'weinfelden', 'arbon', 'romanshorn', 'amriswil'],
        'ticino' => ['lugano', 'bellinzona', 'locarno', 'ascona', 'mendrisio', 'chiasso'],
        'uri' => ['altdorf', 'andermatt', 'flüelen', 'fluelen', 'erstfeld'],
        'valais' => ['sion', 'sierre', 'martigny', 'brig', 'zermatt', 'verbier', 'crans montana', 'visp', 'saas fee'],
        'vaud' => ['lausanne', 'montreux', 'vevey', 'morges', 'yverdon', 'rolle', 'gland', 'aigle', 'pully'],
        'zug' => ['zug', 'baar', 'cham', 'rotkreuz', 'unterägeri', 'unteraegeri', 'oberägeri', 'oberaegeri'],
        'zurich' => [
            'zürich', 'zurich', 'winterthur', 'uster', 'dübendorf', 'duebendorf', 'dietikon', 'kloten',
            'schlieren', 'wädenswil', 'waedenswil', 'horgen', 'meilen', 'bülach', 'buelach', 'mettmenstetten',
        ],
    ];

    /** @var array<string, list<array{int, int}>> */
    private const POSTCODE_RANGES = [
        'aargau' => [[5000, 5749]],
        'appenzell-ausserrhoden' => [[9000, 9107], [9400, 9499]],
        'appenzell-innerrhoden' => [[9050, 9057]],
        'basel-landschaft' => [[4100, 4499]],
        'basel-stadt' => [[4000, 4099]],
        'bern' => [[3000, 3999]],
        'fribourg' => [[1600, 1799], [3200, 3299]],
        'geneva' => [[1200, 1299]],
        'glarus' => [[8750, 8799]],
        'graubunden' => [[7000, 7499], [7500, 7799]],
        'jura' => [[2800, 2999]],
        'lucerne' => [[6000, 6299]],
        'neuchatel' => [[2000, 2499]],
        'nidwalden' => [[6360, 6399]],
        'obwalden' => [[6050, 6079], [6390, 6390]],
        'schaffhausen' => [[8200, 8299]],
        'schwyz' => [[6400, 6459], [8800, 8849]],
        'solothurn' => [[4500, 4659], [4700, 4719]],
        'st-gallen' => [[9000, 9659], [8870, 8899], [7300, 7319]],
        'thurgau' => [[8500, 8599], [9200, 9329]],
        'ticino' => [[6500, 6999]],
        'uri' => [[6460, 6499]],
        'valais' => [[1900, 1999], [3900, 3999]],
        'vaud' => [[1000, 1199], [1300, 1499], [1800, 1899]],
        'zug' => [[6300, 6349]],
        'zurich' => [[8000, 8499], [8600, 8999]],
    ];

    public function __construct(
        private DataForSeoClient $client,
    ) {}

    /** @return Collection<int, SwissRegion> */
    public function regions(): Collection
    {
        return SwissRegion::query()->withCount('entertainments')->orderBy('label')->get();
    }

    public function findRegion(string $slug): ?SwissRegion
    {
        return SwissRegion::query()->where('slug', $slug)->first();
    }

    public function apiHint(SwissRegion $region): string
    {
        return 'location_code='.$region->location_code.' ('.$region->label.', Switzerland), google/maps, 10 keywords';
    }

    public function syncFromApi(string $slug): int
    {
        $region = $this->findRegion($slug);
        if ($region === null) {
            throw new \InvalidArgumentException('Неизвестный регион: '.$slug);
        }

        $items = $this->topByCategory(
            $this->dedupeByIdentity(
                $this->filterByRegion($this->fetchEntertainmentsFromApi($region), $region)
            )
        );
        $now = now();

        DB::transaction(function () use ($region, $items, $now): void {
            SwissEntertainment::query()->where('region_id', $region->id)->delete();

            if ($items !== []) {
                $rows = [];
                foreach ($items as $item) {
                    $rows[] = [
                        'region_id' => $region->id,
                        'title' => $item['title'],
                        'category' => $item['category'],
                        'website' => $item['website'] !== '' ? $item['website'] : null,
                        'rating' => $item['rating'],
                        'reviews' => $item['reviews'],
                        'address' => $item['address'] !== '' ? $item['address'] : null,
                        'place_id' => $item['place_id'] !== '' ? $item['place_id'] : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                SwissEntertainment::query()->insert($rows);
            }

            $region->update(['entertainments_synced_at' => $now]);
        });

        return count($items);
    }

    public function paginateFromDb(string $slug, Request $request): LengthAwarePaginator
    {
        $region = $this->findRegion($slug);
        if ($region === null) {
            throw new \InvalidArgumentException('Неизвестный регион: '.$slug);
        }

        return SwissEntertainment::query()
            ->where('region_id', $region->id)
            ->orderByDesc('rating')
            ->orderByDesc('reviews')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }

    public function lastFullSyncAt(): ?Carbon
    {
        return SwissEntertainmentSyncState::current()->last_full_sync_at;
    }

    public function markFullSyncComplete(): Carbon
    {
        $state = SwissEntertainmentSyncState::current();
        $now = now();
        $state->update(['last_full_sync_at' => $now]);

        return $now;
    }

    /**
     * @param  Collection<int, SwissEntertainment>  $items
     * @return array{
     *     total_objects: int,
     *     total_brands: int,
     *     categories: array<string, array{objects: int, brands: int}>
     * }
     */
    public function regionSummary(Collection $items): array
    {
        return EntertainmentBrand::regionSummary(
            $items->map(fn (SwissEntertainment $item): array => [
                'name' => $item->title,
                'category' => $item->category,
            ])->values()->all()
        );
    }

    /**
     * @return array{
     *     canton: string,
     *     country: string,
     *     attractions: array<string, list<array{name: string, website: ?string, rating: ?float, reviews: ?int}>>
     * }
     */
    public function structuredForRegion(SwissRegion $region): array
    {
        $items = $region->entertainments()
            ->orderByDesc('rating')
            ->orderByDesc('reviews')
            ->get();

        $result = [
            'canton' => $region->label,
            'country' => 'Switzerland',
            'attractions' => [
                'zoo' => [],
                'museum' => [],
                'cinema' => [],
                'escape_room' => [],
                'boat_tour' => [],
                'water_park' => [],
                'theme_park' => [],
                'amusement_park' => [],
                'aquarium' => [],
                'ski_resort' => [],
            ],
        ];

        foreach ($items as $item) {
            $key = $this->categoryKey((string) $item->category);
            if ($key === null) {
                continue;
            }

            $result['attractions'][$key][] = [
                'name' => $item->title,
                'website' => $item->website,
                'rating' => $item->rating !== null ? (float) $item->rating : null,
                'reviews' => $item->reviews,
            ];
        }

        return $result;
    }

    /**
     * @return list<array{
     *     title: string,
     *     category: string,
     *     website: string,
     *     rating: ?float,
     *     reviews: ?int,
     *     address: string,
     *     place_id: string
     * }>
     */
    private function fetchEntertainmentsFromApi(SwissRegion $region): array
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
                if (! is_array($item) || ($item['type'] ?? '') !== 'maps_search') {
                    continue;
                }

                $mapped = $this->mapItem($item);
                if ($mapped !== null) {
                    $items[] = $mapped;
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

    /**
     * @param  array<string, mixed>  $item
     * @return array{
     *     title: string,
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
        $title = DataForSeoTitle::normalize($item['title'] ?? null);
        if ($title === null) {
            return null;
        }

        $item['title'] = $title;

        if (! EntertainmentCategory::shouldIncludeItem($item)) {
            return null;
        }

        $category = EntertainmentCategory::resolveFromItem($item);
        if ($category === null) {
            return null;
        }

        $parsedRating = EntertainmentCategory::parseRating($item);

        return [
            'title' => $title,
            'category' => $category,
            'website' => $this->websiteLabel($item),
            'rating' => $parsedRating['value'],
            'reviews' => $parsedRating['reviews'],
            'address' => trim((string) ($item['address'] ?? $item['snippet'] ?? '')),
            'place_id' => trim((string) ($item['place_id'] ?? '')),
        ];
    }

    /**
     * @param  list<array{title: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}>  $items
     * @return list<array{title: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}>
     */
    private function dedupeByIdentity(array $items): array
    {
        $selected = [];
        $seenTitles = [];
        $seenWebsites = [];
        $seenPlaceIds = [];

        $items = collect($items)
            ->sort(fn (array $a, array $b): int => $this->compareEntertainment($a, $b))
            ->values()
            ->all();

        foreach ($items as $item) {
            $titleKey = $this->identityKey($item['title']);
            $websiteKey = $this->websiteKey($item['website']);
            $placeKey = $this->identityKey($item['place_id']);

            if (
                ($titleKey !== null && isset($seenTitles[$titleKey]))
                || ($websiteKey !== null && isset($seenWebsites[$websiteKey]))
                || ($placeKey !== null && isset($seenPlaceIds[$placeKey]))
            ) {
                continue;
            }

            $selected[] = $item;
            if ($titleKey !== null) {
                $seenTitles[$titleKey] = true;
            }
            if ($websiteKey !== null) {
                $seenWebsites[$websiteKey] = true;
            }
            if ($placeKey !== null) {
                $seenPlaceIds[$placeKey] = true;
            }
        }

        return $selected;
    }

    /**
     * @param  list<array{title: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}>  $items
     * @return list<array{title: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}>
     */
    private function filterByRegion(array $items, SwissRegion $region): array
    {
        return array_values(array_filter(
            $items,
            fn (array $item): bool => $this->belongsToSwissRegion($item, $region)
        ));
    }

    /**
     * @param  list<array{title: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}>  $items
     * @return list<array{title: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}>
     */
    private function topByCategory(array $items): array
    {
        return collect($items)
            ->groupBy('category')
            ->flatMap(function (Collection $categoryItems, string $category): Collection {
                $limit = self::CATEGORY_LIMITS[$category] ?? 0;
                if ($limit <= 0) {
                    return collect();
                }

                $ranked = $categoryItems
                    ->filter(fn (array $item): bool => (float) ($item['rating'] ?? 0.0) >= self::MIN_RATING)
                    ->sort(fn (array $a, array $b): int => $this->compareEntertainment($a, $b))
                    ->values();

                $popular = $ranked
                    ->filter(fn (array $item): bool => (int) ($item['reviews'] ?? 0) >= self::MIN_POPULAR_REVIEWS)
                    ->values();

                return ($popular->isNotEmpty() ? $popular : $ranked)
                    ->take($limit)
                    ->values();
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{title: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}  $a
     * @param  array{title: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}  $b
     */
    private function compareEntertainment(array $a, array $b): int
    {
        $ratingCompare = ((float) ($b['rating'] ?? 0.0)) <=> ((float) ($a['rating'] ?? 0.0));
        if ($ratingCompare !== 0) {
            return $ratingCompare;
        }

        return ((int) ($b['reviews'] ?? 0)) <=> ((int) ($a['reviews'] ?? 0));
    }

    /**
     * @param  array{title: string, category: string, website: string, rating: ?float, reviews: ?int, address: string, place_id: string}  $item
     */
    private function belongsToSwissRegion(array $item, SwissRegion $region): bool
    {
        $address = mb_strtolower((string) $item['address']);
        if ($address === '') {
            return false;
        }

        foreach (self::FOREIGN_ADDRESS_PARTS as $part) {
            if (str_contains($address, $part)) {
                return false;
            }
        }

        $city = $this->cityFromAddress($address);
        if ($city !== null) {
            $cityRegion = $this->regionSlugByCity($city);
            if ($cityRegion !== null) {
                return $cityRegion === $region->slug;
            }
        }

        $postcode = $this->postcodeFromAddress($address);
        if ($postcode !== null) {
            if ($this->postcodeBelongsToRegion($postcode, $region->slug)) {
                return true;
            }

            if ($this->postcodeBelongsToAnyRegion($postcode)) {
                return false;
            }
        }

        return str_contains($address, mb_strtolower($region->label));
    }

    private function cityFromAddress(string $address): ?string
    {
        if (preg_match('/\b[1-9]\d{3}\s+([^,]+)/u', $address, $matches) !== 1) {
            return null;
        }

        $city = trim($matches[1]);

        return $city !== '' ? $city : null;
    }

    private function postcodeFromAddress(string $address): ?int
    {
        if (preg_match('/\b([1-9]\d{3})\b/u', $address, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function regionSlugByCity(string $city): ?string
    {
        $city = mb_strtolower($city);

        foreach (self::CANTON_CITY_PARTS as $slug => $parts) {
            foreach ($parts as $part) {
                if (str_contains($city, $part)) {
                    return $slug;
                }
            }
        }

        return null;
    }

    private function postcodeBelongsToRegion(int $postcode, string $slug): bool
    {
        foreach (self::POSTCODE_RANGES[$slug] ?? [] as [$from, $to]) {
            if ($postcode >= $from && $postcode <= $to) {
                return true;
            }
        }

        return false;
    }

    private function postcodeBelongsToAnyRegion(int $postcode): bool
    {
        foreach (self::POSTCODE_RANGES as $ranges) {
            foreach ($ranges as [$from, $to]) {
                if ($postcode >= $from && $postcode <= $to) {
                    return true;
                }
            }
        }

        return false;
    }

    private function identityKey(string $value): ?string
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $value !== '' ? $value : null;
    }

    private function websiteKey(string $website): ?string
    {
        $website = trim(mb_strtolower($website));
        if ($website === '') {
            return null;
        }

        $host = parse_url(str_starts_with($website, 'http') ? $website : 'https://'.$website, PHP_URL_HOST);
        $host = is_string($host) ? preg_replace('/^www\./', '', $host) : null;

        return $host !== null && $host !== '' ? $host : $this->identityKey($website);
    }

    private function categoryKey(string $category): ?string
    {
        return match ($category) {
            EntertainmentCategory::ZOO => 'zoo',
            EntertainmentCategory::MUSEUM => 'museum',
            EntertainmentCategory::CINEMA => 'cinema',
            EntertainmentCategory::ESCAPE_ROOM => 'escape_room',
            EntertainmentCategory::BOAT_TOUR => 'boat_tour',
            EntertainmentCategory::WATER_PARK => 'water_park',
            EntertainmentCategory::THEME_PARK => 'theme_park',
            EntertainmentCategory::AMUSEMENT_PARK => 'amusement_park',
            EntertainmentCategory::AQUARIUM => 'aquarium',
            EntertainmentCategory::SKI_RESORT => 'ski_resort',
            default => null,
        };
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
