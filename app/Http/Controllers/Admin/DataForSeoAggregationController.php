<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DfsAggRun;
use App\Services\BusinessListings\CategoriesAggregationOnlyService;
use App\Services\DataForSeoClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DataForSeoAggregationController extends Controller
{
    public function index(CategoriesAggregationOnlyService $service): View
    {
        $categories = $service->resolveTouristCategories();
        $latestRun = DfsAggRun::query()
            ->with(['categories' => fn ($q) => $q->orderByDesc('objects_count')])
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->first();

        $history = DfsAggRun::query()
            ->orderByDesc('collected_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return view('admin.dataforseo-aggregation.index', [
            'pageTitle' => 'DataForSEO Categories Aggregation',
            'destination' => (string) config('bern_tourist.destination_label', 'Canton of Bern'),
            'locationCode' => (int) config('bern_tourist.synthetic_location_code', 9100029),
            'locationCoordinate' => (string) config('bern_tourist.location_coordinate'),
            'endpoint' => DataForSeoClient::BUSINESS_LISTINGS_CATEGORIES_AGGREGATION_URL,
            'selectedCategories' => $categories,
            'selectedCategoriesCount' => count($categories),
            'plannedApiRequests' => $service->plannedApiRequests($categories),
            'latestRun' => $latestRun,
            'history' => $history,
        ]);
    }

    public function fetch(Request $request, CategoriesAggregationOnlyService $service): JsonResponse
    {
        if (! $request->boolean('confirmed')) {
            return response()->json([
                'ok' => false,
                'message' => 'Подтвердите выполнение: будет выполнен только Categories Aggregation API. Business Listings и POI не загружаются.',
            ], 422);
        }

        try {
            $result = $service->fetchAggregation();
            $run = $result['run'];

            return response()->json([
                'ok' => true,
                'run_id' => $run->id,
                'redirect' => route('admin.dataforseo-aggregation.show', $run),
                'stats' => [
                    'endpoint' => $run->endpoint,
                    'location_code' => $run->location_code,
                    'api_requests' => $result['api_requests'],
                    'planned_requests' => $result['planned_requests'],
                    'categories_processed' => $run->categories_processed,
                    'total_objects_reported' => $result['total_objects_reported'],
                    'api_cost' => $result['api_cost'],
                    'execution_time_ms' => $result['execution_time_ms'],
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }

    public function show(DfsAggRun $run): View
    {
        $run->load(['categories' => fn ($q) => $q->orderByDesc('objects_count')->orderBy('category_code')]);

        return view('admin.dataforseo-aggregation.show', [
            'pageTitle' => 'Aggregation run #'.$run->id,
            'run' => $run,
        ]);
    }
}
