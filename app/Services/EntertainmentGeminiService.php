<?php

namespace App\Services;

use App\Models\BudgetPromt;
use App\Models\EntertainmentVisitPrice;
use App\Models\QuizAnswer;
use App\Models\SwissRegion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class EntertainmentGeminiService
{
    public const PROMPT_NAME = 'entertainment_prompt';

    public const DAILY_PROMPT_NAME = 'entertainment_prompt_daily';

    public const EVERY_TWO_DAYS_PROMPT_NAME = 'entertainment_prompt_every_two_days';

    public const EVERY_THREE_DAYS_PROMPT_NAME = 'entertainment_prompt_every_three_days';

    public function __construct(
        private readonly GeminiService $gemini,
        private readonly GeminiProService $geminiPro,
        private readonly OpenAiService $openAi,
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
        $payload = [
            'region' => $answer->region,
            'entertainment_level' => $answer->entertainment_level,
            'total_days' => $answer->total_days,
            'total_people' => $answer->total_people,
        ];
        $amount = $this->fallbackAmountForAnswer($answer);

        return [
            'payload' => $payload,
            'answer' => 'Database entertainment amount: '.$amount,
            'amount' => $amount,
        ];
    }

    public function fallbackAmountForAnswer(QuizAnswer $answer): float
    {
        $stored = $this->storedAmountForAnswer($answer);
        if ($stored !== null) {
            return $stored;
        }

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

        $cacheKey = 'budget_entertainment:'.sha1($promptName.'|'.$prompt.'|'.$material);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['amount'])) {
            return [
                'payload' => $payload,
                'answer' => 'Cached entertainment amount: '.$cached['amount'],
                'amount' => (float) $cached['amount'],
            ];
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

        $aiAnswer = $this->moneyAnswerFromModelChain($material, $prompt);
        $answer = $aiAnswer['answer'];
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

        $amount = $aiAnswer['amount'];
        Log::info('[entertainment:gemini] response', [
            'region_id' => $region->id,
            'region' => $region->slug,
            'prompt_name' => $promptName,
            'http_status' => $this->gemini->lastHttpStatus(),
            'raw_answer' => $answer,
            'parsed_amount' => $amount,
            'model' => $aiAnswer['model'],
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

        Cache::put($cacheKey, ['amount' => $amount], now()->addDays(20));

        return [
            'payload' => $payload,
            'answer' => $answer,
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
                'EntertainmentBudget:openai',
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

    private function storedAmountForAnswer(QuizAnswer $answer): ?float
    {
        $region = $this->regionForAnswer($answer);
        if ($region === null || ! Schema::hasTable('entertainment_visit_prices')) {
            return null;
        }

        $days = max(1, (int) ($answer->total_days ?: 1));
        $visits = $this->visitsForLevel($answer->entertainment_level, $days);
        $adults = max(0, (int) $answer->travelers_count);
        $children = max(0, (int) $answer->children_count);
        if ($adults + $children <= 0) {
            $adults = max(1, (int) $answer->total_people);
        }

        $prices = EntertainmentVisitPrice::query()
            ->where('region_id', $region->id)
            ->whereNotNull('adult_avg_price')
            ->get()
            ->keyBy('category');

        if ($prices->isEmpty()) {
            return null;
        }

        $categories = $region->entertainments()
            ->select('category')
            ->whereIn('category', $prices->keys()->all())
            ->groupBy('category')
            ->orderByRaw('MAX(rating) DESC')
            ->orderByRaw('MAX(reviews) DESC')
            ->limit(max(1, $visits))
            ->pluck('category')
            ->values();

        if ($categories->isEmpty()) {
            $categories = $prices->keys()->take(max(1, $visits))->values();
        }

        $visitTotals = [];
        foreach ($categories as $category) {
            $price = $prices->get($category);
            if (! $price) {
                continue;
            }
            $adultPrice = (float) $price->adult_avg_price;
            $childPrice = $price->child_avg_price !== null ? (float) $price->child_avg_price : round($adultPrice * 0.6, 2);
            $visitTotals[] = ($adults * $adultPrice) + ($children * $childPrice);
        }

        if ($visitTotals === []) {
            return null;
        }

        $total = array_sum($visitTotals);
        if ($visits > count($visitTotals)) {
            $total += ($visits - count($visitTotals)) * (array_sum($visitTotals) / count($visitTotals));
        }

        return round($total, 2);
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
