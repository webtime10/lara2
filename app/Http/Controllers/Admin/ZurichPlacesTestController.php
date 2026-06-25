<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ZurichPlacesRawService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ZurichPlacesTestController extends Controller
{
    public function index(Request $request, ZurichPlacesRawService $service): View
    {
        try {
            $entertainment = $service->paginateEntertainment($request);
            $food = $service->paginateFood($request);
            $error = null;
        } catch (\Throwable $e) {
            $entertainment = new LengthAwarePaginator([], 0, 100, 1, ['pageName' => 'entertainment_page']);
            $food = new LengthAwarePaginator([], 0, 100, 1, ['pageName' => 'food_page']);
            $error = $e->getMessage();
        }

        return view('admin.test.zurich-places', [
            'pageTitle' => 'Тест — Places Цюрих (сырые данные)',
            'entertainment' => $entertainment,
            'food' => $food,
            'error' => $error,
            'locationCode' => ZurichPlacesRawService::LOCATION_CODE,
            'placesService' => $service,
        ]);
    }
}
