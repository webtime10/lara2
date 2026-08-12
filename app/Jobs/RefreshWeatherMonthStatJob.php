<?php

namespace App\Jobs;

use App\Exceptions\WeatherGeminiRateLimitedException;
use App\Models\WeatherMonthStat;
use App\Models\WeatherSyncRun;
use App\Services\WeatherMonthStatAiService;
use App\Support\SwissWeatherCantons;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefreshWeatherMonthStatJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 10;

    public int $timeout = 180;

    public function __construct(
        public readonly int $runId,
        public readonly string $regionSlug,
        public readonly int $month,
        public readonly bool $force = false,
        public readonly bool $onlyEmpty = true,
    ) {
        $this->onQueue('weather');
    }

    public function handle(WeatherMonthStatAiService $ai): void
    {
        $run = WeatherSyncRun::query()->find($this->runId);
        if (! $run) {
            return;
        }

        if ($run->status === WeatherSyncRun::STATUS_QUEUED) {
            $run->status = WeatherSyncRun::STATUS_RUNNING;
            $run->save();
        }

        $canton = SwissWeatherCantons::findBySlug($this->regionSlug);
        if ($canton === null) {
            $this->mark($run, 'fail', 'неизвестный кантон '.$this->regionSlug);

            return;
        }

        $label = $canton['name_ru'].' · '.(SwissWeatherCantons::monthNamesRu()[$this->month] ?? $this->month);
        $skipIfFilled = $this->onlyEmpty && ! $this->force;

        if ($skipIfFilled) {
            $existing = WeatherMonthStat::query()
                ->where('region_slug', $this->regionSlug)
                ->where('month', $this->month)
                ->first();
            if ($existing instanceof WeatherMonthStat && $existing->isFilled()) {
                $this->mark($run, 'skip', $label.' — уже есть');

                return;
            }
        }

        try {
            $ai->refreshMonth($canton, $this->month, null, false);
            $this->mark($run, 'ok', '✓ '.$label);
        } catch (WeatherGeminiRateLimitedException $e) {
            $delay = max(40, $e->retryAfterSeconds);
            $this->touchLog($run, '⏳ '.$label.' — лимит, повтор через '.$delay.'с', false);
            $this->release($delay);
        } catch (Throwable $e) {
            $this->mark($run, 'fail', '✗ '.$label.' — '.$e->getMessage());
        }
    }

    public function failed(?Throwable $e): void
    {
        $run = WeatherSyncRun::query()->find($this->runId);
        if (! $run) {
            return;
        }

        $canton = SwissWeatherCantons::findBySlug($this->regionSlug);
        $label = ($canton['name_ru'] ?? $this->regionSlug).' · '
            .(SwissWeatherCantons::monthNamesRu()[$this->month] ?? $this->month);

        $this->mark($run, 'fail', '✗ '.$label.' — '.($e?->getMessage() ?: 'job failed'));
    }

    private function mark(WeatherSyncRun $run, string $kind, string $message): void
    {
        DB::transaction(function () use ($run, $kind, $message): void {
            /** @var WeatherSyncRun $locked */
            $locked = WeatherSyncRun::query()->lockForUpdate()->find($run->id);
            if (! $locked) {
                return;
            }

            if ($kind === 'ok') {
                $locked->succeeded++;
            } elseif ($kind === 'skip') {
                $locked->skipped++;
            } else {
                $locked->failed++;
            }

            $locked->appendLog($message, $kind !== 'fail');

            if ($locked->processed() >= $locked->total) {
                $locked->status = $locked->failed > 0 && $locked->succeeded === 0
                    ? WeatherSyncRun::STATUS_FAILED
                    : WeatherSyncRun::STATUS_DONE;
                $locked->finished_at = now();
                $stamp = $locked->finished_at->format('d.m.Y H:i');
                $locked->last_message = ($locked->source === WeatherSyncRun::SOURCE_SCHEDULE
                    ? 'Автозапуск сработал '.$stamp.'. '
                    : '')
                    .'Готово: ok='.$locked->succeeded
                    .', skip='.$locked->skipped
                    .', fail='.$locked->failed;

                if ($locked->source === WeatherSyncRun::SOURCE_SCHEDULE) {
                    $locked->appendLog('✓ Автозапуск сработал '.$stamp, $locked->status === WeatherSyncRun::STATUS_DONE);
                }
            }

            $locked->save();
        });
    }

    private function touchLog(WeatherSyncRun $run, string $message, bool $ok): void
    {
        DB::transaction(function () use ($run, $message, $ok): void {
            /** @var WeatherSyncRun $locked */
            $locked = WeatherSyncRun::query()->lockForUpdate()->find($run->id);
            if (! $locked) {
                return;
            }
            $locked->appendLog($message, $ok);
            $locked->save();
        });
    }
}
