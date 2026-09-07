<?php

namespace App\Jobs;

use App\Models\WeatherMonthStat;
use App\Models\WeatherSyncRun;
use App\Services\WeatherSyncDispatcher;
use App\Support\WeatherCountry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * После прогона с fail — поставить в очередь только пустые клетки.
 */
class QueueWeatherEmptyRefillJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public const MAX_WAVES = 40;

    public function __construct(
        public readonly int $parentRunId,
        public readonly int $wave = 1,
        public readonly string $country = WeatherCountry::CH,
    ) {
    }

    public function handle(WeatherSyncDispatcher $dispatcher): void
    {
        $parent = WeatherSyncRun::query()->find($this->parentRunId);
        if ($parent && $parent->status === WeatherSyncRun::STATUS_CANCELLED) {
            return;
        }

        $country = WeatherCountry::normalize(
            ($parent?->country ?: null) ?: $this->country
        );

        if ($this->wave > self::MAX_WAVES) {
            if ($parent) {
                $parent->appendLog(
                    'Автодозаливка остановлена: достигнут лимит '.self::MAX_WAVES.' волн. Нажмите «Дозалить».',
                    false
                );
                $parent->save();
            }

            return;
        }

        $emptyLeft = $this->countEmptyCells($country);
        if ($emptyLeft === 0) {
            if ($parent) {
                $parent->appendLog('Автодозаливка: пустых клеток не осталось', true);
                $parent->save();
            }

            return;
        }

        $result = $dispatcher->dispatchAll(
            force: false,
            onlyEmpty: true,
            slug: null,
            source: WeatherSyncRun::SOURCE_REFILL,
            country: $country,
        );

        if (! $parent) {
            return;
        }

        $queued = (int) ($result['queued'] ?? 0);
        $parent->appendLog(
            $queued > 0
                ? 'Автодозаливка волна '.$this->wave.': в очередь '.$queued
                    .' пустых (оставалось ~'.$emptyLeft.', run '.$result['run']->uuid.')'
                : 'Автодозаливка волна '.$this->wave.': пустых клеток не осталось',
            true
        );
        $parent->save();
    }

    private function countEmptyCells(string $country): int
    {
        $regionsClass = WeatherCountry::regionsClass($country);
        $empty = 0;
        foreach ($regionsClass::all() as $region) {
            foreach ($regionsClass::months() as $month) {
                $existing = WeatherMonthStat::query()
                    ->where('country', $country)
                    ->where('region_slug', $region['slug'])
                    ->where('month', $month)
                    ->first();
                if (! $existing instanceof WeatherMonthStat || ! $existing->isFilled()) {
                    $empty++;
                }
            }
        }

        return $empty;
    }
}
