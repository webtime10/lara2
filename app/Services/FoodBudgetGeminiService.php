<?php

namespace App\Services;

use App\Models\BudgetPromt;
use App\Models\QuizAnswer;
use Illuminate\Support\Facades\Log;

class FoodBudgetGeminiService
{
    private const GROCERY_PROMPT = 'korzina_magazina';

    private const CAFE_PROMPT = 'cafe_prompt';

    private const RESTAURANTS_PROMPT = 'restaurants_prompt';

    public function __construct(
        private readonly GeminiService $gemini,
    ) {}

    /**
     * @return array{prompt_name: string, payload: array<string, mixed>, answer: string, amount: float}
     */
    public function runForAnswer(QuizAnswer $answer): array
    {
        $promptName = $this->promptNameForDiningLevel($answer->dining_level);
        $payload = $this->payloadForAnswer($answer);
        $prompt = $this->renderPrompt($promptName, $payload);

        if ($prompt === '') {
            $amount = $this->fallbackAmountForAnswer($answer);

            return [
                'prompt_name' => $promptName,
                'payload' => $payload,
                'answer' => 'Fallback food amount: '.$amount,
                'amount' => $amount,
            ];
        }

        $material = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (! is_string($material) || trim($material) === '') {
            $amount = $this->fallbackAmountForAnswer($answer);

            return [
                'prompt_name' => $promptName,
                'payload' => $payload,
                'answer' => 'Fallback food amount: '.$amount,
                'amount' => $amount,
            ];
        }

        Log::info('[food:gemini] request', [
            'quiz_answer_id' => $answer->id,
            'prompt_name' => $promptName,
            'dining_level' => $answer->dining_level,
            'payload' => $payload,
        ]);

        $rawAnswer = $this->gemini->chat($material, $prompt, $this->gemini->defaultChatTimeout());
        if ($rawAnswer === null || trim($rawAnswer) === '') {
            $amount = $this->fallbackAmountForAnswer($answer);
            Log::warning('[food:gemini] empty_response', [
                'quiz_answer_id' => $answer->id,
                'prompt_name' => $promptName,
                'http_status' => $this->gemini->lastHttpStatus(),
                'fallback_amount' => $amount,
            ]);

            return [
                'prompt_name' => $promptName,
                'payload' => $payload,
                'answer' => 'Fallback food amount: '.$amount,
                'amount' => $amount,
            ];
        }

        $amount = $this->moneyToFloat($rawAnswer);
        if ($amount === null) {
            $amount = $this->fallbackAmountForAnswer($answer);
            Log::warning('[food:gemini] amount_parse_failed', [
                'quiz_answer_id' => $answer->id,
                'prompt_name' => $promptName,
                'raw_answer' => $rawAnswer,
                'fallback_amount' => $amount,
            ]);
        }

        Log::info('[food:gemini] response', [
            'quiz_answer_id' => $answer->id,
            'prompt_name' => $promptName,
            'raw_answer' => $rawAnswer,
            'parsed_amount' => $amount,
        ]);

        return [
            'prompt_name' => $promptName,
            'payload' => $payload,
            'answer' => $rawAnswer,
            'amount' => $amount,
        ];
    }

    public function fallbackAmountForAnswer(QuizAnswer $answer): float
    {
        $days = max(1, (int) ($answer->total_days ?: 1));
        $adults = max(0, (int) $answer->travelers_count);
        $children = max(0, (int) $answer->children_count);
        $adultEquivalent = max(1.0, $adults + ($children * 0.6));
        $dailyPerAdult = $this->dailyFallbackForDiningLevel($answer->dining_level);

        return round($days * $adultEquivalent * $dailyPerAdult, 2);
    }

    private function promptNameForDiningLevel(?string $diningLevel): string
    {
        $level = mb_strtolower(trim((string) $diningLevel));
        if (str_contains($level, 'v_osnovnom') || str_contains($level, 'doma') || str_contains($level, 'home')) {
            return self::GROCERY_PROMPT;
        }

        if (str_contains($level, 'nedorogie') || str_contains($level, 'kafe') || str_contains($level, 'cafe')) {
            return self::CAFE_PROMPT;
        }

        return self::RESTAURANTS_PROMPT;
    }

    private function dailyFallbackForDiningLevel(?string $diningLevel): float
    {
        return match ($this->promptNameForDiningLevel($diningLevel)) {
            self::GROCERY_PROMPT => 35.0,
            self::CAFE_PROMPT => 55.0,
            self::RESTAURANTS_PROMPT => 95.0,
            default => 55.0,
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
            'adults' => (int) $answer->travelers_count,
            'children' => (int) $answer->children_count,
            'days' => (int) $answer->total_days,
            'language' => $answer->language ?: 'ar',
            'dining_level' => $answer->dining_level,
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
