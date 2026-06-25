<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoodSource;
use App\Models\SwissRegion;
use App\Services\FoodSourceAiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FoodSourceController extends Controller
{
    public function index(Request $request): View
    {
        $pageTitle = 'Бюджет — Источники питания';
        $regions = $this->regions();
        $foodTypes = FoodSource::typeLabels();

        $query = FoodSource::query()->with('region')->latest('id');

        if ($request->filled('region_id')) {
            $query->where('region_id', (int) $request->input('region_id'));
        }

        if ($request->filled('food_type')) {
            $query->where('food_type', (string) $request->input('food_type'));
        }

        $sources = $query->paginate(30)->withQueryString();

        return view('admin.food-sources.index', compact('pageTitle', 'sources', 'regions', 'foodTypes'));
    }

    public function create(): View
    {
        return view('admin.food-sources.create', [
            'pageTitle' => 'Источник питания — создание',
            'regions' => $this->regions(),
            'foodTypes' => FoodSource::typeLabels(),
            'source' => new FoodSource(['currency' => 'CHF']),
            'priceFields' => FoodSource::allPriceFields(),
            'restaurantFields' => FoodSource::RESTAURANT_PRICE_FIELDS,
            'groceryFields' => FoodSource::GROCERY_PRICE_FIELDS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->normalizePriceFields($data);

        FoodSource::query()->create($data);

        return redirect()->route('admin.food-sources.index')->with('success', 'Источник питания создан');
    }

    public function edit(FoodSource $foodSource): View
    {
        return view('admin.food-sources.edit', [
            'pageTitle' => 'Источник питания — редактирование',
            'regions' => $this->regions(),
            'foodTypes' => FoodSource::typeLabels(),
            'source' => $foodSource,
            'priceFields' => FoodSource::allPriceFields(),
            'restaurantFields' => FoodSource::RESTAURANT_PRICE_FIELDS,
            'groceryFields' => FoodSource::GROCERY_PRICE_FIELDS,
        ]);
    }

    public function update(Request $request, FoodSource $foodSource): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data = $this->normalizePriceFields($data);

        $foodSource->update($data);

        return redirect()->route('admin.food-sources.edit', $foodSource)->with('success', 'Источник питания сохранён');
    }

    public function destroy(FoodSource $foodSource): RedirectResponse
    {
        $foodSource->delete();

        return redirect()->route('admin.food-sources.index')->with('success', 'Источник питания удалён');
    }

    public function refreshAi(FoodSource $foodSource, FoodSourceAiService $ai): RedirectResponse
    {
        try {
            $ai->refreshPrices($foodSource);

            return back()->with('success', 'Цены обновлены через ChatGPT');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $rules = [
            'region_id' => ['required', 'integer', 'exists:swiss_regions,id'],
            'name' => ['required', 'string', 'max:255'],
            'website' => ['nullable', 'string'],
            'food_type' => ['required', 'string', Rule::in(FoodSource::foodTypes())],
            'currency' => ['nullable', 'string', 'max:10'],
        ];

        foreach (FoodSource::allPriceFields() as $field) {
            $rules[$field] = ['nullable', 'numeric', 'min:0'];
        }

        return $request->validate($rules);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePriceFields(array $data): array
    {
        $foodType = (string) ($data['food_type'] ?? '');
        $active = array_flip(FoodSource::activePriceFieldsForType($foodType));

        foreach (FoodSource::allPriceFields() as $field) {
            if (! isset($active[$field])) {
                $data[$field] = null;
                continue;
            }

            $data[$field] = ($data[$field] ?? '') === '' ? null : round((float) $data[$field], 2);
        }

        $data['currency'] = trim((string) ($data['currency'] ?? '')) ?: 'CHF';
        $data['website'] = trim((string) ($data['website'] ?? '')) ?: null;

        return $data;
    }

    /** @return \Illuminate\Support\Collection<int, SwissRegion> */
    private function regions()
    {
        return SwissRegion::query()->orderBy('label')->get();
    }
}
