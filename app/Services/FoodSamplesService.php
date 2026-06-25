<?php

namespace App\Services;

use App\Models\FoodImport;
use App\Models\FoodSample;
use App\Models\SwissRegion;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class FoodSamplesService
{
    /** @var array<string, int> */
    private const LIMITS = [
        'cafe' => 10,
        'restaurant' => 10,
        'restaurant_candidate' => 5,
    ];

    /** @var list<string> */
    private const CAFE_KEYWORDS = [
        'cafe',
        'coffee shop',
        'bakery',
    ];

    /** @var list<string> */
    private const MASS_BRAND_PARTS = [
        'burger king',
        'mcdonald',
        'subway',
        'kfc',
        'starbucks',
        'hans im glück',
        'hans im glueck',
        'five guys',
        'domino',
        'pizza hut',
        'vapiano',
        'tibits',
        'vicafe',
        'vi cafe',
        'spiga',
        'holy cow',
        'rolli',
    ];

    /** @var list<string> */
    private const HIGH_CONFIDENCE_PARTS = [
        'michelin',
        'gourmet',
        'fine dining',
        'fine-dining',
        'chef table',
        'chef\'s table',
        'chefs table',
    ];

    /** @var list<string> */
    private const MEDIUM_CONFIDENCE_PARTS = [
        'restaurant hotel',
        'hotel restaurant',
        'grand hotel',
        'palace',
        'bellevue',
        'kronenhalle',
        'brasserie',
        'auberge',
        'château',
        'chateau',
        'schloss',
        'historisch',
        'historic',
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

    /** @var list<string> */
    private const CAFE_NAME_PARTS = [
        'cafe',
        'café',
        'coffee',
        'bakery',
        'bäckerei',
        'backerei',
        'konditorei',
        'tea house',
        'espresso bar',
    ];

    /** @var list<string> */
    private const NON_FOOD_BUSINESS_PARTS = [
        'sportzentrum',
        'shopping center',
        'museum',
        'factory',
        'visitor center',
        'cheese factory',
        'schaukäserei',
        'schaukaeserei',
        'käserei',
        'kaeserei',
        'retail store',
        'showroom',
        'tourist attraction',
        'amusement center',
        'entertainment center',
    ];

    /** @var list<string> */
    private const FOREIGN_ADDRESS_PARTS = [
        'germany',
        'deutschland',
        'france',
        'italy',
        'italia',
        'austria',
        'österreich',
        'liechtenstein',
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

    /**
     * @return array{total: int, cafe: int, restaurant: int, restaurant_candidate: int}
     */
    public function buildForRegion(SwissRegion $region): array
    {
        $now = now();
        $summary = ['total' => 0, 'cafe' => 0, 'restaurant' => 0, 'restaurant_candidate' => 0];

        DB::transaction(function () use ($region, $now, &$summary): void {
            FoodSample::query()->where('region_id', $region->id)->delete();

            $pools = $this->samplePools($region);
            $usedImportIds = [];
            $usedBrands = [];

            foreach (self::LIMITS as $foodType => $limit) {
                $items = $this->selectUnique($pools[$foodType], $limit, $usedImportIds, $usedBrands);

                $rank = 1;
                foreach ($items as $row) {
                    /** @var FoodImport $item */
                    $item = $row['item'];
                    $sampleFoodType = $row['food_type'];

                    FoodSample::query()->create([
                        'region_id' => $region->id,
                        'food_import_id' => $item->id,
                        'food_type' => $sampleFoodType,
                        'gpt_processed' => $sampleFoodType !== 'restaurant_candidate',
                        'classification_confidence' => $row['confidence'],
                        'name' => $item->name,
                        'website' => $item->website,
                        'rating' => $item->rating,
                        'reviews_count' => $item->reviews_count,
                        'address' => $item->address,
                        'price_level' => $this->priceLevelLabel($item->price_level, $sampleFoodType, $row['confidence']),
                        'place_id' => $item->place_id,
                        'sample_rank' => $rank++,
                        'selected_at' => $now,
                    ]);
                }

                $summary[$foodType] = $items->count();
                $summary['total'] += $items->count();
            }
        });

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function samplePools(SwissRegion $region): array
    {
        $items = FoodImport::query()
            ->where('region_id', $region->id)
            ->orderByDesc('reviews_count')
            ->orderByDesc('rating')
            ->get()
            ->filter(fn (FoodImport $item): bool => $this->belongsToSwissRegion($item, $region));

        $cafes = [];
        $restaurants = [];
        $candidates = [];

        foreach ($items as $item) {
            if ($this->isNonFoodBusiness($item)) {
                continue;
            }

            if ($item->food_type === 'cafe') {
                if ($this->hasRestaurantName($item->name) || ! $this->hasCafeSignal($item)) {
                    $restaurants[] = $this->sampleRow($item, 'restaurant');
                    continue;
                }

                $cafes[] = $this->sampleRow($item, 'cafe');
                continue;
            }

            if ($item->food_type === 'restaurant') {
                $restaurants[] = $this->sampleRow($item, 'restaurant');
                continue;
            }

            if ($item->food_type === 'restaurant_candidate') {
                $confidence = $this->candidateConfidence($item);
                if ($confidence === null) {
                    $restaurants[] = $this->sampleRow($item, 'restaurant');
                    continue;
                }

                $candidates[] = $this->sampleRow($item, 'restaurant_candidate', $confidence);
            }
        }

        return [
            'cafe' => $cafes,
            'restaurant' => $restaurants,
            'restaurant_candidate' => $candidates,
        ];
    }

    /**
     * @return array{item: FoodImport, food_type: string, confidence: ?string}
     */
    private function sampleRow(FoodImport $item, string $foodType, ?string $confidence = null): array
    {
        return [
            'item' => $item,
            'food_type' => $foodType,
            'confidence' => $confidence,
        ];
    }

    private function candidateConfidence(FoodImport $item): ?string
    {
        $text = mb_strtolower($item->name.' '.$item->website.' '.$item->address);

        foreach (self::MASS_BRAND_PARTS as $part) {
            if (str_contains($text, $part)) {
                return null;
            }
        }

        foreach (self::HIGH_CONFIDENCE_PARTS as $part) {
            if (str_contains($text, $part)) {
                return 'high';
            }
        }

        foreach (self::MEDIUM_CONFIDENCE_PARTS as $part) {
            if (str_contains($text, $part)) {
                return 'medium';
            }
        }

        if (($item->price_level ?? 0) >= 4 && ($item->rating ?? 0.0) >= 4.5) {
            return 'low';
        }

        return null;
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

    private function isNonFoodBusiness(FoodImport $item): bool
    {
        $text = mb_strtolower($item->name.' '.$item->address.' '.$item->website);

        foreach (self::NON_FOOD_BUSINESS_PARTS as $part) {
            if (str_contains($text, $part)) {
                return true;
            }
        }

        return false;
    }

    private function hasCafeSignal(FoodImport $item): bool
    {
        if (in_array((string) $item->keyword, self::CAFE_KEYWORDS, true)) {
            return true;
        }

        $name = mb_strtolower($item->name);
        foreach (self::CAFE_NAME_PARTS as $part) {
            if (str_contains($name, $part)) {
                return true;
            }
        }

        return false;
    }

    private function priceLevelLabel(?int $priceLevel, string $foodType, ?string $confidence): ?string
    {
        $label = match ($priceLevel) {
            1 => 'budget',
            2 => 'mid_range',
            3 => 'premium',
            4 => 'luxury',
            default => null,
        };

        if ($label !== null) {
            return $label;
        }

        return match ($foodType) {
            'cafe' => 'budget',
            'restaurant' => 'mid_range',
            'restaurant_candidate' => $confidence === 'high' ? 'luxury' : 'premium',
            default => null,
        };
    }

    private function selectUnique(array $rows, int $limit, array &$usedImportIds, array &$usedBrands)
    {
        $selected = [];

        foreach ($rows as $row) {
            /** @var FoodImport $item */
            $item = $row['item'];
            if (isset($usedImportIds[$item->id])) {
                continue;
            }

            $brandKey = $this->brandKey($item);
            if ($brandKey !== null && isset($usedBrands[$brandKey])) {
                continue;
            }

            $selected[] = $row;
            $usedImportIds[$item->id] = true;
            if ($brandKey !== null) {
                $usedBrands[$brandKey] = true;
            }

            if (count($selected) >= $limit) {
                break;
            }
        }

        return collect($selected);
    }

    private function belongsToSwissRegion(FoodImport $item, SwissRegion $region): bool
    {
        $address = mb_strtolower((string) $item->address);
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

    private function brandKey(FoodImport $item): ?string
    {
        $domain = $this->domainBrandKey((string) $item->website);
        if ($domain !== null) {
            return 'domain:'.$domain;
        }

        $name = mb_strtolower((string) $item->name);
        $name = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $name) ?? $name;
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if ($name === '') {
            return null;
        }

        $words = array_values(array_filter(explode(' ', $name)));
        if (count($words) === 1) {
            return 'name:'.$words[0];
        }

        return 'name:'.implode(' ', array_slice($words, 0, 2));
    }

    private function domainBrandKey(string $website): ?string
    {
        $host = parse_url($website, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return null;
        }

        $host = mb_strtolower($host);
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $parts = array_values(array_filter(explode('.', $host)));

        if (count($parts) < 2) {
            return $host;
        }

        $secondLevel = $parts[count($parts) - 2];
        if (in_array($secondLevel, ['co', 'com', 'net', 'org'], true) && count($parts) >= 3) {
            return $parts[count($parts) - 3].'.'.$secondLevel.'.'.$parts[count($parts) - 1];
        }

        return $secondLevel.'.'.$parts[count($parts) - 1];
    }

    public function paginateRegionSamples(SwissRegion $region, Request $request): LengthAwarePaginator
    {
        return FoodSample::query()
            ->where('region_id', $region->id)
            ->when($request->filled('food_type'), fn ($query) => $query->where('food_type', $request->input('food_type')))
            ->orderByRaw("FIELD(food_type, 'cafe', 'restaurant', 'restaurant_candidate', 'fine_restaurant')")
            ->orderBy('sample_rank')
            ->paginate(100)
            ->withQueryString();
    }

    /** @return array<string, int> */
    public static function limits(): array
    {
        return self::LIMITS;
    }
}
