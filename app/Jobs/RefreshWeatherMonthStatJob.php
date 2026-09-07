<?php

namespace App\Jobs;

use App\Exceptions\WeatherGeminiRateLimitedException;
use App\Models\WeatherMonthStat;
use App\Models\WeatherSyncRun;
use App\Services\WeatherMonthStatAiService;
use App\Services\WeatherSyncDispatcher;
use App\Support\WeatherCountry;
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
        public readonly string $country = WeatherCountry::CH,
    ) {
    }

    public function handle(WeatherMonthStatAiService $ai): void
    {
        $run = WeatherSyncRun::query()->find($this->runId);
        if (! $run) {
            return;
        }

        if ($run->status === WeatherSyncRun::STATUS_CANCELLED) {
            return;
        }

        if ($run->status === WeatherSyncRun::STATUS_QUEUED) {
            $run->status = WeatherSyncRun::STATUS_RUNNING;
            $run->save();
        }

        $country = WeatherCountry::normalize($run->country ?: $this->country);
        $regionsClass = WeatherCountry::regionsClass($country);
        $region = $regionsClass::findBySlug($this->regionSlug);
        if ($region === null) {
            $this->mark($run, 'fail', 'неизвестный регион '.$this->regionSlug);

            return;
        }

        $label = $region['name_ru'].' · '.($regionsClass::monthNamesRu()[$this->month] ?? $this->month);
        $skipIfFilled = $this->onlyEmpty && ! $this->force;

        if ($skipIfFilled) {
            $existing = WeatherMonthStat::query()
                ->where('country', $country)
                ->where('region_slug', $this->regionSlug)
                ->where('month', $this->month)
                ->first();
            if ($existing instanceof WeatherMonthStat && $existing->isFilled()) {
                $this->mark($run, 'skip', $label.' — уже есть');

                return;
            }
        }

        try {
            $ai->refreshMonth($region, $this->month, null, false, $country);
            $this->mark($run, 'ok', '✓ '.$label);
        } catch (WeatherGeminiRateLimitedException $e) {
            $run->refresh();
            if ($run->status === WeatherSyncRun::STATUS_CANCELLED) {
                return;
            }
            $delay = max(180, $e->retryAfterSeconds);
            $this->touchLog($run, '⏳ '.$label.' — лимит, повтор через '.$delay.'с', false);
            $this->release($delay);
        } catch (Throwable $e) {
            $run->refresh();
            if ($run->status === WeatherSyncRun::STATUS_CANCELLED) {
                return;
            }
            if ($this->shouldRetry($e)) {
                $delay = $this->retryDelaySeconds($e);
                $this->touchLog(
                    $run,
                    '⏳ '.$label.' — повтор '.$this->attempts().'/'.$this->tries
                        .' через '.$delay.'с: '.$e->getMessage(),
                    false
                );
                $this->release($delay);

                return;
            }

            $this->clearCellAfterForceFail($country);
            $this->mark($run, 'fail', '✗ '.$label.' — '.$e->getMessage());
        }
    }

    public function failed(?Throwable $e): void
    {
        $run = WeatherSyncRun::query()->find($this->runId);
        if (! $run || $run->status === WeatherSyncRun::STATUS_CANCELLED) {
            return;
        }

        $country = WeatherCountry::normalize($run->country ?: $this->country);
        $regionsClass = WeatherCountry::regionsClass($country);
        $region = $regionsClass::findBySlug($this->regionSlug);
        $label = ($region['name_ru'] ?? $this->regionSlug).' · '
            .($regionsClass::monthNamesRu()[$this->month] ?? $this->month);

        $this->clearCellAfterForceFail($country);
        $this->mark($run, 'fail', '✗ '.$label.' — '.($e?->getMessage() ?: 'job failed'));
    }

    private function clearCellAfterForceFail(string $country): void
    {
        if (! $this->force) {
            return;
        }

        WeatherMonthStat::query()
            ->where('country', $country)
            ->where('region_slug', $this->regionSlug)
            ->where('month', $this->month)
            ->delete();
    }

    private function shouldRetry(Throwable $e): bool
    {
        if ($this->attempts() >= $this->tries) {
            return false;
        }

        $msg = mb_strtolower($e->getMessage());

        return str_contains($msg, 'http 503')
            || str_contains($msg, 'http 429')
            || str_contains($msg, 'http 404')
            || str_contains($msg, 'http 500')
            || str_contains($msg, 'http 502')
            || str_contains($msg, 'пустой ответ')
            || str_contains($msg, 'unavailable')
            || str_contains($msg, 'timeout')
            || str_contains($msg, 'timed out')
            || str_contains($msg, 'сеть')
            || str_contains($msg, 'невалидный json');
    }

    private function retryDelaySeconds(?Throwable $e = null): int
    {
        $msg = mb_strtolower((string) ($e?->getMessage() ?? ''));
        $attempt = max(1, $this->attempts());

        if (
            str_contains($msg, '429')
            || str_contains($msg, 'квот')
            || str_contains($msg, 'rate')
            || str_contains($msg, 'resource_exhausted')
        ) {
            return min(900, 180 * $attempt);
        }

        if (str_contains($msg, '404') || str_contains($msg, 'пустой ответ')) {
            return min(600, 120 * $attempt);
        }

        return min(480, 90 * $attempt);
    }

    private function mark(WeatherSyncRun $run, string $kind, string $message): void
    {
        $shouldRefill = false;

        DB::transaction(function () use ($run, $kind, $message, &$shouldRefill): void {
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

            if ($locked->status === WeatherSyncRun::STATUS_CANCELLED) {
                $locked->save();

                return;
            }

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

                if ($locked->failed > 0) {
                    $shouldRefill = true;
                    $mins = (int) round(WeatherSyncDispatcher::REFILL_DELAY_SECONDS / 60);
                    $locked->appendLog(
                        'Автодозаливка пустых через '.$mins.' мин (волнами, пока не зальётся)',
                        true
                    );
                }
            }

            $locked->save();
        });

        if ($shouldRefill) {
            $fresh = WeatherSyncRun::query()->find($run->id);
            if ($fresh && $fresh->status !== WeatherSyncRun::STATUS_CANCELLED) {
                $country = WeatherCountry::normalize($fresh->country ?: $this->country);
                $nextWave = 1 + WeatherSyncRun::query()
                    ->where('country', $country)
                    ->where('source', WeatherSyncRun::SOURCE_REFILL)
                    ->where('started_at', '>=', now()->subHours(12))
                    ->count();

                QueueWeatherEmptyRefillJob::dispatch($fresh->id, $nextWave, $country)
                    ->delay(now()->addSeconds(WeatherSyncDispatcher::REFILL_DELAY_SECONDS));
            }
        }
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
