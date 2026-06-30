<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EntertainmentVisitPrice;
use App\Models\SwissEntertainment;
use App\Models\SwissRegion;
use App\Services\EntertainmentVisitPriceAiService;
use App\Support\EntertainmentCategory;
use App\Support\SyncErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class EntertainmentVisitPriceController extends Controller
{
    public function index(): View
    {
        $regions = SwissRegion::query()->orderBy('label')->get();
        $pricesTableExists = Schema::hasTable('entertainment_visit_prices');
        $prices = $pricesTableExists
            ? EntertainmentVisitPrice::query()
                ->get()
                ->groupBy('region_id')
                ->map(fn ($items) => $items->keyBy('category'))
            : collect();

        $counts = SwissEntertainment::query()
            ->selectRaw('region_id, category, COUNT(*) AS count_items')
            ->groupBy('region_id', 'category')
            ->get()
            ->groupBy('region_id')
            ->map(fn ($items) => $items->keyBy('category'));

        $rows = $regions->map(fn (SwissRegion $region): array => [
            'region' => $region,
            'prices' => $prices->get($region->id, collect()),
            'counts' => $counts->get($region->id, collect()),
        ]);
        $regionsPayload = $rows->map(fn (array $row): array => [
            'slug' => $row['region']->slug,
            'label' => $row['region']->label,
            'url' => route('admin.entertainment-visit-prices.refresh', $row['region']->slug),
        ])->values();

        return view('admin.entertainment-visit-prices.index', [
            'pageTitle' => 'Бюджет — Цены развлечений',
            'rows' => $rows,
            'categories' => EntertainmentCategory::ALL,
            'pricesTableExists' => $pricesTableExists,
            'regionsPayload' => $regionsPayload,
            'aiModelLabels' => EntertainmentVisitPriceAiService::modelLabels(),
            'defaultAiModel' => EntertainmentVisitPriceAiService::defaultModel(),
        ]);
    }

    public function refresh(string $slug, Request $request, EntertainmentVisitPriceAiService $ai): JsonResponse
    {
        if (! Schema::hasTable('entertainment_visit_prices')) {
            return response()->json([
                'ok' => false,
                'message' => 'Таблица entertainment_visit_prices ещё не создана. Запустите миграции Laravel.',
            ], 409);
        }

        $region = SwissRegion::query()->where('slug', $slug)->first();
        if (! $region) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $result = $ai->refreshRegion($region, $request->input('ai_model'));

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $region->label,
                'model' => $result['model'],
                'places_count' => $result['places_count'],
                'prices' => collect($result['prices'])->map(fn (EntertainmentVisitPrice $price): array => [
                    'category' => $price->category,
                    'adult_avg_price' => $price->adult_avg_price,
                    'child_avg_price' => $price->child_avg_price,
                    'currency' => $price->currency,
                    'last_checked' => $price->last_checked?->format('d.m.Y H:i'),
                ])->values()->all(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'slug' => $slug,
                'label' => $region->label,
                'message' => SyncErrorMessage::format($e),
            ], 502);
        }
    }

    public function save(Request $request): RedirectResponse
    {
        if (! Schema::hasTable('entertainment_visit_prices')) {
            return redirect()
                ->route('admin.entertainment-visit-prices.index')
                ->with('error', 'Таблица entertainment_visit_prices ещё не создана. Запустите миграции Laravel.');
        }

        $items = (array) $request->input('prices', []);

        foreach ($items as $regionId => $categories) {
            foreach ((array) $categories as $category => $row) {
                if (! in_array($category, EntertainmentCategory::ALL, true)) {
                    continue;
                }

                $adult = $this->nullableMoney($row['adult'] ?? null);
                $child = $this->nullableMoney($row['child'] ?? null);

                if ($adult === null && $child === null) {
                    EntertainmentVisitPrice::query()
                        ->where('region_id', (int) $regionId)
                        ->where('category', $category)
                        ->delete();
                    continue;
                }

                EntertainmentVisitPrice::query()->updateOrCreate(
                    [
                        'region_id' => (int) $regionId,
                        'category' => $category,
                    ],
                    [
                        'adult_avg_price' => $adult,
                        'child_avg_price' => $child,
                        'currency' => 'USD',
                        'places_count' => (int) ($row['places_count'] ?? 0),
                        'last_checked' => now(),
                    ]
                );
            }
        }

        return redirect()
            ->route('admin.entertainment-visit-prices.index')
            ->with('success', 'Цены развлечений сохранены.');
    }

    private function nullableMoney(mixed $value): ?float
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
