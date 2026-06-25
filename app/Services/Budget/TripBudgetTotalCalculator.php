<?php

namespace App\Services\Budget;

use App\Models\QuizAnswer;
use App\Services\BudgetPriorityAdjustmentService;

class TripBudgetTotalCalculator
{
    public function __construct(
        private readonly HotelBudgetCalculator $hotelBudgetCalculator,
        private readonly ApartmentBudgetCalculator $apartmentBudgetCalculator,
        private readonly BudgetPriorityAdjustmentService $priorityAdjustmentService,
    ) {}

    public function applyTo(QuizAnswer $answer): ?float
    {
        $housingTotal = $this->housingTotal($answer);
        $entertainmentTotal = $this->moneyToFloat($answer->entertainment_budget_total);
        $foodTotal = $this->moneyToFloat($answer->food_budget_total);
        $carTotal = $this->moneyToFloat($answer->car_budget_total);

        $baseTotal = $this->sumTotals($housingTotal, $entertainmentTotal, $foodTotal, $carTotal);
        if ($baseTotal === null) {
            return null;
        }
        $adjustment = $this->priorityAdjustmentService->adjustmentFor($answer, $baseTotal);
        $total = max(0.0, round($baseTotal + $adjustment, 2));

        $answer->forceFill([
            'housing_budget_total' => $housingTotal !== null ? $this->formatMoney($housingTotal) : null,
            'budget_base_total' => $this->formatMoney($baseTotal),
            'base_total' => $baseTotal,
            'budget_priority_adjustment_total' => $this->formatSignedMoney($adjustment),
            'total' => $total,
            'budget_total' => '$'.number_format($total, 0, '.', ' '),
        ])->save();

        return $total;
    }

    public function calculate(QuizAnswer $answer): ?float
    {
        $housingTotal = $this->housingTotal($answer);
        $entertainmentTotal = $this->moneyToFloat($answer->entertainment_budget_total);
        $foodTotal = $this->moneyToFloat($answer->food_budget_total);
        $carTotal = $this->moneyToFloat($answer->car_budget_total);

        $baseTotal = $this->sumTotals($housingTotal, $entertainmentTotal, $foodTotal, $carTotal);
        if ($baseTotal === null) {
            return null;
        }

        return max(0.0, round($baseTotal + $this->priorityAdjustmentService->adjustmentFor($answer, $baseTotal), 2));
    }

    private function sumTotals(?float ...$totals): ?float
    {
        $hasAny = false;
        $sum = 0.0;

        foreach ($totals as $total) {
            if ($total === null) {
                continue;
            }

            $hasAny = true;
            $sum += $total;
        }

        if (! $hasAny) {
            return null;
        }

        return round($sum, 2);
    }

    private function housingTotal(QuizAnswer $answer): ?float
    {
        return match ($answer->housing_type) {
            'oteli' => $this->hotelBudgetCalculator->calculate($answer),
            'apartamenty' => $this->apartmentBudgetCalculator->calculate($answer),
            'apartamenti' => $this->apartmentBudgetCalculator->calculate($answer),
            default => null,
        };
    }

    private function moneyToFloat(?string $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $normalized = preg_replace('/[^\d,.\-]+/u', '', $value) ?? '';
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

    private function formatMoney(float $value): string
    {
        return '$'.number_format($value, 0, '.', ' ');
    }

    private function formatSignedMoney(float $value): string
    {
        if (abs($value) < 0.01) {
            return '$0';
        }

        $prefix = $value > 0 ? '+$' : '-$';

        return $prefix.number_format(abs($value), 0, '.', ' ');
    }
}
