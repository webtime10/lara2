<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ZurichRestaurantsService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ZurichRestaurantsController extends Controller
{
    public function index(): View
    {
        return view('admin.test.zurich-restaurants', [
            'pageTitle' => 'Тест — Рестораны Цюриха',
            'locationCode' => ZurichRestaurantsService::LOCATION_CODE,
        ]);
    }

    public function fetch(ZurichRestaurantsService $service): JsonResponse
    {
        set_time_limit(300);

        try {
            $items = $service->fetchAndSave();

            return response()->json([
                'success' => true,
                'message' => 'Сохранено '.count($items).' ресторанов в food_imports',
                'items' => $items,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'items' => [],
            ], 422);
        }
    }
}
