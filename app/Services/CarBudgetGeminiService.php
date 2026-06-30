<?php

namespace App\Services;

use App\Models\BudgetPromt;
use App\Models\CarRentalPrice;
use App\Models\QuizAnswer;
use App\Models\SwissRegion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CarBudgetGeminiService
{
    private const PEOPLE_PER_CAR = 5;

    private const ECONOMY_PROMPT = 'car_economy_prompt';

    private const MEDIUM_PROMPT = 'car_medium_prompt';

    private const LUXURY_PROMPT = 'car_luxury_prompt';

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly GeminiProService $geminiPro,
        private readonly OpenAiService $openAi,
    ) {}

    /**
     * @return array{prompt_name: string, payload: array<string, mixed>, answer: string, amount: float}
     */
    public function runForAnswer(QuizAnswer $answer): array
    {
        $payload = $this->payloadForAnswer($answer);

        if (! $this->needsCar($answer)) {
            return [
                'prompt_name' => '',
                'payload' => $payload,
                'answer' => 'No car rental selected',
                'amount' => 0.0,
            ];
        }

        $amount = $this->fallbackAmountForAnswer($answer);

        $carsCount = $this->carsCountForAnswer($answer);

        return [
            'prompt_name' => $this->promptNameForCarClass($answer->car_class),
            'payload' => $payload,
            'answer' => 'Database car rental amount: '.$amount.' ('.$carsCount.' car'.($carsCount > 1 ? 's' : '').')',
            'amount' => $amount,
        ];
    }

    /** @return array{answer: ?string, amount: ?float, model: string} */
    private function moneyAnswerFromModelChain(string $material, string $prompt): array
    {
        $lastAnswer = null;
        $lastModel = FoodSourceGeminiPriceService::MODEL_GEMINI_FREE;

        foreach ($this->fallbackChain() as $model) {
            $lastModel = $model;
            try {
                $answer = $this->askModel($model, $material, $prompt);
            } catch (\Throwable) {
                continue;
            }

            if ($answer === null || trim($answer) === '') {
                continue;
            }

            $lastAnswer = $answer;
            $amount = $this->moneyToFloat($answer);
            if ($amount !== null) {
                return ['answer' => $answer, 'amount' => $amount, 'model' => $model];
            }
        }

        return ['answer' => $lastAnswer, 'amount' => null, 'model' => $lastModel];
    }

    private function askModel(string $model, string $material, string $prompt): ?string
    {
        return match ($model) {
            FoodSourceGeminiPriceService::MODEL_GEMINI_PAID => $this->geminiPro->chat(
                $material,
                $prompt,
                max(60, (int) config('services.gemini_pro.chat_timeout', 1800)),
                [
                    'maxOutputTokens' => max(1024, (int) config('services.gemini_pro.max_output_tokens', 65536)),
                ],
            ),
            FoodSourceGeminiPriceService::MODEL_OPENAI => $this->openAi->askOpenAiWithModel(
                $prompt,
                $material,
                trim((string) config('services.openai.model', 'gpt-4o-mini')) ?: 'gpt-4o-mini',
                'CarBudget:openai',
            ),
            default => $this->gemini->chat($material, $prompt, $this->gemini->defaultChatTimeout()),
        };
    }

    /** @return list<string> */
    private function fallbackChain(): array
    {
        return [
            FoodSourceGeminiPriceService::MODEL_GEMINI_FREE,
            FoodSourceGeminiPriceService::MODEL_GEMINI_PAID,
            FoodSourceGeminiPriceService::MODEL_OPENAI,
        ];
    }

    public function fallbackAmountForAnswer(QuizAnswer $answer): float
    {
        if (! $this->needsCar($answer)) {
            return 0.0;
        }

        $storedAmount = $this->storedAmountForAnswer($answer);
        if ($storedAmount !== null) {
            return $storedAmount;
        }

        $days = max(1, (int) ($answer->total_days ?: 1));
        $carsCount = $this->carsCountForAnswer($answer);

        return round($days * $this->dailyFallbackForCarClass($answer->car_class) * $carsCount, 2);
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

    private function storedAmountForAnswer(QuizAnswer $answer): ?float
    {
        $region = $this->regionForAnswer($answer);
        $carClass = $this->carClassKey($answer->car_class);
        if (! $region || $carClass === null) {
            return null;
        }

        $price = CarRentalPrice::query()
            ->where('region_id', $region->id)
            ->where('car_class', $carClass)
            ->first();

        if (! $price || $price->daily_price === null) {
            return null;
        }

        $days = max(1, (int) ($answer->total_days ?: 1));
        $carsCount = $this->carsCountForAnswer($answer);

        return round($days * (float) $price->daily_price * $carsCount, 2);
    }

    private function carsCountForAnswer(QuizAnswer $answer): int
    {
        $people = (int) ($answer->total_people ?? 0);
        if ($people <= 0) {
            $people = (int) $answer->travelers_count + (int) $answer->children_count;
        }

        return max(1, (int) ceil(max(1, $people) / self::PEOPLE_PER_CAR));
    }

    private function carClassKey(?string $carClass): ?string
    {
        return match ($this->promptNameForCarClass($carClass)) {
            self::ECONOMY_PROMPT => CarRentalPrice::CLASS_ECONOMY,
            self::MEDIUM_PROMPT => CarRentalPrice::CLASS_MEDIUM,
            self::LUXURY_PROMPT => CarRentalPrice::CLASS_LUXURY,
            default => null,
        };
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
            'total_people' => max(1, (int) ($answer->total_people ?: ((int) $answer->travelers_count + (int) $answer->children_count))),
            'cars_count' => $this->carsCountForAnswer($answer),
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
