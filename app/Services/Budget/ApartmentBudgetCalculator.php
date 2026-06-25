<?php

namespace App\Services\Budget;

use App\Models\QuizAnswer;
use App\Models\SwissApartment;
use App\Models\SwissRegion;

class ApartmentBudgetCalculator
{
    /**
     * Считает стоимость проживания в апартаментах и записывает её в quiz_answers.budget_total.
     */
    public function applyTo(QuizAnswer $answer): ?float
    {
        $total = $this->calculate($answer);
        if ($total === null) {
            return null;
        }

        $answer->forceFill([
            'budget_total' => '$'.number_format($total, 0, '.', ' '),
        ])->save();

        return $total;
    }

    public function calculate(QuizAnswer $answer): ?float
    {
        if (! in_array($answer->housing_type, ['apartamenty', 'apartamenti'], true)) {
            return null;
        }

        $days = (int) ($answer->total_days ?? 0);
        if ($days <= 0) {
            return null;
        }

        $region = $this->region($answer);
        if ($region === null) {
            return null;
        }

        $level = $this->comfortLevel((string) $answer->comfort_level);
        $apartmentPrice = SwissApartment::query()
            ->where('region_id', $region->id)
            ->where('level', $level)
            ->avg('price_usd');

        if ($apartmentPrice === null || (float) $apartmentPrice <= 0.0) {
            return null;
        }

        return round((float) $apartmentPrice * $this->apartmentsCount($answer) * $days, 2);
    }

    private function region(QuizAnswer $answer): ?SwissRegion
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

    private function comfortLevel(string $comfortLevel): int
    {
        return match ($comfortLevel) {
            'deshevle' => 1,
            'visokii' => 3,
            default => 2,
        };
    }

    private function apartmentsCount(QuizAnswer $answer): int
    {
        $people = (int) ($answer->total_people ?? 0);
        if ($people <= 0) {
            $people = (int) $answer->travelers_count + (int) $answer->children_count;
        }

        // Один апартамент условно покрывает до 4 человек. Для больших групп берём несколько.
        return max(1, (int) ceil(max(1, $people) / 4));
    }
}
