<?php

namespace App\Http\Controllers\Api\Plugins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Plugins\BudgetIncomingRequest;
use App\Jobs\ProcessBudgetCalculationJob;
use App\Models\QuizAnswer;
use App\Services\Budget\BudgetCalculationService;
use App\Support\QuizAnswerMapper;
use Illuminate\Http\JsonResponse;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetCalculationService $calculationService,
    ) {}

    /**
     * Точка входа: WordPress шлёт сюда POST /api/plugins/budget.
     */
    public function store(BudgetIncomingRequest $request): JsonResponse
    {
        // Здесь уже прошла валидация (BudgetIncomingRequest):
        // language, session_token, answers.catalog.* — структура из Vue-калькулятора (Budget.js).
        $validated = $request->validated();

        $result = [
            'ok' => true,
            'message' => 'Budget calculation queued.',
            'language' => $validated['language'] ?? null,
        ];
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

        $quizAnswer->forceFill(['calculation_status' => 'pending'])->save();
        ProcessBudgetCalculationJob::dispatch($quizAnswer->id);

        return response()->json([
            'ok' => true,
            'status' => 'processing',
            'message' => 'Budget calculation queued.',
            'quiz_answer_id' => $quizAnswer->id,
        ], 202);
    }

    public function status(int $quizAnswerId): JsonResponse
    {
        $quizAnswer = QuizAnswer::query()->find($quizAnswerId);
        if (! $quizAnswer) {
            return response()->json([
                'ok' => false,
                'status' => 'not_found',
                'message' => 'Budget calculation not found.',
            ], 404);
        }

        return response()->json($this->calculationService->responseFor($quizAnswer), 200);
    }
}
