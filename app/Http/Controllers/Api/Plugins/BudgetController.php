<?php

namespace App\Http\Controllers\Api\Plugins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Plugins\BudgetIncomingRequest;
use App\Models\QuizAnswer;
use App\Services\Plugins\Budget\BudgetIngestService;
use Illuminate\Http\JsonResponse;

class BudgetController extends Controller
{
    public function __construct(
        private BudgetIngestService $ingest
    ) {}

    public function store(BudgetIncomingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Гибрид: индексы для статистики, остальное — в JSON payload.
        $answers = is_array($validated['answers'] ?? null) ? $validated['answers'] : [];
        $catalog = is_array($answers['catalog'] ?? null) ? $answers['catalog'] : [];

        $region = trim((string) ($catalog['region']['region'] ?? ''));
        $travelersCount = (int) ($catalog['travelers']['quantity'] ?? 0);
        $travelersCount = max(0, min(255, $travelersCount));

        $quizAnswer = QuizAnswer::create([
            'session_token' => isset($validated['session_token']) ? trim((string) $validated['session_token']) : null,
            'region' => $region !== '' ? $region : null,
            'travelers_count' => $travelersCount,
            'payload' => [
                'language' => $validated['language'],
                'catalog' => $catalog,
            ],
        ]);

        $result = $this->ingest->accept($validated);
        $result['quiz_answer_id'] = $quizAnswer->id;

        return response()->json($result, 200);
    }
}
