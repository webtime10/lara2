<?php

namespace App\Http\Controllers\Api\Plugins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Plugins\BudgetIncomingRequest;
use App\Models\QuizAnswer;
use App\Services\BudgetPriorityAdjustmentService;
use App\Services\CarBudgetGeminiService;
use App\Services\Budget\TripBudgetTotalCalculator;
use App\Services\EntertainmentGeminiService;
use App\Services\FoodBudgetGeminiService;
use App\Services\Plugins\Budget\BudgetIngestService;
use App\Support\QuizAnswerMapper;
use Illuminate\Http\JsonResponse;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetIngestService $ingest,
        private TripBudgetTotalCalculator $tripBudgetTotalCalculator,
        private EntertainmentGeminiService $entertainmentGeminiService,
        private FoodBudgetGeminiService $foodBudgetGeminiService,
        private CarBudgetGeminiService $carBudgetGeminiService,
        private BudgetPriorityAdjustmentService $budgetPriorityAdjustmentService,
    ) {}

    /**
     * Точка входа: WordPress шлёт сюда POST /api/plugins/budget.
     */
    public function store(BudgetIncomingRequest $request): JsonResponse
    {
        // Здесь уже прошла валидация (BudgetIncomingRequest):
        // language, session_token, answers.catalog.* — структура из Vue-калькулятора (Budget.js).
        $validated = $request->validated();

        // Каждый запрос отправляем в AI (BudgetAiService) без кэша.
        // В $result прилетает массив вида:
        // ['ok' => bool, 'message' => string, 'budget' => [...], 'model' => ..., 'language' => ...].
        $result = $this->ingest->accept($validated);
// Запись в базу данных по маппингу
        // QuizAnswerMapper — отдельный класс (app/Support/QuizAnswerMapper.php),
        // который умеет превратить:
        //   - входящий payload из WP ($validated['answers']['catalog']),
        //   - ответ AI ($result['budget'], $result['model'], $result['ok'], ...)
        // в плоский массив полей таблицы quiz_answers (language, region, housing_type,
        // budget_total, budget_per_person, budget_summary, budget_rows и т.д.).
        //
        // Здесь мы один раз вызываем маппер и сохраняем полностью весь срез запроса
        // и ответа AI в базе (quiz_answers).
        // Запись в базу данных по маппингу
        $quizAnswer = QuizAnswer::create(
            QuizAnswerMapper::toAttributes($validated, $result)
        );

        try {
            $entertainment = $this->entertainmentGeminiService->runForAnswer($quizAnswer);
            if ($entertainment['amount'] !== null) {
                $quizAnswer->forceFill([
                    'entertainment_budget_total' => '$'.number_format($entertainment['amount'], 0, '.', ' '),
                ])->save();
                $quizAnswer->entertainment_budget_total = '$'.number_format($entertainment['amount'], 0, '.', ' ');
                $result['entertainment_budget_total'] = $quizAnswer->entertainment_budget_total;
            } else {
                $message = 'Развлечения: Gemini ответил, но число не найдено. Ответ: '.$entertainment['answer'];
                $result['entertainment_error'] = $message;
                $quizAnswer->forceFill([
                    'ai_message' => trim((string) $quizAnswer->ai_message."\n".$message),
                ])->save();
            }
        } catch (\Throwable $e) {
            $result['entertainment_error'] = $e->getMessage();
            $quizAnswer->forceFill([
                'ai_message' => trim((string) $quizAnswer->ai_message."\nРазвлечения: ".$e->getMessage()),
            ])->save();
        }

        try {
            $food = $this->foodBudgetGeminiService->runForAnswer($quizAnswer);
            $quizAnswer->forceFill([
                'food_budget_total' => '$'.number_format($food['amount'], 0, '.', ' '),
            ])->save();
            $quizAnswer->food_budget_total = '$'.number_format($food['amount'], 0, '.', ' ');
            $result['food_budget_total'] = $quizAnswer->food_budget_total;
        } catch (\Throwable $e) {
            $result['food_error'] = $e->getMessage();
            $quizAnswer->forceFill([
                'ai_message' => trim((string) $quizAnswer->ai_message."\nПитание: ".$e->getMessage()),
            ])->save();
        }

        try {
            $car = $this->carBudgetGeminiService->runForAnswer($quizAnswer);
            $quizAnswer->forceFill([
                'car_budget_total' => '$'.number_format($car['amount'], 0, '.', ' '),
            ])->save();
            $quizAnswer->car_budget_total = '$'.number_format($car['amount'], 0, '.', ' ');
            $result['car_budget_total'] = $quizAnswer->car_budget_total;
        } catch (\Throwable $e) {
            $result['car_error'] = $e->getMessage();
            $quizAnswer->forceFill([
                'ai_message' => trim((string) $quizAnswer->ai_message."\nАренда авто: ".$e->getMessage()),
            ])->save();
        }

        $total = $this->tripBudgetTotalCalculator->applyTo($quizAnswer);
        if ($total !== null) {
            $quizAnswer = $quizAnswer->fresh();
            if (! isset($result['budget']) || ! is_array($result['budget'])) {
                $result['budget'] = [];
            }
            $result['budget']['total'] = $quizAnswer->budget_total;
            $result['budget']['base_total'] = $quizAnswer->budget_base_total;
            $result['budget']['final_total'] = $quizAnswer->budget_total;
            $result['budget']['rows'] = [
                ['label' => 'Проживание', 'price' => $quizAnswer->housing_budget_total ?: '$0'],
                ['label' => 'Транспорт', 'price' => $quizAnswer->car_budget_total ?: '$0'],
                ['label' => 'Развлечения', 'price' => $quizAnswer->entertainment_budget_total ?: '$0'],
                ['label' => 'Питание', 'price' => $quizAnswer->food_budget_total ?: '$0'],
            ];
            $result['budget']['priority_adjustment'] = $this->priorityAdjustmentText($quizAnswer);
            $result['housing_budget_total'] = $quizAnswer->housing_budget_total;
            $result['entertainment_budget_total'] = $quizAnswer->entertainment_budget_total;
            $result['food_budget_total'] = $quizAnswer->food_budget_total;
            $result['car_budget_total'] = $quizAnswer->car_budget_total;
            $result['total'] = $quizAnswer->total;
            $result['base_total'] = $quizAnswer->base_total;
            $result['budget_base_total'] = $quizAnswer->budget_base_total;
            $result['budget_priority_adjustment_total'] = $quizAnswer->budget_priority_adjustment_total;
            $result['priority_adjustment'] = $this->priorityAdjustmentText($quizAnswer);
            $result['budget_total'] = $quizAnswer->budget_total;
            $result['item_total'] = $quizAnswer->budget_total;
        }

        // Добавляем в JSON-ответ ID записи из quiz_answers, чтобы WordPress
        // мог знать, какой именно запрос был сохранён.
        $result['quiz_answer_id'] = $quizAnswer->id;

        return response()->json($result, 200);
    }

    private function priorityAdjustmentText(QuizAnswer $quizAnswer): string
    {
        $percent = $this->budgetPriorityAdjustmentService->percentForAnswer($quizAnswer);
        $amount = trim((string) $quizAnswer->budget_priority_adjustment_total);
        $percentText = ($percent > 0 ? '+' : '').rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.').'%';

        return $percentText.'|'.($amount !== '' ? $amount : '$0');
    }
}
