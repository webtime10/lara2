<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SwissHotelsService;
use App\Support\SyncErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class BudgetHotelsController extends Controller
{
    public function index(SwissHotelsService $hotels): View
    {
        return view('admin.budget.hotels.index', [
            'pageTitle' => 'Бюджет — Отели по кантонам',
            'regions' => $hotels->regions(),
            'lastFullSyncAt' => $hotels->lastFullSyncAt(),
        ]);
    }

    public function syncRegion(string $slug, SwissHotelsService $hotels): JsonResponse
    {
        $region = $hotels->findRegion($slug);
        if ($region === null) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $count = $hotels->syncFromApi($slug);
            $region->refresh();

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $region->label,
                'count' => $count,
                'synced_at' => $region->hotels_synced_at?->format('d.m.Y H:i'),
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

    public function completeFullSync(SwissHotelsService $hotels): JsonResponse
    {
        $syncedAt = $hotels->markFullSyncComplete();

        return response()->json([
            'ok' => true,
            'last_full_sync_at' => $syncedAt->format('d.m.Y H:i'),
        ]);
    }

    public function show(string $slug, Request $request, SwissHotelsService $hotels): View
    {
        $region = $hotels->findRegion($slug);
        if ($region === null) {
            abort(404);
        }

        $error = null;
        $syncedCount = $region->hotels()->count();

        try {
            if ($request->boolean('refresh')) {
                $syncedCount = $hotels->syncFromApi($slug);
                $region->refresh();
            }
            $items = $hotels->paginateFromDb($slug, $request);
        } catch (\Throwable $e) {
            $items = new LengthAwarePaginator([], 0, 100);
            $error = $e->getMessage();
        }

        return view('admin.budget.hotels.show', [
            'pageTitle' => 'Отели — '.$region->label,
            'region' => $region,
            'apiHint' => $hotels->apiHint($region),
            'items' => $items,
            'syncedCount' => $syncedCount,
            'error' => $error,
        ]);
    }

    public function hotel(string $slug, int $hotel, SwissHotelsService $hotels): View
    {
        $region = $hotels->findRegion($slug);
        $hotelModel = $hotels->findHotel($slug, $hotel);
        if ($region === null || $hotelModel === null) {
            abort(404);
        }

        return view('admin.budget.hotels.hotel', [
            'pageTitle' => $hotelModel->title,
            'region' => $region,
            'hotel' => $hotelModel,
            'grid' => $hotels->occupancyGrid(),
            'apiHint' => $hotels->apiHint($region),
            'stored' => $hotels->storedOccupancyPrices($hotelModel),
        ]);
    }

    public function occupancyPrices(Request $request, string $slug, int $hotel, SwissHotelsService $hotels): JsonResponse
    {
        $region = $hotels->findRegion($slug);
        $hotelModel = $hotels->findHotel($slug, $hotel);
        if ($region === null || $hotelModel === null) {
            return response()->json(['ok' => false, 'message' => 'Отель не найден'], 404);
        }

        $key = trim((string) $request->input('key', ''));
        if ($key === '') {
            return response()->json(['ok' => false, 'message' => 'Не указана ячейка occupancy'], 422);
        }

        try {
            $data = $hotels->fetchOccupancyCell(
                $hotelModel,
                $region,
                $key,
                $request->boolean('skip_filled')
            );

            return response()->json([
                'ok' => true,
                'saved' => true,
                'skipped' => (bool) ($data['skipped'] ?? false),
                'hotel' => $hotelModel->title,
                'hotel_identifier' => $hotelModel->hotel_identifier,
                'stored_2a_price' => (float) $hotelModel->price_usd,
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'fetched_at' => $data['fetched_at'],
                'cell' => $data['cell'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => SyncErrorMessage::format($e),
            ], 502);
        }
    }

    public function occupancyBatchHotels(string $slug, SwissHotelsService $hotels): JsonResponse
    {
        $region = $hotels->findRegion($slug);
        if ($region === null) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $list = $hotels->hotelsForOccupancyBatch($slug);

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $region->label,
                'keys' => $hotels->selectedOccupancyKeys(),
                'hotels' => $list,
                'count' => count($list),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => SyncErrorMessage::format($e),
            ], 502);
        }
    }
}
