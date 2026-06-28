<?php

namespace App\Services;

use App\Models\CarRentalPrice;
use App\Models\SwissRegion;
use RuntimeException;

class CarRentalPriceAiService
{
    public function __construct(
        private GeminiService $gemini,
        private GeminiProService $geminiPro,
        private OpenAiService $openAi,
    ) {}

    /**
     * @return array{prices: array<string, CarRentalPrice>, model: string}
     */
    public function refreshRegion(SwissRegion $region, ?string $model = null): array
    {
        $model = $this->normalizeModel($model);
        $request = $this->pricesFromModelChain($model, $this->sourceText($region), $this->instruction());
        $model = $request['model'];
        $prices = $request['prices'];

        $saved = [];
        foreach (CarRentalPrice::classes() as $class) {
            $dailyPrice = $prices[$class] ?? null;
            if ($dailyPrice === null) {
                continue;
            }

            $saved[$class] = CarRentalPrice::query()->updateOrCreate(
                [
                    'region_id' => $region->id,
                    'car_class' => $class,
                ],
                [
                    'daily_price' => $dailyPrice,
                    'currency' => 'USD',
                    'ai_model' => $model,
                    'last_checked' => now(),
                ],
            );
        }

        if ($saved === []) {
            throw new RuntimeException($this->modelLabel($model).' вернул пустые цены.');
        }

        return ['prices' => $saved, 'model' => $model];
    }

    /** @return array{model: string, prices: array<string, float|null>} */
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
                $lastError = $this->modelLabel($model).' вернул пустые дневные цены.';
            } catch (\Throwable $e) {
                $lastError = $this->modelLabel($model).': '.$e->getMessage();
            }
        }

        throw new RuntimeException($lastError ?: 'Все AI-модели не смогли вернуть цены авто.');
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
                'CarRentalPrice:openai',
            ),
            default => $this->gemini->chat(
                $material,
                $instruction,
                max(60, (int) config('services.gemini.chat_timeout', 900)),
                ['temperature' => 0.2],
            ),
        };
    }

    private function sourceText(SwissRegion $region): string
    {
        return json_encode([
            'country' => 'Switzerland',
            'canton' => $region->label,
            'region_slug' => $region->slug,
            'currency' => 'USD',
            'requested_prices' => [
                'economy_daily_price',
                'medium_daily_price',
                'luxury_daily_price',
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
    }

    private function instruction(): string
    {
        return <<<'TXT'
You estimate average daily car rental prices for Swiss travel budget calculations.

Use the canton/region as local context. Return realistic average daily rental prices in USD.
Prices are for one rental day, without fuel, tolls, parking, deposit, or insurance upgrades.

Car classes:
- economy_daily_price: small economy car
- medium_daily_price: standard/mid-size car
- luxury_daily_price: luxury/premium car

Return ONLY valid JSON object. No markdown. No explanations.

Required format:
{
  "economy_daily_price": 65,
  "medium_daily_price": 95,
  "luxury_daily_price": 180
}
TXT;
    }

    /** @return array<string, float|null> */
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

        return array_filter([
            CarRentalPrice::CLASS_ECONOMY => $this->numericOrNull($data['economy_daily_price'] ?? null),
            CarRentalPrice::CLASS_MEDIUM => $this->numericOrNull($data['medium_daily_price'] ?? null),
            CarRentalPrice::CLASS_LUXURY => $this->numericOrNull($data['luxury_daily_price'] ?? null),
        ], fn (?float $value): bool => $value !== null);
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
