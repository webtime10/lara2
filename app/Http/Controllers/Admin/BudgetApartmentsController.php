<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SwissApartmentsService;
use App\Support\SyncErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class BudgetApartmentsController extends Controller
{
    public function index(SwissApartmentsService $apartments): View
    {
        return view('admin.budget.apartments.index', [
            'pageTitle' => 'Бюджет — Апартаменты по кантонам',
            'regions' => $apartments->regions(),
            'lastFullSyncAt' => $apartments->lastFullSyncAt(),
        ]);
    }

    public function syncRegion(string $slug, SwissApartmentsService $apartments): JsonResponse
    {
        $region = $apartments->findRegion($slug);
        if ($region === null) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $count = $apartments->syncFromApi($slug);
            $region->refresh();

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $region->label,
                'count' => $count,
                'synced_at' => $region->apartments_synced_at?->format('d.m.Y H:i'),
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

    public function completeFullSync(SwissApartmentsService $apartments): JsonResponse
    {
        $syncedAt = $apartments->markFullSyncComplete();

        return response()->json([
            'ok' => true,
            'last_full_sync_at' => $syncedAt->format('d.m.Y H:i'),
        ]);
    }

    public function show(string $slug, Request $request, SwissApartmentsService $apartments): View
    {
        $region = $apartments->findRegion($slug);
        if ($region === null) {
            abort(404);
        }

        $error = null;
        $syncedCount = $region->apartments()->count();

        try {
            if ($request->boolean('refresh')) {
                $syncedCount = $apartments->syncFromApi($slug);
                $region->refresh();
            }
            $items = $apartments->paginateFromDb($slug, $request);
        } catch (\Throwable $e) {
            $items = new LengthAwarePaginator([], 0, 100);
            $error = $e->getMessage();
        }

        return view('admin.budget.apartments.show', [
            'pageTitle' => 'Апартаменты — '.$region->label,
            'region' => $region,
            'apiHint' => $apartments->apiHint($region),
            'items' => $items,
            'syncedCount' => $syncedCount,
            'error' => $error,
        ]);
    }
}
