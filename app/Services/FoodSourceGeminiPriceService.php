<?php

namespace App\Services;

use App\Models\BudgetPromt;
use App\Models\FoodSource;
use App\Models\SwissRegion;
use RuntimeException;

class FoodSourceGeminiPriceService
{
	private const PROMPT_NAME = 'korzina_magazina';

    public const MODEL_GEMINI_FREE = 'gemini_free';

    public const MODEL_GEMINI_PAID = 'gemini_paid';

    public const MODEL_OPENAI = 'openai';

    public function __construct(
        private GeminiService $gemini,
        private GeminiProService $geminiPro,
        private OpenAiService $openAi,
    ) {}

    /** @return array<string, string> */
    public static function modelLabels(): array
    {
        return [
            self::MODEL_GEMINI_FREE => 'Gemini 2.5 Flash бесплатный',
            self::MODEL_GEMINI_PAID => 'Gemini 2.5 Flash платный',
            self::MODEL_OPENAI => 'OpenAI',
        ];
    }

    /**
     * @return array{source: FoodSource, prices: array<string, float|null>, model: string}
     */
    public function refreshRegion(SwissRegion $region, ?string $model = null): array
    {
        $allowedFields = FoodSource::GROCERY_PRICE_FIELDS;
        $model = $this->normalizeModel($model);
        $request = $this->pricesFromModelChain($model, $this->sourceText($region), $this->instruction($allowedFields), $allowedFields);
        $prices = $request['prices'];
        $model = $request['model'];

        $source = FoodSource::query()
            ->where('region_id', $region->id)
            ->where('food_type', FoodSource::TYPE_HOME_COOKING)
            ->orderBy('id')
            ->first();

        if (! $source) {
            $source = new FoodSource([
                'region_id' => $region->id,
                'name' => 'Продуктовая корзина — '.$region->label,
                'food_type' => FoodSource::TYPE_HOME_COOKING,
                'currency' => 'USD',
            ]);
        }

        $payload = [
            'region_id' => $region->id,
            'name' => $source->name ?: 'Продуктовая корзина — '.$region->label,
            'food_type' => FoodSource::TYPE_HOME_COOKING,
            'currency' => 'USD',
            'website' => $source->website,
            'last_checked' => now(),
        ];

        foreach ($allowedFields as $field) {
            $payload[$field] = array_key_exists($field, $prices) ? $prices[$field] : null;
        }

        foreach (FoodSource::RESTAURANT_PRICE_FIELDS as $field) {
            $payload[$field] = null;
        }

        $source->fill($payload);
        $source->save();

        return ['source' => $source, 'prices' => $prices, 'model' => $model];
    }

    /**
     * @param  list<string>  $allowedFields
     * @return array{model: string, prices: array<string, float|null>}
     */
    private function pricesFromModelChain(string $selectedModel, string $material, string $instruction, array $allowedFields): array
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
                $prices = $this->parseJsonPrices($answer, $allowedFields);
                if ($prices !== []) {
                    return ['model' => $model, 'prices' => $prices];
                }
                $lastError = $this->modelLabel($model).' вернул пустые цены.';
            } catch (\Throwable $e) {
                $lastError = $this->modelLabel($model).': '.$e->getMessage();
            }
        }

        throw new RuntimeException($lastError ?: 'Все AI-модели не смогли вернуть цены.');
    }

    /** @return list<string> */
    private function fallbackChain(string $selectedModel): array
    {
        return array_values(array_unique([
            $selectedModel,
            self::MODEL_GEMINI_PAID,
            self::MODEL_OPENAI,
        ]));
    }

    private function normalizeModel(?string $model): string
    {
        $model = trim((string) $model);
        $allowed = array_flip(array_keys(self::modelLabels()));

        return isset($allowed[$model]) ? $model : self::MODEL_GEMINI_FREE;
    }

    private function modelLabel(string $model): string
    {
        return self::modelLabels()[$model] ?? $model;
    }

    private function askModel(string $model, string $material, string $instruction): ?string
    {
        return match ($model) {
            self::MODEL_GEMINI_PAID => $this->geminiPro->chat(
                $material,
                $instruction,
                max(60, (int) config('services.gemini_pro.chat_timeout', 1800)),
                [
                    'temperature' => 0.2,
                    'maxOutputTokens' => max(1024, (int) config('services.gemini_pro.max_output_tokens', 65536)),
                ],
            ),
            self::MODEL_OPENAI => $this->openAi->askOpenAiWithModel(
                $instruction,
                $material,
                trim((string) config('services.openai.model', 'gpt-4o-mini')) ?: 'gpt-4o-mini',
                'FoodSourcePrice:openai',
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
     * @param  list<string>  $allowedFields
     */
    private function instruction(array $allowedFields): string
    {
        $fields = implode(', ', $allowedFields);
		$customPrompt = trim((string) BudgetPromt::query()
			->where('name', self::PROMPT_NAME)
			->value('content'));

		if ($customPrompt !== '') {
			return $customPrompt."\n\nAllowed keys: {$fields}\nUse only the allowed keys. Do not include any other fields.";
		}

        return <<<TXT
You estimate current grocery basket prices for Swiss food budget calculations.

Return ONLY valid JSON object. No markdown. No explanations.
Currency must be USD. Prices must be numeric decimal values or null.

Use realistic average retail prices for the canton/region in Switzerland.
Use common grocery store prices, not restaurant/cafe prices.

Each field is the price for a 3-day supply for one adult who mainly cooks at home.
The sum of all fields should represent a realistic 3-day grocery basket for one adult in that canton.

Allowed keys: {$fields}
Use only the allowed keys. Do not include any other fields.

Meaning of keys (quantities for 3 days, one adult):
- bread_price: bread for 3 days (about 300–450 g total)
- milk_price: 3 liters of milk
- eggs_price: 6 eggs
- chicken_price: meat for 3 days (about 450–600 g)
- rice_price: rice for 3 days (about 240–360 g)
- pasta_grocery_price: pasta for 3 days (about 300–450 g)
- vegetables_price: vegetables for 3 days (about 600–900 g)
- fruits_price: fruits for 3 days (about 450–750 g)
- coffee_price: coffee for 3 days
- water_price: 4.5 liters of bottled water (1.5 L per day)
TXT;
    }

    private function sourceText(SwissRegion $region): string
    {
        return json_encode([
            'country' => 'Switzerland',
            'canton' => $region->label,
            'region_slug' => $region->slug,
            'currency' => 'USD',
            'food_type' => FoodSource::TYPE_HOME_COOKING,
            'basket_days' => FoodSource::GROCERY_BASKET_DAYS,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
    }

    /**
     * @param  list<string>  $allowedFields
     * @return array<string, float|null>
     */
    private function parseJsonPrices(string $answer, array $allowedFields): array
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

        $allowed = array_flip($allowedFields);
        $prices = [];

        foreach ($allowedFields as $field) {
            if (! array_key_exists($field, $data)) {
                $prices[$field] = null;
                continue;
            }

            $value = $data[$field];
            if ($value === null || $value === '') {
                $prices[$field] = null;
                continue;
            }

            if (! is_numeric($value)) {
                $prices[$field] = null;
                continue;
            }

            $price = round((float) $value, 2);
            $prices[$field] = $price >= 0.0 ? $price : null;
        }

        return $prices;
    }
}
