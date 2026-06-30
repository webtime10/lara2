<?php

namespace App\Services;

use App\Models\BudgetPromt;
use App\Models\FoodImport;
use App\Models\FoodSample;
use App\Models\FoodSource;
use App\Models\FoodVisitPrice;
use App\Models\QuizAnswer;
use App\Models\SwissRegion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FoodBudgetGeminiService
{
    private const GROCERY_PROMPT = 'korzina_magazina';

    private const CAFE_PROMPT = 'cafe_prompt';

    private const RESTAURANTS_PROMPT = 'restaurants_prompt';

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
        $promptName = $this->promptNameForDiningLevel($answer->dining_level);
        $payload = $this->payloadForAnswer($answer);
        $amount = $this->fallbackAmountForAnswer($answer);

        return [
            'prompt_name' => $promptName,
            'payload' => $payload,
            'answer' => 'Database food amount: '.$amount,
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
                'FoodBudget:openai',
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
        $storedGroceryPrice = $this->storedGroceryPriceForAnswer($answer);
        if ($storedGroceryPrice !== null) {
            $days = max(1, (int) ($answer->total_days ?: 1));
            $adults = max(0, (int) $answer->travelers_count);
            $children = max(0, (int) $answer->children_count);
            $adultEquivalent = max(1.0, $adults + ($children * 0.6));

            return round($days * $adultEquivalent * $storedGroceryPrice, 2);
        }

        $storedVisitPrice = $this->storedVisitPriceForAnswer($answer);
        if ($storedVisitPrice !== null) {
            $days = max(1, (int) ($answer->total_days ?: 1));
            $adults = max(0, (int) $answer->travelers_count);
            $children = max(0, (int) $answer->children_count);

            return round($days * (($adults * $storedVisitPrice['adult']) + ($children * $storedVisitPrice['child'])), 2);
        }

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
        $region = $this->regionForAnswer($answer);

        return [
            'region' => $answer->region,
            'month' => $this->monthForAnswer($answer),
            'year' => $this->yearForAnswer($answer),
            'adults' => (int) $answer->travelers_count,
            'children' => (int) $answer->children_count,
            'days' => (int) $answer->total_days,
            'language' => $answer->language ?: 'ar',
            'dining_level' => $answer->dining_level,
            'food_sources' => $region ? $this->foodSourcesForRegion($region) : [],
            'food_places' => $region ? $this->foodPlacesForRegion($region) : [],
            'food_visit_price' => $region ? $this->foodVisitPriceForRegion($region, $answer->dining_level) : null,
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
            $replacements['{'.$key.'}'] = is_array($value)
                ? (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')
                : (string) $value;
        }

        return strtr($prompt, $replacements);
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
     * @return array<int, array<string, mixed>>
     */
    private function foodSourcesForRegion(SwissRegion $region): array
    {
        return FoodSource::query()
            ->where('region_id', $region->id)
            ->where('food_type', FoodSource::TYPE_HOME_COOKING)
            ->orderBy('food_type')
            ->orderBy('price_level')
            ->orderByDesc('rating')
            ->limit(30)
            ->get()
            ->map(fn (FoodSource $source): array => $this->foodSourceRow($source))
            ->values()
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function foodPlacesForRegion(SwissRegion $region): array
    {
        $samples = FoodSample::query()
            ->where('region_id', $region->id)
            ->whereIn('food_type', ['cafe', 'restaurant', 'restaurant_candidate'])
            ->orderBy('sample_rank')
            ->orderByDesc('reviews_count')
            ->orderByDesc('rating')
            ->get();

        if ($samples->isNotEmpty()) {
            return [
                'cafes' => $samples
                    ->where('food_type', 'cafe')
                    ->map(fn (FoodSample $item): array => $this->foodPlaceRow($item))
                    ->values()
                    ->all(),
                'restaurants' => $samples
                    ->where('food_type', 'restaurant')
                    ->map(fn (FoodSample $item): array => $this->foodPlaceRow($item))
                    ->values()
                    ->all(),
                'restaurant_candidates' => $samples
                    ->where('food_type', 'restaurant_candidate')
                    ->map(fn (FoodSample $item): array => $this->foodPlaceRow($item))
                    ->values()
                    ->all(),
            ];
        }

        $imports = FoodImport::query()
            ->where('region_id', $region->id)
            ->whereIn('food_type', ['cafe', 'restaurant', 'restaurant_candidate'])
            ->orderByDesc('reviews_count')
            ->orderByDesc('rating')
            ->limit(40)
            ->get();

        return [
            'cafes' => $imports
                ->where('food_type', 'cafe')
                ->map(fn (FoodImport $item): array => $this->foodPlaceRow($item))
                ->values()
                ->all(),
            'restaurants' => $imports
                ->where('food_type', 'restaurant')
                ->map(fn (FoodImport $item): array => $this->foodPlaceRow($item))
                ->values()
                ->all(),
            'restaurant_candidates' => $imports
                ->where('food_type', 'restaurant_candidate')
                ->map(fn (FoodImport $item): array => $this->foodPlaceRow($item))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{type: string, adult_avg_price: float, child_avg_price: float, currency: string, places_count: int, last_checked: ?string}|null
     */
    private function foodVisitPriceForRegion(SwissRegion $region, ?string $diningLevel): ?array
    {
        $foodType = $this->foodVisitTypeForDiningLevel($diningLevel);
        if ($foodType === null) {
            return null;
        }

        $price = FoodVisitPrice::query()
            ->where('region_id', $region->id)
            ->where('food_type', $foodType)
            ->first();

        if (! $price || $price->adult_avg_price === null) {
            return null;
        }

        return [
            'type' => $foodType,
            'adult_avg_price' => (float) $price->adult_avg_price,
            'child_avg_price' => $price->child_avg_price !== null ? (float) $price->child_avg_price : round((float) $price->adult_avg_price * 0.6, 2),
            'currency' => $price->currency ?: 'USD',
            'places_count' => (int) $price->places_count,
            'last_checked' => $price->last_checked?->format('d.m.Y H:i'),
        ];
    }

    /** @return array{adult: float, child: float}|null */
    private function storedVisitPriceForAnswer(QuizAnswer $answer): ?array
    {
        $region = $this->regionForAnswer($answer);
        if (! $region) {
            return null;
        }

        $visitPrice = $this->foodVisitPriceForRegion($region, $answer->dining_level);
        if ($visitPrice === null) {
            return null;
        }

        return [
            'adult' => $visitPrice['adult_avg_price'],
            'child' => $visitPrice['child_avg_price'],
        ];
    }

    private function storedGroceryPriceForAnswer(QuizAnswer $answer): ?float
    {
        if ($this->promptNameForDiningLevel($answer->dining_level) !== self::GROCERY_PROMPT) {
            return null;
        }

        $region = $this->regionForAnswer($answer);
        if (! $region) {
            return null;
        }

        $totals = FoodSource::query()
            ->where('region_id', $region->id)
            ->where('food_type', FoodSource::TYPE_HOME_COOKING)
            ->get()
            ->map(function (FoodSource $source): float {
                $sum = 0.0;
                foreach (FoodSource::GROCERY_PRICE_FIELDS as $field) {
                    if ($source->{$field} !== null && $source->{$field} !== '') {
                        $sum += (float) $source->{$field};
                    }
                }

                return $sum;
            })
            ->filter(fn (float $sum): bool => $sum > 0);

        return $totals->isNotEmpty() ? round((float) $totals->avg(), 2) : null;
    }

    private function foodVisitTypeForDiningLevel(?string $diningLevel): ?string
    {
        $promptName = $this->promptNameForDiningLevel($diningLevel);

        return match ($promptName) {
            self::CAFE_PROMPT => FoodVisitPrice::TYPE_CAFE,
            self::RESTAURANTS_PROMPT => FoodVisitPrice::TYPE_RESTAURANT,
            default => null,
        };
    }

    /**
     * @param FoodImport|FoodSample $item
     * @return array<string, mixed>
     */
    private function foodPlaceRow(FoodImport|FoodSample $item): array
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
     * @return array<string, mixed>
     */
    private function foodSourceRow(FoodSource $source): array
    {
        return array_filter([
            'name' => $source->name,
            'type' => $source->food_type,
            'price_level' => $source->price_level,
            'currency' => $source->currency ?: 'CHF',
            'rating' => $source->rating !== null ? (float) $source->rating : null,
            'reviews_count' => $source->reviews_count,
            'address' => $source->address,
            'website' => $source->website,
            'prices' => $this->priceFieldsForSource($source),
        ], fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * @return array<string, float>
     */
    private function priceFieldsForSource(FoodSource $source): array
    {
        $prices = [];
        foreach ($source->activePriceFields() as $field) {
            if ($source->{$field} !== null && $source->{$field} !== '') {
                $prices[$field] = (float) $source->{$field};
            }
        }

        return $prices;
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
