<?php

namespace App\Services\Budget;

use App\Models\QuizAnswer;
use App\Services\BudgetPriorityAdjustmentService;
use App\Services\CarBudgetGeminiService;
use App\Services\EntertainmentGeminiService;
use App\Services\FoodBudgetGeminiService;

class BudgetCalculationService
{
    public function __construct(
        private readonly TripBudgetTotalCalculator $tripBudgetTotalCalculator,
        private readonly EntertainmentGeminiService $entertainmentGeminiService,
        private readonly FoodBudgetGeminiService $foodBudgetGeminiService,
        private readonly CarBudgetGeminiService $carBudgetGeminiService,
        private readonly BudgetPriorityAdjustmentService $budgetPriorityAdjustmentService,
    ) {}

    public function calculate(QuizAnswer $quizAnswer): QuizAnswer
    {
        $quizAnswer->forceFill([
            'calculation_status' => 'processing',
            'calculation_error' => null,
            'calculation_started_at' => now(),
        ])->save();

        try {
            $this->calculateEntertainment($quizAnswer);
            $this->calculateFood($quizAnswer);
            $this->calculateCar($quizAnswer);
            $this->tripBudgetTotalCalculator->applyTo($quizAnswer);

            $quizAnswer->forceFill([
                'calculation_status' => 'completed',
                'calculation_completed_at' => now(),
            ])->save();
        } catch (\Throwable $e) {
            $quizAnswer->forceFill([
                'calculation_status' => 'failed',
                'calculation_error' => $e->getMessage(),
                'ai_message' => trim((string) $quizAnswer->ai_message."\nРасчёт бюджета: ".$e->getMessage()),
                'calculation_completed_at' => now(),
            ])->save();
        }

        return $quizAnswer->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function responseFor(QuizAnswer $quizAnswer): array
    {
        $quizAnswer = $quizAnswer->fresh();
        $status = (string) ($quizAnswer->calculation_status ?: 'completed');
        $budget = [
            'total' => $quizAnswer->budget_total,
            'base_total' => $quizAnswer->budget_base_total,
            'final_total' => $quizAnswer->budget_total,
            'per_person' => $this->perPersonText($quizAnswer),
            'rows' => [
                ['label' => 'Проживание', 'price' => $quizAnswer->housing_budget_total ?: '$0'],
                ['label' => 'Транспорт', 'price' => $quizAnswer->car_budget_total ?: '$0'],
                ['label' => 'Развлечения', 'price' => $quizAnswer->entertainment_budget_total ?: '$0'],
                ['label' => 'Питание', 'price' => $quizAnswer->food_budget_total ?: '$0'],
            ],
            'priority_adjustment' => $this->priorityAdjustmentText($quizAnswer),
        ];

        return [
            'ok' => $status !== 'failed',
            'status' => $status,
            'message' => $status === 'completed' ? 'Budget calculation completed.' : 'Budget calculation is processing.',
            'quiz_answer_id' => $quizAnswer->id,
            'budget' => $budget,
            'housing_budget_total' => $quizAnswer->housing_budget_total,
            'entertainment_budget_total' => $quizAnswer->entertainment_budget_total,
            'food_budget_total' => $quizAnswer->food_budget_total,
            'car_budget_total' => $quizAnswer->car_budget_total,
            'total' => $quizAnswer->total,
            'base_total' => $quizAnswer->base_total,
            'budget_base_total' => $quizAnswer->budget_base_total,
            'budget_priority_adjustment_total' => $quizAnswer->budget_priority_adjustment_total,
            'priority_adjustment' => $this->priorityAdjustmentText($quizAnswer),
            'budget_total' => $quizAnswer->budget_total,
            'item_total' => $quizAnswer->budget_total,
            'calculation_error' => $quizAnswer->calculation_error,
        ];
    }

    private function calculateEntertainment(QuizAnswer $quizAnswer): void
    {
        try {
            $entertainment = $this->entertainmentGeminiService->runForAnswer($quizAnswer);
            if ($entertainment['amount'] !== null) {
                $quizAnswer->forceFill([
                    'entertainment_budget_total' => '$'.number_format($entertainment['amount'], 0, '.', ' '),
                ])->save();

                return;
            }

            $message = 'Развлечения: Gemini ответил, но число не найдено. Ответ: '.$entertainment['answer'];
            $quizAnswer->forceFill([
                'ai_message' => trim((string) $quizAnswer->ai_message."\n".$message),
            ])->save();
        } catch (\Throwable $e) {
            $quizAnswer->forceFill([
                'ai_message' => trim((string) $quizAnswer->ai_message."\nРазвлечения: ".$e->getMessage()),
            ])->save();
        }
    }

    private function calculateFood(QuizAnswer $quizAnswer): void
    {
        try {
            $food = $this->foodBudgetGeminiService->runForAnswer($quizAnswer);
            $quizAnswer->forceFill([
                'food_budget_total' => '$'.number_format($food['amount'], 0, '.', ' '),
            ])->save();
        } catch (\Throwable $e) {
            $quizAnswer->forceFill([
                'ai_message' => trim((string) $quizAnswer->ai_message."\nПитание: ".$e->getMessage()),
            ])->save();
        }
    }

    private function calculateCar(QuizAnswer $quizAnswer): void
    {
        try {
            $car = $this->carBudgetGeminiService->runForAnswer($quizAnswer);
            $quizAnswer->forceFill([
                'car_budget_total' => '$'.number_format($car['amount'], 0, '.', ' '),
            ])->save();
        } catch (\Throwable $e) {
            $quizAnswer->forceFill([
                'ai_message' => trim((string) $quizAnswer->ai_message."\nАренда авто: ".$e->getMessage()),
            ])->save();
        }
    }

    private function priorityAdjustmentText(QuizAnswer $quizAnswer): string
    {
        $percent = $this->budgetPriorityAdjustmentService->percentForAnswer($quizAnswer);
        $amount = trim((string) $quizAnswer->budget_priority_adjustment_total);
        $percentText = ($percent > 0 ? '+' : '').rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.').'%';

        return $percentText.'|'.($amount !== '' ? $amount : '$0');
    }

    private function perPersonText(QuizAnswer $quizAnswer): string
    {
        $people = max(1, (int) $quizAnswer->total_people);
        $total = $quizAnswer->total !== null ? (float) $quizAnswer->total : 0.0;

        if ($total <= 0) {
            return (string) ($quizAnswer->budget_per_person ?: '');
        }

        return '$'.number_format($total / $people, 0, '.', ' ').' на человека';
    }
}
