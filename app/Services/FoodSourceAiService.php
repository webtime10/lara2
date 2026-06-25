<?php

namespace App\Services;

use App\Models\FoodSource;
use RuntimeException;

class FoodSourceAiService
{
    public function __construct(
        private OpenAiService $openAi,
    ) {}

    /** @return array<string, mixed> */
    public function refreshPrices(FoodSource $source): array
    {
        $website = trim((string) $source->website);
        if ($website === '') {
            throw new RuntimeException('Для обновления через ChatGPT нужно заполнить website.');
        }

        $allowedFields = $source->activePriceFields();
        if ($allowedFields === []) {
            throw new RuntimeException('Неизвестный food_type: '.$source->food_type);
        }

        $answer = $this->openAi->askOpenAiWithModel(
            $this->instruction($source, $allowedFields),
            $this->sourceText($source),
            trim((string) config('services.openai.extraction_model', 'gpt-4o-mini')),
            'FoodSourceAiService'
        );

        if ($answer === null || trim($answer) === '') {
            throw new RuntimeException('ChatGPT не вернул ответ.');
        }

        $prices = $this->parseJsonPrices($answer, $allowedFields);
        if ($prices === []) {
            throw new RuntimeException('ChatGPT не вернул подходящих полей цен.');
        }

        $payload = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $prices)) {
                $payload[$field] = $prices[$field];
            }
        }

        foreach (FoodSource::inactivePriceFieldsForType($source->food_type) as $field) {
            $payload[$field] = null;
        }

        $payload['last_checked'] = now();
        $source->update($payload);

        return $payload;
    }

    /**
     * @param  list<string>  $allowedFields
     */
    private function instruction(FoodSource $source, array $allowedFields): string
    {
        $fields = implode(', ', $allowedFields);

        return <<<TXT
You update source prices for Swiss food budget calculations.

Return ONLY valid JSON object. No markdown. No comments.
Currency must be CHF. Prices must be numeric decimal values or null.

Food type: {$source->food_type}
Allowed keys: {$fields}

Use only the allowed keys. Do not include any other price fields.
If a price cannot be reasonably determined from the source, return null for that key.
TXT;
    }

    private function sourceText(FoodSource $source): string
    {
        return json_encode([
            'region' => $source->region?->label,
            'name' => $source->name,
            'website' => $source->website,
            'food_type' => $source->food_type,
            'currency' => $source->currency ?: 'CHF',
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
            throw new RuntimeException('Не удалось разобрать JSON от ChatGPT.');
        }

        $allowed = array_flip($allowedFields);
        $prices = [];

        foreach ($data as $field => $value) {
            if (! is_string($field) || ! isset($allowed[$field])) {
                continue;
            }

            if ($value === null || $value === '') {
                $prices[$field] = null;
                continue;
            }

            if (! is_numeric($value)) {
                continue;
            }

            $price = round((float) $value, 2);
            $prices[$field] = $price >= 0.0 ? $price : null;
        }

        return $prices;
    }
}
