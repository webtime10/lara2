<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SwissHousingAverageService;
use Illuminate\View\View;

class HotelAveragePriceController extends Controller
{
    public function index(SwissHousingAverageService $averages): View
    {
        return view('admin.hotel-average-prices.index', [
            'pageTitle' => 'Бюджет — Средние цены отелей',
            'rows' => $averages->hotelRows(),
            'levels' => SwissHousingAverageService::LEVELS,
            'levelLabels' => SwissHousingAverageService::LEVEL_LABELS,
        ]);
    }
}
