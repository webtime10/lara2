<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoodSource;
use App\Models\SwissRegion;
use App\Services\FoodSourceGeminiPriceService;
use App\Support\SyncErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FoodSourceController extends Controller
{
    public function index(Request $request): View
    {
        $pageTitle = 'Бюджет — Цены питания';
        $regions = $this->regions();
        $foodTypes = FoodSource::typeLabels();

        $displayRegions = $regions;
        if ($request->filled('region_id')) {
            $displayRegions = $regions->where('id', (int) $request->input('region_id'))->values();
        }

        $sourcesByRegion = FoodSource::query()
            ->where('food_type', FoodSource::TYPE_HOME_COOKING)
            ->whereIn('region_id', $displayRegions->pluck('id'))
            ->orderBy('id')
            ->get()
            ->unique('region_id')
            ->keyBy('region_id');

        $foodRows = $displayRegions->map(fn (SwissRegion $region): array => [
            'region' => $region,
            'source' => $sourcesByRegion->get($region->id),
        ]);
        $aiModelLabels = FoodSourceGeminiPriceService::modelLabels();
        $defaultAiModel = FoodSourceGeminiPriceService::MODEL_GEMINI_FREE;

        return view('admin.food-sources.index', compact('pageTitle', 'foodRows', 'regions', 'foodTypes', 'aiModelLabels', 'defaultAiModel'));
    }

    public function create(): View
    {
        return view('admin.food-sources.create', [
            'pageTitle' => 'Продуктовая корзина — создание',
            'regions' => $this->regions(),
            'foodTypes' => FoodSource::typeLabels(),
            'source' => new FoodSource([
                'currency' => 'CHF',
                'food_type' => FoodSource::TYPE_HOME_COOKING,
            ]),
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
            'pageTitle' => 'Продуктовая корзина — редактирование',
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

    public function refreshAi(Request $request, FoodSource $foodSource, FoodSourceGeminiPriceService $gemini): RedirectResponse
    {
        try {
            $region = $foodSource->region;
            if (! $region) {
                return back()->with('error', 'Регион не найден');
            }

            $gemini->refreshRegion($region, $request->input('ai_model'));

            return back()->with('success', 'Цены обновлены через Gemini');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function refreshGeminiRegion(string $slug, Request $request, FoodSourceGeminiPriceService $gemini): JsonResponse|RedirectResponse
    {
        $region = SwissRegion::query()->where('slug', $slug)->first();
        if (! $region) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $result = $gemini->refreshRegion($region, $request->input('ai_model'));
            $source = $result['source'];
            if (! $request->expectsJson()) {
                return back()->with('success', 'Цены сохранены через выбранную модель: '.$region->label);
            }

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $region->label,
                'model' => $result['model'],
                'source_id' => $source->id,
                'prices_count' => collect($result['prices'])->filter(fn ($value) => $value !== null)->count(),
                'last_checked' => $source->last_checked?->format('d.m.Y H:i'),
            ]);
        } catch (\Throwable $e) {
            if (! $request->expectsJson()) {
                return back()->with('error', SyncErrorMessage::format($e));
            }

            return response()->json([
                'ok' => false,
                'slug' => $slug,
                'label' => $region->label,
                'message' => SyncErrorMessage::format($e),
            ], 502);
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
