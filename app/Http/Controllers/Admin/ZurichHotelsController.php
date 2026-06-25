<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ZurichHotelsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class ZurichHotelsController extends Controller
{
    public function index(Request $request, ZurichHotelsService $service): View
    {
        try {
            $items = $service->paginate($request);
            $error = null;
        } catch (\Throwable $e) {
            $items = new LengthAwarePaginator([], 0, 40);
            $error = $e->getMessage();
        }

        return view('admin.test.zurich-hotels', [
            'pageTitle' => 'Тест — Отели Цюриха',
            'items' => $items,
            'error' => $error,
        ]);
    }
}
