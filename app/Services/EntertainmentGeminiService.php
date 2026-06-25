<?php

namespace App\Services;

use App\Models\BudgetPromt;
use App\Models\QuizAnswer;
use App\Models\SwissRegion;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class EntertainmentGeminiService
{
    public const PROMPT_NAME = 'entertainment_prompt';

    public const DAILY_PROMPT_NAME = 'entertainment_prompt_daily';

    public const EVERY_TWO_DAYS_PROMPT_NAME = 'entertainment_prompt_every_two_days';

    public const EVERY_THREE_DAYS_PROMPT_NAME = 'entertainment_prompt_every_three_days';

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly SwissEntertainmentsService $entertainments,
    ) {}

    /**
     * @return array{payload: array<string, mixed>, answer: string}
     */
    public function runForRegion(SwissRegion $region, ?string $entertainmentLevel = null): array
    {
        return $this->run($region, $entertainmentLevel);
    }

    /**
     * @return array{payload: array<string, mixed>, answer: string, amount: ?float}
     */
    public function runForAnswer(QuizAnswer $answer): array
    {
        $region = $this->regionForAnswer($answer);
        if ($region === null) {
            throw new RuntimeException('Регион для развлечений не найден: '.$answer->region);
        }

        return $this->run($region, $answer->entertainment_level, [
            'total_days' => $answer->total_days,
            'total_people' => $answer->total_people,
        ]);
    }

    public function fallbackAmountForAnswer(QuizAnswer $answer): float
    {
        $days = max(1, (int) ($answer->total_days ?: 1));
        $people = max(1, (int) ($answer->total_people ?: ((int) $answer->travelers_count + (int) $answer->children_count)));
        $visits = $this->visitsForLevel($answer->entertainment_level, $days);

        return (float) ($visits * $people * 35);
    }

    /**
     * @param  array<string, mixed>  $extraPayload
     * @return array{payload: array<string, mixed>, answer: string, amount: ?float}
     */
    private function run(SwissRegion $region, ?string $entertainmentLevel = null, array $extraPayload = []): array
    {
        $promptName = $this->promptNameForLevel($entertainmentLevel);
        $prompt = $this->loadPrompt($promptName);

        if ($prompt === '') {
            throw new RuntimeException('Промт для развлечений не задан: Промты WP → Budget → Промты для развлечений.');
        }

        $payload = $this->entertainments->structuredForRegion($region);
        $payload['entertainment_prompt_name'] = $promptName;
        $payload['entertainment_level'] = $entertainmentLevel;
        foreach ($extraPayload as $key => $value) {
            $payload[$key] = $value;
        }

        $material = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (! is_string($material) || trim($material) === '') {
            throw new RuntimeException('Не удалось подготовить JSON развлечений для Gemini.');
        }

        Log::info('[entertainment:gemini] request', [
            'region_id' => $region->id,
            'region' => $region->slug,
            'prompt_name' => $promptName,
            'entertainment_level' => $entertainmentLevel,
            'total_days' => $extraPayload['total_days'] ?? null,
            'total_people' => $extraPayload['total_people'] ?? null,
            'payload' => $payload,
        ]);

        $answer = $this->gemini->chat($material, $prompt, $this->gemini->defaultChatTimeout());
        if ($answer === null || trim($answer) === '') {
            Log::warning('[entertainment:gemini] empty_response', [
                'region_id' => $region->id,
                'region' => $region->slug,
                'prompt_name' => $promptName,
                'http_status' => $this->gemini->lastHttpStatus(),
            ]);

            $amount = $this->fallbackAmount($entertainmentLevel, $extraPayload);

            return [
                'payload' => $payload,
                'answer' => 'Fallback entertainment amount: '.$amount,
                'amount' => $amount,
            ];
        }

        $amount = $this->moneyToFloat($answer);
        Log::info('[entertainment:gemini] response', [
            'region_id' => $region->id,
            'region' => $region->slug,
            'prompt_name' => $promptName,
            'http_status' => $this->gemini->lastHttpStatus(),
            'raw_answer' => $answer,
            'parsed_amount' => $amount,
        ]);

        if ($amount === null) {
            Log::warning('[entertainment:gemini] amount_parse_failed', [
                'region_id' => $region->id,
                'region' => $region->slug,
                'prompt_name' => $promptName,
                'raw_answer' => $answer,
            ]);

            $amount = $this->fallbackAmount($entertainmentLevel, $extraPayload);
        }

        return [
            'payload' => $payload,
            'answer' => $answer,
            'amount' => $amount,
        ];
    }

    private function promptNameForLevel(?string $entertainmentLevel): string
    {
        $level = mb_strtolower(trim((string) $entertainmentLevel));

        if (
            str_contains($level, '3')
            || str_contains($level, 'tri')
            || str_contains($level, 'three')
            || str_contains($level, 'neskolko')
            || str_contains($level, 'few')
        ) {
            return self::EVERY_THREE_DAYS_PROMPT_NAME;
        }

        if (
            str_contains($level, '2')
            || str_contains($level, 'dva')
            || str_contains($level, 'two')
        ) {
            return self::EVERY_TWO_DAYS_PROMPT_NAME;
        }

        return self::DAILY_PROMPT_NAME;
    }

    private function fallbackAmount(?string $entertainmentLevel, array $payload): float
    {
        $days = max(1, (int) ($payload['total_days'] ?? 1));
        $people = max(1, (int) ($payload['total_people'] ?? 1));
        $visits = $this->visitsForLevel($entertainmentLevel, $days);

        return (float) ($visits * $people * 35);
    }

    private function visitsForLevel(?string $entertainmentLevel, int $days): int
    {
        $level = mb_strtolower(trim((string) $entertainmentLevel));
        if (
            str_contains($level, '3')
            || str_contains($level, 'tri')
            || str_contains($level, 'three')
            || str_contains($level, 'neskolko')
            || str_contains($level, 'few')
        ) {
            return max(1, (int) ceil($days / 3));
        }

        if (
            str_contains($level, '2')
            || str_contains($level, 'dva')
            || str_contains($level, 'two')
        ) {
            return max(1, (int) ceil($days / 2));
        }

        return max(1, $days);
    }

    private function loadPrompt(string $name): string
    {
        $prompt = trim((string) BudgetPromt::query()
            ->where('name', $name)
            ->value('content'));

        if ($prompt !== '') {
            return $prompt;
        }

        return trim((string) BudgetPromt::query()
            ->where('name', self::PROMPT_NAME)
            ->value('content'));
    }

    private function regionForAnswer(QuizAnswer $answer): ?SwissRegion
    {
        $region = trim((string) $answer->region);
        if ($region === '') {
            return null;
        }

        return SwissRegion::query()
            ->where('slug', $region)
            ->orWhere('label', $region)
            ->first();
    }

    private function moneyToFloat(string $value): ?float
    {
        $normalized = preg_replace('/[^\d,.\-]+/u', '', trim($value)) ?? '';
        if ($normalized === '' || $normalized === '-') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace(',', '', $normalized);
        } elseif (str_contains($normalized, ',') && ! str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }
}
