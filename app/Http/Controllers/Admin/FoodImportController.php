<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FoodImportGptClassificationService;
use App\Services\FoodImportsService;
use App\Support\SyncErrorMessage;
use App\Models\FoodImport;
use App\Models\FoodSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class FoodImportController extends Controller
{
    public function index(FoodImportsService $imports): View
    {
        return view('admin.food-imports.index', [
            'pageTitle' => 'Бюджет — Кафе и рестораны',
            'regions' => $imports->regions(),
            'keywords' => FoodImportsService::KEYWORDS,
        ]);
    }

    public function clearAll(): JsonResponse
    {
        $samplesCount = FoodSample::query()->count();
        $count = FoodImport::query()->count();
        FoodSample::query()->delete();
        FoodImport::query()->delete();

        return response()->json([
            'ok' => true,
            'deleted' => $count,
            'samples_deleted' => $samplesCount,
        ]);
    }

    public function clearRegion(string $slug, FoodImportsService $imports): JsonResponse
    {
        $region = $imports->findRegion($slug);
        if ($region === null) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        $samplesCount = FoodSample::query()->where('region_id', $region->id)->count();
        $count = FoodImport::query()->where('region_id', $region->id)->count();
        FoodSample::query()->where('region_id', $region->id)->delete();
        FoodImport::query()->where('region_id', $region->id)->delete();

        return response()->json([
            'ok' => true,
            'slug' => $region->slug,
            'label' => $region->label,
            'deleted' => $count,
            'samples_deleted' => $samplesCount,
        ]);
    }

    public function syncRegion(string $slug, FoodImportsService $imports): JsonResponse
    {
        $region = $imports->findRegion($slug);
        if ($region === null) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $result = $imports->importRegion($slug);

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $result['region'],
                'count' => $result['saved'],
                'total' => $region->foodImports()->count(),
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

    public function classifyRegion(
        string $slug,
        FoodImportsService $imports,
        FoodImportGptClassificationService $classifier,
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

    public function show(string $slug, Request $request, FoodImportsService $imports): View
    {
        $region = $imports->findRegion($slug);
        if ($region === null) {
            abort(404);
        }

        $error = null;

        try {
            $items = $imports->paginateRegionImports($slug, $request);
        } catch (\Throwable $e) {
            $items = new LengthAwarePaginator([], 0, 100);
            $error = $e->getMessage();
        }

        return view('admin.food-imports.show', [
            'pageTitle' => 'Кафе и рестораны — '.$region->label,
            'region' => $region,
            'items' => $items,
            'keywords' => FoodImportsService::KEYWORDS,
            'error' => $error,
        ]);
    }
}
