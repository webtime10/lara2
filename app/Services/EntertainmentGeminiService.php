<?php

namespace App\Services;

use App\Models\EntertainmentVisitPrice;
use App\Models\QuizAnswer;
use App\Models\SwissRegion;
use Illuminate\Support\Facades\Schema;

class EntertainmentGeminiService
{
    /**
     * @return array{payload: array<string, mixed>, answer: string, amount: float}
     */
    public function runForAnswer(QuizAnswer $answer): array
    {
        $days = max(1, (int) ($answer->total_days ?: 1));
        $visits = $this->visitsForLevel($answer->entertainment_level, $days);
        $amount = $this->fallbackAmountForAnswer($answer);

        return [
            'payload' => [
                'region' => $answer->region,
                'entertainment_level' => $answer->entertainment_level,
                'total_days' => $days,
                'total_people' => $answer->total_people,
                'visits' => $visits,
            ],
            'answer' => 'Entertainment budget from visit prices: '.$amount,
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

    public function visitsForLevel(?string $entertainmentLevel, int $days): int
    {
        $days = max(1, $days);
        $level = mb_strtolower(trim((string) $entertainmentLevel));

        // kak_mojno_menhe_platnix → 1 развлечение в 3 дня
        if (
            str_contains($level, 'menhe_platnix')
            || str_contains($level, 'tri_dnya')
            || str_contains($level, 'v_tri')
        ) {
            return max(1, (int) ceil($days / 3));
        }

        // razvlechenia_raz_v_neskolko_dnay → 1 развлечение в 2 дня
        if (
            str_contains($level, 'neskolko')
            || str_contains($level, 'dva_dnya')
            || str_contains($level, 'v_dva')
        ) {
            return max(1, (int) ceil($days / 2));
        }

        // kazdii_den → 1 развлечение каждый день
        return $days;
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
}
