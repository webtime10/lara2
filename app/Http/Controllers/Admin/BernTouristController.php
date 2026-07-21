<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DfsBlCategoryAggregation;
use App\Models\DfsBlLocationCandidate;
use App\Models\DfsBlPoi;
use App\Models\DfsBlTouristCategoryMatch;
use App\Services\BusinessListings\BernTouristCollectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BernTouristController extends Controller
{
    public function index(Request $request): View
    {
        $destination = 'bern';
        $selectedLocation = DfsBlLocationCandidate::query()
            ->where('destination_slug', $destination)
            ->where('is_selected', true)
            ->orderByDesc('id')
            ->first();

        $aggQuery = DfsBlCategoryAggregation::query()
            ->where('destination_slug', $destination)
            ->orderByDesc('collected_at')
            ->orderByDesc('objects_count');

        $latestAggAt = (clone $aggQuery)->value('collected_at');
        $aggregations = $latestAggAt
            ? (clone $aggQuery)->where('collected_at', $latestAggAt)->get()
                ->groupBy('category_code')
                ->map(fn ($rows) => $rows->sortByDesc('objects_count')->first())
                ->sortByDesc('objects_count')
                ->values()
            : collect();

        $matches = DfsBlTouristCategoryMatch::query()
            ->orderByDesc('collected_at')
            ->orderBy('topic_group')
            ->limit(200)
            ->get()
            ->unique('topic_group')
            ->values();

        $poisQuery = DfsBlPoi::query()
            ->with('categories')
            ->where('destination_slug', $destination)
            ->orderByDesc('collected_at')
            ->orderBy('name');

        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $poisQuery->where(function ($builder) use ($q) {
                $builder->where('name', 'like', '%'.$q.'%')
                    ->orWhere('title', 'like', '%'.$q.'%')
                    ->orWhere('address', 'like', '%'.$q.'%');
            });
        }

        if ($request->filled('category')) {
            $category = trim((string) $request->input('category'));
            $poisQuery->whereHas('categories', function ($builder) use ($category) {
                $builder->where('category_name', $category)
                    ->orWhere('category_code', $category);
            });
        }

        $pois = $poisQuery->paginate(40)->withQueryString();

        $categoryOptions = DfsBlPoi::query()
            ->where('destination_slug', $destination)
            ->whereNotNull('primary_category')
            ->distinct()
            ->orderBy('primary_category')
            ->pluck('primary_category');

        $stats = [
            'pois_total' => DfsBlPoi::query()->where('destination_slug', $destination)->count(),
            'without_coords' => DfsBlPoi::query()->where('destination_slug', $destination)->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            })->count(),
            'without_rating' => DfsBlPoi::query()->where('destination_slug', $destination)->whereNull('rating')->count(),
            'without_reviews' => DfsBlPoi::query()->where('destination_slug', $destination)->whereNull('reviews_count')->count(),
            'matched_topics' => $matches->where('matched', true)->count(),
            'unmatched_topics' => $matches->where('matched', false)->count(),
        ];

        return view('admin.bern-tourist.index', [
            'pageTitle' => 'Bern — туристические Business Listings',
            'selectedLocation' => $selectedLocation,
            'locationCandidates' => DfsBlLocationCandidate::query()
                ->where('destination_slug', $destination)
                ->orderByDesc('is_selected')
                ->orderBy('location_name')
                ->get(),
            'aggregations' => $aggregations,
            'matches' => $matches,
            'pois' => $pois,
            'stats' => $stats,
            'categoryOptions' => $categoryOptions,
            'filters' => [
                'q' => (string) $request->input('q', ''),
                'category' => (string) $request->input('category', ''),
            ],
        ]);
    }

    public function collect(Request $request, BernTouristCollectService $service): JsonResponse
    {
        try {
            $stats = $service->collect(
                $request->boolean('skip_listings'),
                max(1, (int) $request->input('probe_limit', 3))
            );

            return response()->json([
                'ok' => true,
                'stats' => $stats,
                'redirect' => route('admin.bern-tourist.index'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}
