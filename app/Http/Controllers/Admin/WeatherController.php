<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\WeatherMonthStat;
use App\Models\WeatherPromt;
use App\Models\WeatherSyncRun;
use App\Services\WeatherMonthStatAiService;
use App\Services\WeatherSyncDispatcher;
use App\Support\WeatherCountry;
use App\Support\SyncErrorMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class WeatherController extends Controller
{
    public function index(string $country = WeatherCountry::CH): View
    {
        $country = WeatherCountry::normalize($country);
        $regionsClass = WeatherCountry::regionsClass($country);
        $tableExists = Schema::hasTable('weather_month_stats');
        $runsExist = Schema::hasTable('weather_sync_runs');
        $stats = $tableExists
            ? WeatherMonthStat::query()
                ->where('country', $country)
                ->get()
                ->groupBy('region_slug')
                ->map(fn ($items) => $items->keyBy('month'))
            : collect();

        $regions = $regionsClass::all();
        $months = $regionsClass::months();

        $rows = collect($regions)->map(function (array $region) use ($stats, $months): array {
            $byMonth = $stats->get($region['slug'], collect());
            $filled = 0;
            foreach ($months as $month) {
                $stat = $byMonth->get($month);
                if ($stat instanceof WeatherMonthStat && $stat->isFilled()) {
                    $filled++;
                }
            }

            return [
                'canton' => $region,
                'stats' => $byMonth,
                'filled' => $filled,
            ];
        });

        $regionsPayload = $rows->map(fn (array $row): array => [
            'slug' => $row['canton']['slug'],
            'label' => $row['canton']['name_ru'],
            'url' => route('admin.weather.queue-region', [$country, $row['canton']['slug']], false),
        ])->values();

        $activeRun = null;
        $lastScheduledSuccess = null;
        if ($runsExist) {
            $activeRun = WeatherSyncRun::query()
                ->where('country', $country)
                ->whereIn('status', [WeatherSyncRun::STATUS_QUEUED, WeatherSyncRun::STATUS_RUNNING])
                ->latest('id')
                ->first();

            $lastScheduledSuccess = WeatherSyncRun::query()
                ->where('country', $country)
                ->where('source', WeatherSyncRun::SOURCE_SCHEDULE)
                ->where('status', WeatherSyncRun::STATUS_DONE)
                ->whereNotNull('finished_at')
                ->latest('finished_at')
                ->first();
        }

        $scheduleHint = $country === WeatherCountry::JP
            ? '1-го числа в 04:00'
            : '1-го числа в 03:00';

        return view('admin.weather.index', [
            'pageTitle' => 'Погода — '.WeatherCountry::label($country),
            'country' => $country,
            'countryLabel' => WeatherCountry::label($country),
            'promptAdminPath' => WeatherCountry::promptAdminPath($country),
            'promptUrl' => route('admin.prompts-wp.weather', $country, false),
            'scheduleHint' => $scheduleHint,
            'rows' => $rows,
            'months' => $months,
            'monthNames' => $regionsClass::monthNamesRu(),
            'tableExists' => $tableExists,
            'runsExist' => $runsExist,
            'regionsPayload' => $regionsPayload,
            'aiModelLabels' => WeatherMonthStatAiService::modelLabels(),
            'defaultAiModel' => WeatherMonthStatAiService::defaultModel(),
            'activeRun' => $activeRun,
            'lastScheduledSuccess' => $lastScheduledSuccess,
            'queueAllUrl' => route('admin.weather.queue', $country, false),
            'statusUrl' => route('admin.weather.status', $country, false),
            'stopUrl' => route('admin.weather.stop', $country, false),
            'clearAllUrl' => route('admin.weather.clear-all', $country, false),
        ]);
    }

    public function queue(string $country, Request $request, WeatherSyncDispatcher $dispatcher): JsonResponse
    {
        $country = WeatherCountry::normalize($country);

        if (! Schema::hasTable('weather_month_stats') || ! Schema::hasTable('weather_sync_runs')) {
            return response()->json([
                'ok' => false,
                'message' => 'Таблицы Weather ещё не созданы. Запустите миграции Laravel.',
            ], 409);
        }

        $force = (bool) $request->boolean('force');
        $onlyEmpty = ! $force;

        if ($missing = $this->missingPromptMessage($country)) {
            return response()->json(['ok' => false, 'message' => $missing], 422);
        }

        try {
            $result = $dispatcher->dispatchAll($force, $onlyEmpty, null, WeatherSyncRun::SOURCE_MANUAL, $country);
            $run = $result['run'];

            return response()->json([
                'ok' => true,
                'queued' => $result['queued'],
                'run' => $this->runPayload($run),
                'hint' => 'Нужен воркер: php artisan queue:work',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => SyncErrorMessage::format($e),
            ], 502);
        }
    }

    public function queueRegion(string $country, string $slug, Request $request, WeatherSyncDispatcher $dispatcher): JsonResponse
    {
        $country = WeatherCountry::normalize($country);

        if (! Schema::hasTable('weather_month_stats') || ! Schema::hasTable('weather_sync_runs')) {
            return response()->json([
                'ok' => false,
                'message' => 'Таблицы Weather ещё не созданы. Запустите миграции Laravel.',
            ], 409);
        }

        $force = (bool) $request->boolean('force', true);

        if ($missing = $this->missingPromptMessage($country)) {
            return response()->json(['ok' => false, 'message' => $missing], 422);
        }

        try {
            $result = $dispatcher->dispatchAll($force, ! $force, $slug, WeatherSyncRun::SOURCE_MANUAL, $country);
            $run = $result['run'];

            return response()->json([
                'ok' => true,
                'queued' => $result['queued'],
                'run' => $this->runPayload($run),
                'hint' => 'Нужен воркер: php artisan queue:work',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => SyncErrorMessage::format($e),
            ], 502);
        }
    }

    public function status(string $country, Request $request): JsonResponse
    {
        $country = WeatherCountry::normalize($country);

        if (! Schema::hasTable('weather_sync_runs')) {
            return response()->json(['ok' => false, 'message' => 'Нет таблицы weather_sync_runs'], 409);
        }

        $uuid = trim((string) $request->query('uuid', ''));
        $run = $uuid !== ''
            ? WeatherSyncRun::query()->where('uuid', $uuid)->where('country', $country)->first()
            : WeatherSyncRun::query()->where('country', $country)->latest('id')->first();

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

    public function stop(string $country, Request $request, WeatherSyncDispatcher $dispatcher): JsonResponse
    {
        $country = WeatherCountry::normalize($country);

        if (! Schema::hasTable('weather_sync_runs')) {
            return response()->json(['ok' => false, 'message' => 'Нет таблицы weather_sync_runs'], 409);
        }

        $uuid = trim((string) $request->input('uuid', ''));
        $cancelled = $dispatcher->cancelAllActive($country);

        if ($cancelled === [] && $uuid !== '') {
            $run = WeatherSyncRun::query()->where('uuid', $uuid)->where('country', $country)->first();
            if ($run && ! $run->isFinished()) {
                $cancelled[] = $dispatcher->cancelRun($run);
            } elseif ($run) {
                return response()->json([
                    'ok' => true,
                    'run' => $this->runPayload($run),
                ]);
            }
        }

        if ($cancelled === []) {
            return response()->json(['ok' => false, 'message' => 'Активный запуск не найден'], 404);
        }

        $run = $cancelled[array_key_last($cancelled)];

        return response()->json([
            'ok' => true,
            'run' => $this->runPayload($run),
        ]);
    }

    public function refresh(string $country, string $slug, Request $request): JsonResponse
    {
        return $this->queueRegion($country, $slug, $request, app(WeatherSyncDispatcher::class));
    }

    public function clearAll(string $country): JsonResponse
    {
        $country = WeatherCountry::normalize($country);

        if (! Schema::hasTable('weather_month_stats')) {
            return response()->json([
                'ok' => false,
                'message' => 'Таблица weather_month_stats ещё не создана.',
            ], 409);
        }

        $deleted = WeatherMonthStat::query()->where('country', $country)->count();
        WeatherMonthStat::query()->where('country', $country)->delete();

        return response()->json([
            'ok' => true,
            'deleted' => $deleted,
        ]);
    }

    private function missingPromptMessage(string $country): ?string
    {
        $country = WeatherCountry::normalize($country);
        $prefix = WeatherCountry::promptPrefix($country);
        $codes = Language::forAdminForms()->pluck('code')->map(fn ($c) => strtolower((string) $c))->all();
        if ($codes === []) {
            $codes = ['ar', 'he', 'ru', 'en'];
        }

        foreach ($codes as $code) {
            $content = WeatherPromt::query()->where('name', $prefix.$code)->value('content');
            if (is_string($content) && trim($content) !== '') {
                return null;
            }
        }

        $legacy = WeatherPromt::query()->where('name', WeatherCountry::legacyPromptName($country))->value('content');
        if (is_string($legacy) && trim($legacy) !== '') {
            return null;
        }

        return 'Главный промт не задан. Сначала заполните: '.WeatherCountry::promptAdminPath($country).'.';
    }

    /** @return array<string, mixed> */
    private function runPayload(WeatherSyncRun $run): array
    {
        $processed = $run->processed();
        $pct = $run->total > 0 ? (int) round(($processed / $run->total) * 100) : 0;

        return [
            'uuid' => $run->uuid,
            'country' => $run->country,
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
