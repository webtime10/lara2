<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarRentalPrice;
use App\Models\SwissRegion;
use App\Services\CarRentalPriceAiService;
use App\Support\SyncErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarRentalPriceController extends Controller
{
    public function index(): View
    {
        $regions = SwissRegion::query()->orderBy('label')->get();
        $prices = CarRentalPrice::query()
            ->get()
            ->groupBy('region_id')
            ->map(fn ($items) => $items->keyBy('car_class'));

        $rows = $regions->map(fn (SwissRegion $region): array => [
            'region' => $region,
            'prices' => $prices->get($region->id, collect()),
        ]);
        $regionsPayload = $rows->map(fn (array $row): array => [
            'slug' => $row['region']->slug,
            'label' => $row['region']->label,
            'url' => route('admin.car-rental-prices.refresh', $row['region']->slug),
        ])->values();

        return view('admin.car-rental-prices.index', [
            'pageTitle' => 'Бюджет — Цены авто',
            'rows' => $rows,
            'regionsPayload' => $regionsPayload,
            'carClasses' => CarRentalPrice::classes(),
            'carClassLabels' => CarRentalPrice::classLabels(),
            'aiModelLabels' => CarRentalPriceAiService::modelLabels(),
            'defaultAiModel' => CarRentalPriceAiService::defaultModel(),
        ]);
    }

    public function refresh(string $slug, Request $request, CarRentalPriceAiService $ai): JsonResponse
    {
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
                'prices' => collect($result['prices'])->map(fn (CarRentalPrice $price): array => [
                    'car_class' => $price->car_class,
                    'daily_price' => $price->daily_price,
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

    public function clearAll(): JsonResponse
    {
        $deleted = CarRentalPrice::query()->count();
        CarRentalPrice::query()->delete();

        return response()->json([
            'ok' => true,
            'deleted' => $deleted,
        ]);
    }
}
