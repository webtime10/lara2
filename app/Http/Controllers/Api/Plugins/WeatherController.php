<?php

namespace App\Http\Controllers\Api\Plugins;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Plugins\WeatherIncomingRequest;
use App\Services\Plugins\Weather\WeatherIngestService;
use Illuminate\Http\JsonResponse;

class WeatherController extends Controller
{
    public function __construct(
        private WeatherIngestService $ingest
    ) {}

    public function store(WeatherIncomingRequest $request): JsonResponse
    {
        $result = $this->ingest->accept($request->validated());

        return response()->json($result, 200);
    }
}
