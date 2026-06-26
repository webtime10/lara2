<?php

namespace App\Services;

use App\Models\BudgetPromt;
use App\Models\QuizAnswer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CarBudgetGeminiService
{
    private const ECONOMY_PROMPT = 'car_economy_prompt';

    private const MEDIUM_PROMPT = 'car_medium_prompt';

    private const LUXURY_PROMPT = 'car_luxury_prompt';

    public function __construct(
        private readonly GeminiService $gemini,
    ) {}

    /**
     * @return array{prompt_name: string, payload: array<string, mixed>, answer: string, amount: float}
     */
    public function runForAnswer(QuizAnswer $answer): array
    {
        if (! $this->needsCar($answer)) {
            return [
                'prompt_name' => '',
                'payload' => $this->payloadForAnswer($answer),
                'answer' => 'No car rental selected',
                'amount' => 0.0,
            ];
        }

        $promptName = $this->promptNameForCarClass($answer->car_class);
        $payload = $this->payloadForAnswer($answer);
        $prompt = $this->renderPrompt($promptName, $payload);

        if ($prompt === '') {
            $amount = $this->fallbackAmountForAnswer($answer);

            return [
                'prompt_name' => $promptName,
                'payload' => $payload,
                'answer' => 'Fallback car amount: '.$amount,
                'amount' => $amount,
            ];
        }

        $material = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (! is_string($material) || trim($material) === '') {
            $amount = $this->fallbackAmountForAnswer($answer);

            return [
                'prompt_name' => $promptName,
                'payload' => $payload,
                'answer' => 'Fallback car amount: '.$amount,
                'amount' => $amount,
            ];
        }

        $cacheKey = 'budget_car:'.sha1($promptName.'|'.$prompt.'|'.$material);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['amount'])) {
            return [
                'prompt_name' => $promptName,
                'payload' => $payload,
                'answer' => 'Cached car amount: '.$cached['amount'],
                'amount' => (float) $cached['amount'],
            ];
        }

        Log::info('[car:gemini] request', [
            'quiz_answer_id' => $answer->id,
            'prompt_name' => $promptName,
            'car_class' => $answer->car_class,
            'payload' => $payload,
        ]);

        $rawAnswer = $this->gemini->chat($material, $prompt, $this->gemini->defaultChatTimeout());
        if ($rawAnswer === null || trim($rawAnswer) === '') {
            $amount = $this->fallbackAmountForAnswer($answer);
            Log::warning('[car:gemini] empty_response', [
                'quiz_answer_id' => $answer->id,
                'prompt_name' => $promptName,
                'http_status' => $this->gemini->lastHttpStatus(),
                'fallback_amount' => $amount,
            ]);

            return [
                'prompt_name' => $promptName,
                'payload' => $payload,
                'answer' => 'Fallback car amount: '.$amount,
                'amount' => $amount,
            ];
        }

        $amount = $this->moneyToFloat($rawAnswer);
        if ($amount === null) {
            $amount = $this->fallbackAmountForAnswer($answer);
            Log::warning('[car:gemini] amount_parse_failed', [
                'quiz_answer_id' => $answer->id,
                'prompt_name' => $promptName,
                'raw_answer' => $rawAnswer,
                'fallback_amount' => $amount,
            ]);
        }

        Cache::put($cacheKey, ['amount' => $amount], now()->addDays(20));

        return [
            'prompt_name' => $promptName,
            'payload' => $payload,
            'answer' => $rawAnswer,
            'amount' => $amount,
        ];
    }

    public function fallbackAmountForAnswer(QuizAnswer $answer): float
    {
        if (! $this->needsCar($answer)) {
            return 0.0;
        }

        $days = max(1, (int) ($answer->total_days ?: 1));

        return round($days * $this->dailyFallbackForCarClass($answer->car_class), 2);
    }

    private function needsCar(QuizAnswer $answer): bool
    {
        $value = mb_strtolower(trim((string) $answer->car_rental));

        return in_array($value, ['da', 'yes', 'true', '1'], true);
    }

    private function promptNameForCarClass(?string $carClass): string
    {
        $class = mb_strtolower(trim((string) $carClass));
        if (str_contains($class, 'deshov') || str_contains($class, 'econom') || str_contains($class, 'budget')) {
            return self::ECONOMY_PROMPT;
        }

        if (str_contains($class, 'sredn') || str_contains($class, 'medium')) {
            return self::MEDIUM_PROMPT;
        }

        return self::LUXURY_PROMPT;
    }

    private function dailyFallbackForCarClass(?string $carClass): float
    {
        return match ($this->promptNameForCarClass($carClass)) {
            self::ECONOMY_PROMPT => 65.0,
            self::MEDIUM_PROMPT => 95.0,
            self::LUXURY_PROMPT => 180.0,
            default => 95.0,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadForAnswer(QuizAnswer $answer): array
    {
        return [
            'region' => $answer->region,
            'month' => $this->monthForAnswer($answer),
            'year' => $this->yearForAnswer($answer),
            'days' => (int) $answer->total_days,
            'language' => $answer->language ?: 'ar',
            'car_rental' => $answer->car_rental,
            'car_class' => $answer->car_class,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderPrompt(string $promptName, array $payload): string
    {
        $prompt = trim((string) BudgetPromt::query()
            ->where('name', $promptName)
            ->value('content'));

        if ($prompt === '') {
            return '';
        }

        $replacements = [];
        foreach ($payload as $key => $value) {
            $replacements['{'.$key.'}'] = (string) $value;
        }

        return strtr($prompt, $replacements);
    }

    private function monthForAnswer(QuizAnswer $answer): string
    {
        $months = trim((string) $answer->trip_months);
        if ($months !== '') {
            return $months;
        }

        $date = $this->parseDate($answer->trip_date_from) ?? $this->parseDate($answer->trip_date_to);

        return $date ? $date->format('F') : '';
    }

    private function yearForAnswer(QuizAnswer $answer): string
    {
        $date = $this->parseDate($answer->trip_date_from) ?? $this->parseDate($answer->trip_date_to);

        return $date ? $date->format('Y') : '2026';
    }

    private function parseDate(?string $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d.m.Y', 'Y-m-d'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date;
            }
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable) {
            return null;
        }
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
