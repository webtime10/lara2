<?php

namespace App\Services;

use App\Jobs\RefreshWeatherMonthStatJob;
use App\Models\WeatherMonthStat;
use App\Models\WeatherSyncRun;
use App\Support\SwissWeatherCantons;
use Illuminate\Support\Str;
use RuntimeException;

class WeatherSyncDispatcher
{
    /**
     * Паузы между джобами (сек), по кругу: 3 мин → 2 мин → 3 мин → 2 мин...
     * Первая задача стартует сразу (delay = 0).
     *
     * @var list<int>
     */
    public const STAGGER_STEPS_SECONDS = [180, 120];

    /** Для подписей в UI: средняя/первая пауза. */
    public const STAGGER_SECONDS = 180;

    public static function staggerLabel(): string
    {
        $parts = array_map(
            static fn (int $sec): string => ($sec % 60 === 0 ? ((int) ($sec / 60)).' мин' : $sec.' с'),
            self::STAGGER_STEPS_SECONDS
        );

        return implode(' / ', $parts);
    }

    /**
     * @param  string  $source  WeatherSyncRun::SOURCE_MANUAL|SOURCE_SCHEDULE
     * @return array{run: WeatherSyncRun, queued: int}
     */
    public function dispatchAll(
        bool $force = false,
        bool $onlyEmpty = true,
        ?string $slug = null,
        string $source = WeatherSyncRun::SOURCE_MANUAL,
    ): array {
        if ($slug !== null) {
            $canton = SwissWeatherCantons::findBySlug($slug);
            if ($canton === null) {
                throw new RuntimeException('Кантон не найден: '.$slug);
            }
            $cantons = [$canton];
        } else {
            $cantons = SwissWeatherCantons::all();
        }

        $source = $source === WeatherSyncRun::SOURCE_SCHEDULE
            ? WeatherSyncRun::SOURCE_SCHEDULE
            : WeatherSyncRun::SOURCE_MANUAL;

        $tasks = [];
        foreach ($cantons as $canton) {
            foreach (SwissWeatherCantons::months() as $month) {
                if ($onlyEmpty && ! $force) {
                    $existing = WeatherMonthStat::query()
                        ->where('region_slug', $canton['slug'])
                        ->where('month', $month)
                        ->first();
                    if ($existing instanceof WeatherMonthStat && $existing->isFilled()) {
                        continue;
                    }
                }
                $tasks[] = ['slug' => $canton['slug'], 'month' => $month];
            }
        }

        $originLabel = $source === WeatherSyncRun::SOURCE_SCHEDULE
            ? 'Автозапуск по расписанию'
            : 'Ручной запуск';

        $run = WeatherSyncRun::query()->create([
            'uuid' => (string) Str::uuid(),
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
            )->delay(now()->addSeconds($delay));

            // После первой задачи наращиваем delay: 3 мин, потом 2 мин, потом снова 3...
            if ($index < count($tasks) - 1) {
                $delay += $steps[$stepIndex % count($steps)];
                $stepIndex++;
            }
        }

        $run->status = WeatherSyncRun::STATUS_RUNNING;
        $run->save();

        return ['run' => $run, 'queued' => count($tasks)];
    }
}
