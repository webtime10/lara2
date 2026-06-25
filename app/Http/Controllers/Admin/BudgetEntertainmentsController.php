<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SwissEntertainment;
use App\Models\SwissEntertainmentSyncState;
use App\Models\SwissRegion;
use App\Services\EntertainmentGeminiService;
use App\Services\SwissEntertainmentsService;
use App\Support\SyncErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class BudgetEntertainmentsController extends Controller
{
    public function index(SwissEntertainmentsService $entertainments): View
    {
        return view('admin.budget.entertainments.index', [
            'pageTitle' => 'Бюджет — Развлечения по кантонам',
            'regions' => $entertainments->regions(),
            'lastFullSyncAt' => $entertainments->lastFullSyncAt(),
        ]);
    }

    public function syncRegion(string $slug, SwissEntertainmentsService $entertainments): JsonResponse
    {
        $region = $entertainments->findRegion($slug);
        if ($region === null) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $count = $entertainments->syncFromApi($slug);
            $region->refresh();

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $region->label,
                'count' => $count,
                'synced_at' => $region->entertainments_synced_at?->format('d.m.Y H:i'),
                'items' => $entertainments->structuredForRegion($region),
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
        $count = SwissEntertainment::query()->count();
        SwissEntertainment::query()->delete();
        SwissRegion::query()->update(['entertainments_synced_at' => null]);
        SwissEntertainmentSyncState::current()->update(['last_full_sync_at' => null]);

        return response()->json([
            'ok' => true,
            'deleted' => $count,
        ]);
    }

    public function clearRegion(string $slug, SwissEntertainmentsService $entertainments): JsonResponse
    {
        $region = $entertainments->findRegion($slug);
        if ($region === null) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        $count = SwissEntertainment::query()
            ->where('region_id', $region->id)
            ->count();

        SwissEntertainment::query()
            ->where('region_id', $region->id)
            ->delete();
        $region->update(['entertainments_synced_at' => null]);

        return response()->json([
            'ok' => true,
            'slug' => $region->slug,
            'label' => $region->label,
            'deleted' => $count,
        ]);
    }

    public function completeFullSync(SwissEntertainmentsService $entertainments): JsonResponse
    {
        $syncedAt = $entertainments->markFullSyncComplete();

        return response()->json([
            'ok' => true,
            'last_full_sync_at' => $syncedAt->format('d.m.Y H:i'),
        ]);
    }

    public function sendToGemini(
        string $slug,
        Request $request,
        SwissEntertainmentsService $entertainments,
        EntertainmentGeminiService $gemini,
    ): JsonResponse {
        $region = $entertainments->findRegion($slug);
        if ($region === null) {
            return response()->json(['ok' => false, 'message' => 'Регион не найден'], 404);
        }

        try {
            $result = $gemini->runForRegion(
                $region,
                $request->input('entertainment_level')
            );

            return response()->json([
                'ok' => true,
                'slug' => $region->slug,
                'label' => $region->label,
                'payload' => $result['payload'],
                'answer' => $result['answer'],
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

    public function show(string $slug, Request $request, SwissEntertainmentsService $entertainments): View
    {
        $region = $entertainments->findRegion($slug);
        if ($region === null) {
            abort(404);
        }

        $error = null;
        $syncedCount = $region->entertainments()->count();

        try {
            if ($request->boolean('refresh')) {
                $syncedCount = $entertainments->syncFromApi($slug);
                $region->refresh();
            }
            $items = $entertainments->paginateFromDb($slug, $request);
        } catch (\Throwable $e) {
            $items = new LengthAwarePaginator([], 0, 100);
            $error = $e->getMessage();
        }

        return view('admin.budget.entertainments.show', [
            'pageTitle' => 'Развлечения — '.$region->label,
            'region' => $region,
            'apiHint' => $entertainments->apiHint($region),
            'items' => $items,
            'syncedCount' => $syncedCount,
            'summary' => $entertainments->regionSummary($region->entertainments()->get()),
            'structuredPayload' => $entertainments->structuredForRegion($region),
            'error' => $error,
        ]);
    }
}
