<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SwissHotelsService;
use App\Support\HotelOccupancyCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetHotelSettingsController extends Controller
{
    public function edit(SwissHotelsService $hotels): View
    {
        $selected = array_fill_keys($hotels->selectedOccupancyKeys(), true);

        return view('admin.budget.hotels.settings', [
            'pageTitle' => 'Отели — настройки',
            'groups' => HotelOccupancyCatalog::grouped(),
            'ageBands' => HotelOccupancyCatalog::AGE_BANDS,
            'selected' => $selected,
            'selectedCount' => count($selected),
            'catalogCount' => count(HotelOccupancyCatalog::all()),
        ]);
    }

    public function update(Request $request, SwissHotelsService $hotels): RedirectResponse
    {
        $keys = $request->input('keys', []);
        if (! is_array($keys)) {
            $keys = [];
        }

        $saved = $hotels->saveOccupancySelection($keys);

        return redirect()
            ->route('admin.budget.hotels.settings')
            ->with('success', 'Сохранено ячеек: '.count($saved).'. Они появятся в карточках отелей по кантонам.');
    }
}
