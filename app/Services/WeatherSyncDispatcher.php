<?php

namespace App\Services;

use App\Jobs\RefreshWeatherMonthStatJob;
use App\Models\WeatherMonthStat;
use App\Models\WeatherSyncRun;
use App\Support\WeatherCountry;
use Illuminate\Support\Str;
use RuntimeException;

class WeatherSyncDispatcher
{
    /**
     * Паузы между джобами (сек), по кругу: 5 мин → 4 мин → 5 мин → 4 мин...
     * Первая задача стартует сразу (delay = 0).
     *
     * @var list<int>
     */
    public const STAGGER_STEPS_SECONDS = [300, 240];

    public const STAGGER_SECONDS = 300;

    public const REFILL_DELAY_SECONDS = 300;

    public static function staggerLabel(): string
    {
        $parts = array_map(
            static fn (int $sec): string => ($sec % 60 === 0 ? ((int) ($sec / 60)).' мин' : $sec.' с'),
            self::STAGGER_STEPS_SECONDS
        );

        return implode(' / ', $parts);
    }

    /**
     * @param  string  $source  WeatherSyncRun::SOURCE_MANUAL|SOURCE_SCHEDULE|SOURCE_REFILL
     * @return array{run: WeatherSyncRun, queued: int}
     */
    public function dispatchAll(
        bool $force = false,
        bool $onlyEmpty = true,
        ?string $slug = null,
        string $source = WeatherSyncRun::SOURCE_MANUAL,
        string $country = WeatherCountry::CH,
    ): array {
        $country = WeatherCountry::normalize($country);
        $regionsClass = WeatherCountry::regionsClass($country);

        if ($slug !== null) {
            $region = $regionsClass::findBySlug($slug);
            if ($region === null) {
                throw new RuntimeException('Регион не найден: '.$slug);
            }
            $regions = [$region];
        } else {
            $regions = $regionsClass::all();
        }

        $source = match ($source) {
            WeatherSyncRun::SOURCE_SCHEDULE => WeatherSyncRun::SOURCE_SCHEDULE,
            WeatherSyncRun::SOURCE_REFILL => WeatherSyncRun::SOURCE_REFILL,
            default => WeatherSyncRun::SOURCE_MANUAL,
        };

        $tasks = [];
        foreach ($regions as $region) {
            foreach ($regionsClass::months() as $month) {
                if ($onlyEmpty && ! $force) {
                    $existing = WeatherMonthStat::query()
                        ->where('country', $country)
                        ->where('region_slug', $region['slug'])
                        ->where('month', $month)
                        ->first();
                    if ($existing instanceof WeatherMonthStat && $existing->isFilled()) {
                        continue;
                    }
                }
                $tasks[] = ['slug' => $region['slug'], 'month' => $month];
            }
        }

        $countryLabel = WeatherCountry::label($country);
        $originLabel = match ($source) {
            WeatherSyncRun::SOURCE_SCHEDULE => 'Автозапуск по расписанию ('.$countryLabel.')',
            WeatherSyncRun::SOURCE_REFILL => 'Автодозаливка пустых ('.$countryLabel.')',
            default => 'Ручной запуск ('.$countryLabel.')',
        };

        $run = WeatherSyncRun::query()->create([
            'uuid' => (string) Str::uuid(),
            'country' => $country,
            'status' => WeatherSyncRun::STATUS_QUEUED,
            'force' => $force,
            'only_empty' => $onlyEmpty && ! $force,
            'source' => $source,
            'total' => count($tasks),
            'succeeded' => 0,
            'failed' => 0,
            'skipped' => 0,
            'last_message' => count($tasks) === 0
                ? $originLabel.': нечего ставить в очередь, все клетки уже заполнены.'
                : $originLabel.': в очереди '.count($tasks).' задач.',
            'recent_logs' => [[
                'ok' => true,
                'text' => count($tasks) === 0
                    ? $originLabel.': нечего обновлять.'
                    : $originLabel.': поставлено в очередь '.count($tasks)
                        .' (пауза '.self::staggerLabel().', force='.($force ? 'да' : 'нет').')',
                'at' => now()->format('H:i:s'),
            ]],
            'started_at' => now(),
            'finished_at' => count($tasks) === 0 ? now() : null,
        ]);

        if ($tasks === []) {
            $run->status = WeatherSyncRun::STATUS_DONE;
            if ($source === WeatherSyncRun::SOURCE_SCHEDULE) {
                $stamp = now()->format('d.m.Y H:i');
                $run->last_message = 'Автозапуск сработал '.$stamp.'. Нечего обновлять.';
                $run->appendLog('✓ Автозапуск сработал '.$stamp, true);
            }
            $run->save();

            return ['run' => $run, 'queued' => 0];
        }

        $delay = 0;
        $stepIndex = 0;
        $steps = self::STAGGER_STEPS_SECONDS;

        foreach ($tasks as $index => $task) {
            RefreshWeatherMonthStatJob::dispatch(
                $run->id,
                $task['slug'],
                $task['month'],
                $force,
                $onlyEmpty && ! $force,
                $country,
            )->delay(now()->addSeconds($delay));

            if ($index < count($tasks) - 1) {
                $delay += $steps[$stepIndex % count($steps)];
                $stepIndex++;
            }
        }

        $run->status = WeatherSyncRun::STATUS_RUNNING;
        $run->save();

        return ['run' => $run, 'queued' => count($tasks)];
    }

