<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FoodImport;
use App\Models\FoodSample;
use App\Models\FoodVisitPrice;
use App\Models\SwissRegion;
use App\Services\FoodVisitPriceAiService;
use App\Support\SyncErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FoodVisitPriceController extends Controller
{
    public function cafes(): View
    {
        return $this->index(FoodVisitPrice::TYPE_CAFE);
    }

    public function restaurants(): View
    {
        return $this->index(FoodVisitPrice::TYPE_RESTAURANT);
    }

    public function refresh(string $type, string $slug, Request $request, FoodVisitPriceAiService $ai): JsonResponse
    {
        $type = $this->normalizeType($type);
        $region = SwissRegion::query()->where('slug', $slug)->first();
        if (! $region) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $result = $ai->refreshRegion($region, $type, $request->input('ai_model'));
            $price = $result['price'];

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $region->label,
                'type' => $type,
                'model' => $result['model'],
                'places_count' => $result['places_count'],
                'adult_avg_price' => $price->adult_avg_price,
                'child_avg_price' => $price->child_avg_price,
                'currency' => $price->currency,
                'last_checked' => $price->last_checked?->format('d.m.Y H:i'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'slug' => $slug,
                'label' => $region->label,
                'type' => $type,
                'message' => SyncErrorMessage::format($e),
            ], 502);
        }
    }

    private function index(string $type): View
    {
        $type = $this->normalizeType($type);
        $regions = SwissRegion::query()->orderBy('label')->get();
        $prices = FoodVisitPrice::query()
            ->where('food_type', $type)
            ->get()
            ->keyBy('region_id');

        $rows = $regions->map(fn (SwissRegion $region): array => [
            'region' => $region,
            'price' => $prices->get($region->id),
            'places_count' => $this->placesCount($region, $type),
        ]);
        $regionsPayload = $rows->map(fn (array $row): array => [
            'slug' => $row['region']->slug,
            'label' => $row['region']->label,
            'url' => route('admin.food-visit-prices.refresh', [$type, $row['region']->slug]),
        ])->values();

        $typeLabels = FoodVisitPrice::typeLabels();

        return view('admin.food-visit-prices.index', [
            'pageTitle' => 'Бюджет — Цены '.$typeLabels[$type],
            'type' => $type,
            'typeLabel' => $typeLabels[$type],
            'rows' => $rows,
            'regionsPayload' => $regionsPayload,
            'aiModelLabels' => FoodVisitPriceAiService::modelLabels(),
            'defaultAiModel' => FoodVisitPriceAiService::defaultModel(),
        ]);
    }

    private function normalizeType(string $type): string
    {
        return match ($type) {
            FoodVisitPrice::TYPE_CAFE => FoodVisitPrice::TYPE_CAFE,
            FoodVisitPrice::TYPE_RESTAURANT => FoodVisitPrice::TYPE_RESTAURANT,
            default => abort(404),
        };
    }

    private function placesCount(SwissRegion $region, string $type): int
    {
        $samples = FoodSample::query()
            ->where('region_id', $region->id)
            ->where('food_type', $type)
            ->count();

        if ($samples > 0) {
            return $samples;
        }

        return FoodImport::query()
            ->where('region_id', $region->id)
            ->where('food_type', $type)
            ->count();
    }
}
