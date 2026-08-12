<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WeatherMonthStat;
use App\Models\WeatherSyncRun;
use App\Services\WeatherMonthStatAiService;
use App\Services\WeatherSyncDispatcher;
use App\Support\SwissWeatherCantons;
use App\Support\SyncErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WeatherController extends Controller
{
    public function index(): View
    {
        $tableExists = Schema::hasTable('weather_month_stats');
        $runsExist = Schema::hasTable('weather_sync_runs');
        $stats = $tableExists
            ? WeatherMonthStat::query()
                ->get()
                ->groupBy('region_slug')
                ->map(fn ($items) => $items->keyBy('month'))
            : collect();

        $cantons = SwissWeatherCantons::all();
        $months = SwissWeatherCantons::months();

        $rows = collect($cantons)->map(function (array $canton) use ($stats, $months): array {
            $byMonth = $stats->get($canton['slug'], collect());
            $filled = 0;
            foreach ($months as $month) {
                $stat = $byMonth->get($month);
                if ($stat instanceof WeatherMonthStat && $stat->isFilled()) {
                    $filled++;
                }
            }

            return [
                'canton' => $canton,
                'stats' => $byMonth,
                'filled' => $filled,
            ];
        });

        $regionsPayload = $rows->map(fn (array $row): array => [
            'slug' => $row['canton']['slug'],
            'label' => $row['canton']['name_ru'],
            'url' => route('admin.weather.queue-region', $row['canton']['slug'], false),
        ])->values();

        $activeRun = null;
        $lastScheduledSuccess = null;
        if ($runsExist) {
            $activeRun = WeatherSyncRun::query()
                ->whereIn('status', [WeatherSyncRun::STATUS_QUEUED, WeatherSyncRun::STATUS_RUNNING])
                ->latest('id')
                ->first();

            $lastScheduledSuccess = WeatherSyncRun::query()
                ->where('source', WeatherSyncRun::SOURCE_SCHEDULE)
                ->where('status', WeatherSyncRun::STATUS_DONE)
                ->whereNotNull('finished_at')
                ->latest('finished_at')
                ->first();
        }

        return view('admin.weather.index', [
            'pageTitle' => 'Погода — Швейцария',
            'rows' => $rows,
            'months' => $months,
            'monthNames' => SwissWeatherCantons::monthNamesRu(),
            'tableExists' => $tableExists,
            'runsExist' => $runsExist,
            'regionsPayload' => $regionsPayload,
            'aiModelLabels' => WeatherMonthStatAiService::modelLabels(),
            'defaultAiModel' => WeatherMonthStatAiService::defaultModel(),
            'activeRun' => $activeRun,
            'lastScheduledSuccess' => $lastScheduledSuccess,
            // Относительные URL: иначе при APP_URL=https и входе по http опрос статуса молча падает.
            'queueAllUrl' => route('admin.weather.queue', [], false),
            'statusUrl' => route('admin.weather.status', [], false),
        ]);
    }

    public function queue(Request $request, WeatherSyncDispatcher $dispatcher): JsonResponse
    {
        if (! Schema::hasTable('weather_month_stats') || ! Schema::hasTable('weather_sync_runs')) {
            return response()->json([
                'ok' => false,
                'message' => 'Таблицы Weather ещё не созданы. Запустите миграции Laravel.',
            ], 409);
        }

        $force = (bool) $request->boolean('force');
        $onlyEmpty = ! $force;

        try {
            $result = $dispatcher->dispatchAll($force, $onlyEmpty);
            $run = $result['run'];

            return response()->json([
                'ok' => true,
                'queued' => $result['queued'],
                'run' => $this->runPayload($run),
                'hint' => 'Нужен воркер: php artisan queue:work --queue=weather',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => SyncErrorMessage::format($e),
            ], 502);
        }
    }

    public function queueRegion(string $slug, Request $request, WeatherSyncDispatcher $dispatcher): JsonResponse
    {
        if (! Schema::hasTable('weather_month_stats') || ! Schema::hasTable('weather_sync_runs')) {
            return response()->json([
                'ok' => false,
                'message' => 'Таблицы Weather ещё не созданы. Запустите миграции Laravel.',
            ], 409);
        }

        $force = (bool) $request->boolean('force', true);

        try {
            $result = $dispatcher->dispatchAll($force, ! $force, $slug);
            $run = $result['run'];

            return response()->json([
                'ok' => true,
                'queued' => $result['queued'],
                'run' => $this->runPayload($run),
                'hint' => 'Нужен воркер: php artisan queue:work --queue=weather',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => SyncErrorMessage::format($e),
            ], 502);
        }
    }

    public function status(Request $request): JsonResponse
    {
        if (! Schema::hasTable('weather_sync_runs')) {
            return response()->json(['ok' => false, 'message' => 'Нет таблицы weather_sync_runs'], 409);
        }

        $uuid = trim((string) $request->query('uuid', ''));
        $run = $uuid !== ''
            ? WeatherSyncRun::query()->where('uuid', $uuid)->first()
            : WeatherSyncRun::query()->latest('id')->first();

        if (! $run) {
            return response()->json(['ok' => false, 'message' => 'Запуск не найден'], 404);
        }

        if ($run->status === WeatherSyncRun::STATUS_RUNNING && $run->processed() >= $run->total && $run->total > 0) {
            $run->status = $run->failed > 0 && $run->succeeded === 0
                ? WeatherSyncRun::STATUS_FAILED
                : WeatherSyncRun::STATUS_DONE;
            $run->finished_at = $run->finished_at ?? now();
            $run->save();
        }

        return response()->json([
            'ok' => true,
            'run' => $this->runPayload($run),
        ]);
    }

    /** Старый синхронный endpoint оставлен не используется UI; редирект смысла нет. */
    public function refresh(string $slug, Request $request, WeatherMonthStatAiService $ai): JsonResponse
    {
        return $this->queueRegion($slug, $request, app(WeatherSyncDispatcher::class));
    }

    public function clearAll(): JsonResponse
    {
        if (! Schema::hasTable('weather_month_stats')) {
            return response()->json([
                'ok' => false,
                'message' => 'Таблица weather_month_stats ещё не создана.',
            ], 409);
        }

        $deleted = WeatherMonthStat::query()->count();
        WeatherMonthStat::query()->delete();

        return response()->json([
            'ok' => true,
            'deleted' => $deleted,
        ]);
    }

    /** @return array<string, mixed> */
    private function runPayload(WeatherSyncRun $run): array
    {
        $processed = $run->processed();
        $pct = $run->total > 0 ? (int) round(($processed / $run->total) * 100) : 0;

        return [
            'uuid' => $run->uuid,
            'status' => $run->status,
            'force' => $run->force,
            'only_empty' => $run->only_empty,
            'total' => $run->total,
            'succeeded' => $run->succeeded,
            'failed' => $run->failed,
            'skipped' => $run->skipped,
            'processed' => $processed,
            'percent' => $pct,
            'finished' => $run->isFinished(),
            'last_message' => $run->last_message,
            'logs' => $run->recent_logs ?? [],
            'started_at' => $run->started_at?->format('d.m.Y H:i'),
            'finished_at' => $run->finished_at?->format('d.m.Y H:i'),
        ];
    }
}
