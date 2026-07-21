<?php

namespace App\Http\Controllers\Api\Plugins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Plugins\IdealRegionIncomingRequest;
use App\Services\Plugins\IdealRegion\IdealRegionMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class IdealRegionController extends Controller
{
    public function __construct(
        private IdealRegionMatchService $matchService,
    ) {}

    /**
     * Принимает ответы квиза «Ваш идеальный регион» и возвращает регионы с оценками по слотам.
     */
    public function store(IdealRegionIncomingRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $result = $this->matchService->match($payload);

        Log::info('ideal_region.incoming', [
            'language' => $payload['language'] ?? null,
            'session_token' => $payload['session_token'] ?? null,
            'catalog_keys' => array_keys($payload['answers']['catalog'] ?? []),
            'best_match' => $result['best_match']['name'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'plugin' => 'ideal_region',
            'status' => 'matched',
            'message' => 'Ideal region answers processed.',
            'received' => $payload,
            'step_slots' => $this->matchService->stepSlots(),
            'result' => $result,
        ], 200);
    }
}
