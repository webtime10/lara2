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
}
