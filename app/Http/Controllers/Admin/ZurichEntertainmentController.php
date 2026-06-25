<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ZurichEntertainmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZurichEntertainmentController extends Controller
{
    public function index(): View
    {
        return view('admin.test.zurich-entertainment', [
            'pageTitle' => 'Тест — Развлечения Цюриха',
            'locationCode' => ZurichEntertainmentService::LOCATION_CODE,
        ]);
    }

    public function fetch(Request $request, ZurichEntertainmentService $service): JsonResponse
    {
        set_time_limit(300);

        try {
            $allItems = $service->fetchAllItems();
            $paginator = $service->paginate($request);

            return response()->json([
                'success' => true,
                'message' => 'Найдено '.$paginator->total().' развлечений, страница '.$paginator->currentPage().' из '.$paginator->lastPage(),
                'items' => array_values($paginator->items()),
                'category_summary' => $service->categorySummary($allItems),
                'region_summary' => $service->regionSummary($allItems),
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'items' => [],
                'pagination' => null,
            ], 422);
        }
    }
}