    public function cancelRun(WeatherSyncRun $run): WeatherSyncRun
    {
        if (in_array($run->status, [
            WeatherSyncRun::STATUS_DONE,
            WeatherSyncRun::STATUS_FAILED,
            WeatherSyncRun::STATUS_CANCELLED,
        ], true)) {
            return $run;
        }

        $removed = $this->deleteQueuedJobsForRun($run->id);

        $run->status = WeatherSyncRun::STATUS_CANCELLED;
        $run->finished_at = now();
        $run->appendLog(
            '⏹ Остановлено пользователем'
                .($removed > 0 ? ' (снято с очереди: '.$removed.')' : ''),
            false
        );
        $run->last_message = 'Остановлено. ok='.$run->succeeded
            .', skip='.$run->skipped
            .', fail='.$run->failed
            .' / '.$run->total;
        $run->save();

        return $run->fresh() ?? $run;
    }

    /**
     * @return list<WeatherSyncRun>
     */
    public function cancelAllActive(?string $country = null): array
    {
        $query = WeatherSyncRun::query()
            ->whereIn('status', [WeatherSyncRun::STATUS_QUEUED, WeatherSyncRun::STATUS_RUNNING])
            ->orderBy('id');

        if ($country !== null) {
            $query->where('country', WeatherCountry::normalize($country));
        }

        $runs = $query->get();

        $cancelled = [];
        foreach ($runs as $run) {
            $cancelled[] = $this->cancelRun($run);
        }

        $this->deleteWeatherRefillJobs($country);

        return $cancelled;
    }

    private function deleteQueuedJobsForRun(int $runId): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('jobs')) {
            return 0;
        }

        $removed = 0;
        $needleRun = 'runId";i:'.$runId.';';
        $needleParent = 'parentRunId";i:'.$runId.';';

        \Illuminate\Support\Facades\DB::table('jobs')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($needleRun, $needleParent, &$removed): void {
                foreach ($rows as $row) {
                    $cmd = $this->jobCommand((string) ($row->payload ?? ''));
                    $isRefresh = str_contains($cmd, 'RefreshWeatherMonthStatJob')
                        && str_contains($cmd, $needleRun);
                    $isRefill = str_contains($cmd, 'QueueWeatherEmptyRefillJob')
                        && str_contains($cmd, $needleParent);
                    if ($isRefresh || $isRefill) {
                        \Illuminate\Support\Facades\DB::table('jobs')->where('id', $row->id)->delete();
                        $removed++;
                    }
                }
            });

        return $removed;
    }

    private function deleteWeatherRefillJobs(?string $country = null): int
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('jobs')) {
            return 0;
        }

        $country = $country !== null ? WeatherCountry::normalize($country) : null;
        $removed = 0;
        \Illuminate\Support\Facades\DB::table('jobs')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$removed, $country): void {
                foreach ($rows as $row) {
                    $cmd = $this->jobCommand((string) ($row->payload ?? ''));
                    if (! str_contains($cmd, 'QueueWeatherEmptyRefillJob')) {
                        continue;
                    }
                    if ($country !== null) {
                        $hasCountry = (bool) preg_match('/country";s:\d+:"([a-z]{2})"/', $cmd, $m);
                        $jobCountry = $hasCountry ? $m[1] : WeatherCountry::CH;
                        if ($jobCountry !== $country) {
                            continue;
                        }
                    }
                    \Illuminate\Support\Facades\DB::table('jobs')->where('id', $row->id)->delete();
                    $removed++;
                }
            });

        return $removed;
    }

    /** Достаём сериализованный command из JSON payload jobs (кавычки экранированы). */
    private function jobCommand(string $payload): string
    {
        $decoded = json_decode($payload, true);
        if (is_array($decoded) && isset($decoded['data']['command']) && is_string($decoded['data']['command'])) {
            return $decoded['data']['command'];
        }

        return $payload;
    }
}
