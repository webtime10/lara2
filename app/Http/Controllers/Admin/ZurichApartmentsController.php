<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ZurichAccommodationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZurichApartmentsController extends Controller
{
    public function index(): View
    {
        return view('admin.test.zurich-apartments', [
            'pageTitle' => 'Тест — Апартаменты Цюриха',
        ]);
    }

    public function fetch(Request $request, ZurichAccommodationService $service): JsonResponse
    {
        try {
            $paginator = $service->paginateApartments($request);

            return response()->json([
                'success' => true,
                'message' => 'Найдено '.$paginator->total().', страница '.$paginator->currentPage().' из '.$paginator->lastPage(),
                'items' => array_values($paginator->items()),
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
