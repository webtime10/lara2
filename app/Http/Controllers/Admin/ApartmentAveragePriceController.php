<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SwissHousingAverageService;
use Illuminate\View\View;

class ApartmentAveragePriceController extends Controller
{
    public function index(SwissHousingAverageService $averages): View
    {
        return view('admin.apartment-average-prices.index', [
            'pageTitle' => 'Бюджет — Средние цены апартаментов',
            'rows' => $averages->apartmentRows(),
            'levels' => SwissHousingAverageService::LEVELS,
            'levelLabels' => SwissHousingAverageService::LEVEL_LABELS,
        ]);
    }
}
