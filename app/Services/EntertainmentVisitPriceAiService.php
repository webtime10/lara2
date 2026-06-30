<?php

namespace App\Services;

use App\Models\BudgetPromt;
use App\Models\EntertainmentVisitPrice;
use App\Models\SwissEntertainment;
use App\Models\SwissRegion;
use App\Support\EntertainmentCategory;
use RuntimeException;

class EntertainmentVisitPriceAiService
{
    public const PROMPT_NAME = 'entertainment_visit_price_prompt';

    /** @var array<string, float> */
    private const FALLBACK_ADULT_USD = [
        EntertainmentCategory::MUSEUM => 20.0,
        EntertainmentCategory::CINEMA => 18.0,
        EntertainmentCategory::ZOO => 32.0,
        EntertainmentCategory::AQUARIUM => 35.0,
        EntertainmentCategory::AMUSEMENT_PARK => 45.0,
        EntertainmentCategory::THEME_PARK => 55.0,
        EntertainmentCategory::WATER_PARK => 42.0,
        EntertainmentCategory::ESCAPE_ROOM => 38.0,
        EntertainmentCategory::BOAT_TOUR => 35.0,
        EntertainmentCategory::SKI_RESORT => 55.0,
    ];

    public function __construct(
        private GeminiService $gemini,
        private GeminiProService $geminiPro,
        private OpenAiService $openAi,
    ) {}

    /**
     * @return array{prices: array<string, EntertainmentVisitPrice>, model: string, places_count: int}
     */
    public function refreshRegion(SwissRegion $region, ?string $model = null): array
    {
        $model = $this->normalizeModel($model);
        $places = $this->placesForRegion($region);

        if ($places === []) {
            throw new RuntimeException('Нет собранных развлечений для расчёта: '.$region->label);
        }

        $request = $this->pricesFromModelChain(
            $model,
            $this->sourceText($region, $places),
            $this->instruction()
        );
        $model = $request['model'];
        $prices = $request['prices'];
        $counts = collect($places)->countBy('category');

        $saved = [];
        foreach ($prices as $category => $row) {
            if (! in_array($category, EntertainmentCategory::ALL, true)) {
                continue;
            }

            $placesCount = (int) ($counts->get($category) ?? 0);
            $price = $this->normalizedPriceRow($category, $row, $placesCount);
            if ($price === null) {
                continue;
            }

            $saved[$category] = $this->savePrice($region, $category, $price, $model, $placesCount);
        }

        foreach ($counts as $category => $placesCount) {
            $category = (string) $category;
            $placesCount = (int) $placesCount;
            if ($placesCount <= 0 || isset($saved[$category])) {
                continue;
            }

            $price = $this->normalizedPriceRow($category, ['adult_avg_price' => null, 'child_avg_price' => null], $placesCount);
            if ($price === null) {
                continue;
            }

            $saved[$category] = $this->savePrice($region, $category, $price, $model, $placesCount);
        }

        if ($saved === []) {
            throw new RuntimeException($this->modelLabel($model).' не вернул цены развлечений.');
        }

        return [
            'prices' => $saved,
            'model' => $model,
            'places_count' => count($places),
        ];
    }

    /** @return array{model: string, prices: array<string, array{adult_avg_price: ?float, child_avg_price: ?float}>} */
    private function pricesFromModelChain(string $selectedModel, string $material, string $instruction): array
    {
        $lastError = null;

        foreach ($this->fallbackChain($selectedModel) as $model) {
            try {
                $answer = $this->askModel($model, $material, $instruction);
            } catch (\Throwable $e) {
                $lastError = $this->modelLabel($model).': '.$e->getMessage();
                continue;
            }

            if ($answer === null || trim($answer) === '') {
                $lastError = $this->modelLabel($model).' не вернул ответ.';
                continue;
            }

            try {
                $prices = $this->parseJsonPrices($answer);
                if ($prices !== []) {
                    return ['model' => $model, 'prices' => $prices];
                }
                $lastError = $this->modelLabel($model).' вернул пустой список цен.';
            } catch (\Throwable $e) {
                $lastError = $this->modelLabel($model).': '.$e->getMessage();
            }
        }

        throw new RuntimeException($lastError ?: 'Все AI-модели не смогли вернуть цены развлечений.');
    }

    /** @return list<string> */
    private function fallbackChain(string $selectedModel): array
    {
        return array_values(array_unique([
            $selectedModel,
            FoodSourceGeminiPriceService::MODEL_GEMINI_PAID,
            FoodSourceGeminiPriceService::MODEL_OPENAI,
        ]));
    }

    /** @return array<string, string> */
    public static function modelLabels(): array
    {
        return FoodSourceGeminiPriceService::modelLabels();
    }

    public static function defaultModel(): string
    {
        return FoodSourceGeminiPriceService::MODEL_GEMINI_FREE;
    }

    private function normalizeModel(?string $model): string
    {
        $model = trim((string) $model);
        $allowed = array_flip(array_keys(self::modelLabels()));

        return isset($allowed[$model]) ? $model : self::defaultModel();
    }

    private function modelLabel(string $model): string
    {
        return self::modelLabels()[$model] ?? $model;
    }

