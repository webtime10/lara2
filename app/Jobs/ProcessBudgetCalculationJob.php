<?php

namespace App\Jobs;

use App\Models\QuizAnswer;
use App\Services\Budget\BudgetCalculationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessBudgetCalculationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        private readonly int $quizAnswerId,
    ) {}

    public function handle(BudgetCalculationService $calculationService): void
    {
        $quizAnswer = QuizAnswer::query()->find($this->quizAnswerId);
        if (! $quizAnswer) {
            return;
        }

        $calculationService->calculate($quizAnswer);
    }
}
