<?php

namespace App\Services;

use App\Models\FoodImport;
use App\Models\FoodSample;
use App\Models\FoodVisitPrice;
use App\Models\SwissRegion;
use RuntimeException;

class FoodVisitPriceAiService
{
    public function __construct(
        private GeminiService $gemini,
        private GeminiProService $geminiPro,
        private OpenAiService $openAi,
    ) {}

    /**
     * @return array{price: FoodVisitPrice, model: string, places_count: int}
     */
    public function refreshRegion(SwissRegion $region, string $foodType, ?string $model = null): array
    {
        $foodType = $this->normalizeFoodType($foodType);
        $model = $this->normalizeModel($model);
        $places = $this->placesForRegion($region, $foodType);

        if ($places === []) {
            throw new RuntimeException('Нет собранных мест для расчёта: '.$region->label);
        }

        $request = $this->pricesFromModelChain(
            $model,
            $this->sourceText($region, $foodType, $places),
            $this->instruction($foodType)
        );
        $model = $request['model'];
        $prices = $request['prices'];

        $adult = $prices['adult_avg_price'];
        $child = $prices['child_avg_price'] ?? ($adult !== null ? round($adult * 0.6, 2) : null);

        $price = FoodVisitPrice::query()->updateOrCreate(
            [
                'region_id' => $region->id,
                'food_type' => $foodType,
            ],
            [
                'adult_avg_price' => $adult,
                'child_avg_price' => $child,
                'currency' => 'USD',
                'ai_model' => $model,
                'places_count' => count($places),
                'last_checked' => now(),
            ],
        );

        return [
            'price' => $price,
            'model' => $model,
            'places_count' => count($places),
        ];
    }

    /** @return array{model: string, prices: array{adult_avg_price: float|null, child_avg_price: float|null}} */
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
                if ($prices['adult_avg_price'] !== null || $prices['child_avg_price'] !== null) {
                    return ['model' => $model, 'prices' => $prices];
                }
                $lastError = $this->modelLabel($model).' не вернул adult_avg_price / child_avg_price.';
            } catch (\Throwable $e) {
                $lastError = $this->modelLabel($model).': '.$e->getMessage();
            }
        }

        throw new RuntimeException($lastError ?: 'Все AI-модели не смогли вернуть средний чек.');
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

    private function normalizeFoodType(string $foodType): string
    {
        return match ($foodType) {
            FoodVisitPrice::TYPE_CAFE => FoodVisitPrice::TYPE_CAFE,
            FoodVisitPrice::TYPE_RESTAURANT => FoodVisitPrice::TYPE_RESTAURANT,
            default => throw new RuntimeException('Неизвестный тип питания: '.$foodType),
        };
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
                'FoodVisitPrice:openai',
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
    private function placesForRegion(SwissRegion $region, string $foodType): array
    {
        $samples = FoodSample::query()
            ->where('region_id', $region->id)
            ->where('food_type', $foodType)
            ->orderBy('sample_rank')
            ->orderByDesc('reviews_count')
            ->orderByDesc('rating')
            ->limit(30)
            ->get();

        if ($samples->isNotEmpty()) {
            return $samples->map(fn (FoodSample $item): array => $this->placeRow($item))->values()->all();
        }

        return FoodImport::query()
            ->where('region_id', $region->id)
            ->where('food_type', $foodType)
            ->orderByDesc('reviews_count')
            ->orderByDesc('rating')
            ->limit(30)
            ->get()
            ->map(fn (FoodImport $item): array => $this->placeRow($item))
            ->values()
            ->all();
    }

    private function placeRow(FoodImport|FoodSample $item): array
    {
        return array_filter([
            'name' => $item->name,
            'type' => $item->food_type,
            'price_level' => $item->price_level,
            'rating' => $item->rating !== null ? (float) $item->rating : null,
            'reviews_count' => $item->reviews_count,
            'address' => $item->address,
            'website' => $item->website,
        ], fn ($value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  list<array<string, mixed>>  $places
     */
    private function sourceText(SwissRegion $region, string $foodType, array $places): string
    {
        return json_encode([
            'country' => 'Switzerland',
            'canton' => $region->label,
            'region_slug' => $region->slug,
            'food_type' => $foodType,
            'currency' => 'USD',
            'places' => $places,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
    }

    private function instruction(string $foodType): string
    {
        $label = FoodVisitPrice::typeLabels()[$foodType] ?? $foodType;

        return <<<TXT
You estimate average visit prices for Swiss travel budget calculations.

Food place type: {$label}

Use the provided places as local evidence. Consider price_level, rating, reviews_count, and canton.
Return ONLY valid JSON object. No markdown. No explanations.
Currency must be USD.

Required keys:
- adult_avg_price: average price for one adult visit
- child_avg_price: average price for one child visit

For cafe: estimate one simple cafe visit per person.
For restaurant: estimate one normal restaurant visit per person, not luxury fine dining.
If child menu data is not explicit, use about 60% of adult price.
TXT;
    }

    /** @return array{adult_avg_price: float|null, child_avg_price: float|null} */
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

        return [
            'adult_avg_price' => $this->numericOrNull($data['adult_avg_price'] ?? null),
            'child_avg_price' => $this->numericOrNull($data['child_avg_price'] ?? null),
        ];
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
