<?php

namespace App\Services;

use App\Models\FoodSample;
use App\Models\SwissRegion;
use RuntimeException;

class FoodSampleGptClassificationService
{
    private const BATCH_SIZE = 50;

    /** @var list<string> */
    private const ALLOWED_TYPES = [
        'restaurant',
        'fine_restaurant',
    ];

    public function __construct(
        private readonly OpenAiService $openAi,
    ) {}

    /**
     * @return array{processed: int, remaining: int}
     */
    public function classifyNextBatch(SwissRegion $region): array
    {
        $items = FoodSample::query()
            ->where('region_id', $region->id)
            ->where('food_type', 'restaurant_candidate')
            ->where('gpt_processed', false)
            ->orderByDesc('reviews_count')
            ->limit(self::BATCH_SIZE)
            ->get(['id', 'name', 'website']);

        if ($items->isEmpty()) {
            return [
                'processed' => 0,
                'remaining' => $this->remainingCandidates($region),
            ];
        }

        $payload = $items->map(fn (FoodSample $item): array => [
            'id' => $item->id,
            'name' => $item->name,
            'website' => $item->website,
        ])->values()->all();

        $answer = $this->openAi->askOpenAiWithModel(
            $this->prompt(),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
            trim((string) config('services.openai.extraction_model', 'gpt-4o-mini')),
            'FoodSampleGptClassificationService'
        );

        if ($answer === null || trim($answer) === '') {
            throw new RuntimeException('GPT не вернул ответ.');
        }

        $rows = $this->parseRows($answer);
        $processed = 0;

        foreach ($rows as $row) {
            $type = $row['food_type'];
            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                continue;
            }

            $updated = FoodSample::query()
                ->where('region_id', $region->id)
                ->where('id', $row['id'])
                ->where('food_type', 'restaurant_candidate')
                ->where('gpt_processed', false)
                ->update([
                    'food_type' => $type,
                    'gpt_processed' => true,
                    'updated_at' => now(),
                ]);

            $processed += $updated;
        }

        return [
            'processed' => $processed,
            'remaining' => $this->remainingCandidates($region),
        ];
    }

    private function remainingCandidates(SwissRegion $region): int
    {
        return FoodSample::query()
            ->where('region_id', $region->id)
            ->where('food_type', 'restaurant_candidate')
            ->where('gpt_processed', false)
            ->count();
    }

    private function prompt(): string
    {
        return <<<'TXT'
Определи категорию заведения.

Варианты:
restaurant
fine_restaurant

Верни только JSON:
[
  {
    "id": 123,
    "food_type": "fine_restaurant"
  }
]
TXT;
    }

    /**
     * @return list<array{id: int, food_type: string}>
     */
    private function parseRows(string $answer): array
    {
        $json = trim($answer);
        if (str_starts_with($json, '```')) {
            $json = preg_replace('/^```(?:json)?\s*/i', '', $json) ?? $json;
            $json = preg_replace('/\s*```$/', '', $json) ?? $json;
            $json = trim($json);
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Не удалось разобрать JSON от GPT.');
        }

        $rows = [];
        foreach ($decoded as $row) {
            if (! is_array($row) || ! isset($row['id'], $row['food_type'])) {
                continue;
            }

            $rows[] = [
                'id' => (int) $row['id'],
                'food_type' => trim((string) $row['food_type']),
            ];
        }

        return $rows;
    }
}