    private function askModel(string $model, string $material, string $instruction): ?string
    {
        return match ($model) {
            FoodSourceGeminiPriceService::MODEL_GEMINI_PAID => $this->geminiPro->chat(
                $material,
                $instruction,
                max(60, (int) config('services.gemini_pro.chat_timeout', 1800)),
                [
                    'temperature' => 0.2,
                    'maxOutputTokens' => max(1024, (int) config('services.gemini_pro.max_output_tokens', 65536)),
                ],
            ),
            FoodSourceGeminiPriceService::MODEL_OPENAI => $this->openAi->askOpenAiWithModel(
                $instruction,
                $material,
                trim((string) config('services.openai.model', 'gpt-4o-mini')) ?: 'gpt-4o-mini',
                'EntertainmentVisitPrice:openai',
            ),
            default => $this->gemini->chat(
                $material,
                $instruction,
                max(60, (int) config('services.gemini.chat_timeout', 900)),
                ['temperature' => 0.2],
            ),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function placesForRegion(SwissRegion $region): array
    {
        return SwissEntertainment::query()
            ->where('region_id', $region->id)
            ->whereIn('category', EntertainmentCategory::ALL)
            ->orderByDesc('rating')
            ->orderByDesc('reviews')
            ->limit(80)
            ->get()
            ->map(fn (SwissEntertainment $item): array => array_filter([
                'title' => $item->title,
                'category' => $item->category,
                'rating' => $item->rating !== null ? (float) $item->rating : null,
                'reviews' => $item->reviews,
                'address' => $item->address,
                'website' => $item->website,
            ], fn ($value): bool => $value !== null && $value !== ''))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $places
     */
    private function sourceText(SwissRegion $region, array $places): string
    {
        return json_encode([
            'country' => 'Switzerland',
            'canton' => $region->label,
            'region_slug' => $region->slug,
            'currency' => 'USD',
            'categories' => EntertainmentCategory::ALL,
            'places' => $places,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
    }

    private function instruction(): string
    {
        $prompt = trim((string) BudgetPromt::query()
            ->where('name', self::PROMPT_NAME)
            ->value('content'));

        if ($prompt !== '') {
            return $prompt;
        }

        throw new RuntimeException(
            'Промт для цен развлечений не задан. Заполните его в админке: Промты WP → Budget → Цена одного визита по категории.'
        );
    }

    /** @return array<string, array{adult_avg_price: ?float, child_avg_price: ?float}> */
    private function parseJsonPrices(string $answer): array
    {
        $json = trim($answer);

        if (str_starts_with($json, '```')) {
            $json = preg_replace('/^```(?:json)?\s*/i', '', $json) ?? $json;
            $json = preg_replace('/\s*```$/', '', $json) ?? $json;
            $json = trim($json);
        }

        $data = json_decode($json, true);
        if (! is_array($data)) {
            throw new RuntimeException('Не удалось разобрать JSON от выбранной модели.');
        }

        $rows = isset($data['prices']) && is_array($data['prices']) ? $data['prices'] : $data;
        $prices = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $category = trim((string) ($row['category'] ?? ''));
            if (! in_array($category, EntertainmentCategory::ALL, true)) {
                continue;
            }

            $prices[$category] = [
                'adult_avg_price' => $this->numericOrNull($row['adult_avg_price'] ?? null),
                'child_avg_price' => $this->numericOrNull($row['child_avg_price'] ?? null),
            ];
        }

        return array_filter($prices, fn (array $row): bool => $row['adult_avg_price'] !== null);
    }

    /**
     * @param  array{adult_avg_price: ?float, child_avg_price: ?float}  $row
     * @return array{adult_avg_price: float, child_avg_price: float}|null
     */
    private function normalizedPriceRow(string $category, array $row, int $placesCount): ?array
    {
        if ($placesCount <= 0) {
            return null;
        }

        $adult = $row['adult_avg_price'] ?? null;
        if ($adult === null || $adult <= 0.0) {
            $adult = self::FALLBACK_ADULT_USD[$category] ?? null;
        }

        if ($adult === null || $adult <= 0.0) {
            return null;
        }

        $child = $row['child_avg_price'] ?? null;
        if ($child === null || $child <= 0.0) {
            $child = round($adult * 0.6, 2);
        }

        return [
            'adult_avg_price' => round((float) $adult, 2),
            'child_avg_price' => round((float) $child, 2),
        ];
    }

    /**
     * @param  array{adult_avg_price: float, child_avg_price: float}  $price
     */
    private function savePrice(
        SwissRegion $region,
        string $category,
        array $price,
        string $model,
        int $placesCount,
    ): EntertainmentVisitPrice {
        return EntertainmentVisitPrice::query()->updateOrCreate(
            [
                'region_id' => $region->id,
                'category' => $category,
            ],
            [
                'adult_avg_price' => $price['adult_avg_price'],
                'child_avg_price' => $price['child_avg_price'],
                'currency' => 'USD',
                'ai_model' => $model,
                'places_count' => $placesCount,
                'last_checked' => now(),
            ],
        );
    }

    private function numericOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $value = round((float) $value, 2);

        return $value >= 0.0 ? $value : null;
    }
}
