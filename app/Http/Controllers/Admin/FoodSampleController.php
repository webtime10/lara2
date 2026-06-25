<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FoodSampleGptClassificationService;
use App\Services\FoodImportsService;
use App\Services\FoodSamplesService;
use App\Support\SyncErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class FoodSampleController extends Controller
{
    public function generateRegion(
        string $slug,
        FoodImportsService $imports,
        FoodSamplesService $samples,
    ): JsonResponse {
        $region = $imports->findRegion($slug);
        if ($region === null) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $summary = $samples->buildForRegion($region);

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $region->label,
                'summary' => $summary,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'slug' => $slug,
                'label' => $region->label,
                'message' => SyncErrorMessage::format($e),
            ], 502);
        }
    }

    public function show(
        string $slug,
        Request $request,
        FoodImportsService $imports,
        FoodSamplesService $samples,
    ): View {
        $region = $imports->findRegion($slug);
        if ($region === null) {
            abort(404);
        }

        $error = null;

        try {
            $items = $samples->paginateRegionSamples($region, $request);
        } catch (\Throwable $e) {
            $items = new LengthAwarePaginator([], 0, 100);
            $error = $e->getMessage();
        }

        return view('admin.food-samples.show', [
            'pageTitle' => 'Выборка питания — '.$region->label,
            'region' => $region,
            'items' => $items,
            'limits' => FoodSamplesService::limits(),
            'error' => $error,
        ]);
    }

    public function classifyRegion(
        string $slug,
        FoodImportsService $imports,
        FoodSampleGptClassificationService $classifier,
    ): JsonResponse {
        $region = $imports->findRegion($slug);
        if ($region === null) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $result = $classifier->classifyNextBatch($region);

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $region->label,
                'processed' => $result['processed'],
                'remaining' => $result['remaining'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'slug' => $slug,
                'label' => $region->label,
                'message' => SyncErrorMessage::format($e),
            ], 502);
        }
    }
}
