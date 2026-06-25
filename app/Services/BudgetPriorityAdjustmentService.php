<?php

namespace App\Services;

use App\Models\BudgetPromt;
use App\Models\QuizAnswer;

class BudgetPriorityAdjustmentService
{
    public const STRICT_PROMPT = 'budget_priority_strict_percent';

    public const BALANCE_PROMPT = 'budget_priority_balance_percent';

    public const RELAX_PROMPT = 'budget_priority_relax_percent';

    public const DEFAULTS = [
        self::STRICT_PROMPT => '-20%',
        self::BALANCE_PROMPT => '0',
        self::RELAX_PROMPT => '+20%',
    ];

    public function adjustmentFor(QuizAnswer $answer, float $baseTotal): float
    {
        if ($baseTotal <= 0) {
            return 0.0;
        }

        $percent = $this->percentForAnswer($answer);
        $adjustment = round($baseTotal * $percent / 100, 2);

        if ($baseTotal + $adjustment < 0) {
            return -1 * $baseTotal;
        }

        return $adjustment;
    }

    public function percentForAnswer(QuizAnswer $answer): float
    {
        return $this->percentValue($this->promptNameForPriority($answer->budget_priority));
    }

    public function percentValue(string $name): float
    {
        $raw = BudgetPromt::query()->where('name', $name)->value('content');
        $raw = is_string($raw) && trim($raw) !== '' ? $raw : (self::DEFAULTS[$name] ?? '0');

        return $this->parsePercent($raw);
    }

    private function promptNameForPriority(?string $priority): string
    {
        $priority = mb_strtolower(trim((string) $priority));

        if (str_contains($priority, 'vashnee') || str_contains($priority, 'vazhnee') || str_contains($priority, 'strict')) {
            return self::STRICT_PROMPT;
        }

        if (str_contains($priority, 'ne_vagen') || str_contains($priority, 'ne_vazhen') || str_contains($priority, 'relax')) {
            return self::RELAX_PROMPT;
        }

        return self::BALANCE_PROMPT;
    }

    private function parsePercent(string $value): float
    {
        $normalized = trim($value);
        $normalized = str_replace('%', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
        $normalized = preg_replace('/[^\d+.\-]+/u', '', $normalized) ?? '';

        return is_numeric($normalized) ? (float) $normalized : 0.0;
    }
}
