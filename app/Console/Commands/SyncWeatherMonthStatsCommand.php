<?php

namespace App\Console\Commands;

use App\Services\WeatherSyncDispatcher;
use Illuminate\Console\Command;

class SyncWeatherMonthStatsCommand extends Command
{
    protected $signature = 'weather:sync
                            {--force : Перезаписать все клетки (игнор уже заполненных)}
                            {--only-empty : Только пустые клетки (по умолчанию, если нет --force)}
                            {--slug= : Только один кантон (slug)}
                            {--scheduled : Запуск из планировщика (для метки «сработало» в админке)}';

    protected $description = 'Поставить в очередь обновление погоды (кантон × месяц) через бесплатный Gemini';

    public function handle(WeatherSyncDispatcher $dispatcher): int
    {
        $force = (bool) $this->option('force');
        $onlyEmpty = ! $force;
        if ($this->option('only-empty')) {
            $onlyEmpty = true;
        }

        $slug = $this->option('slug');
        $slug = is_string($slug) && trim($slug) !== '' ? trim($slug) : null;
        $source = $this->option('scheduled')
            ? \App\Models\WeatherSyncRun::SOURCE_SCHEDULE
            : \App\Models\WeatherSyncRun::SOURCE_MANUAL;

        $result = $dispatcher->dispatchAll($force, $onlyEmpty, $slug, $source);
        $run = $result['run'];

        $this->info('Run '.$run->uuid.': queued='.$result['queued'].', status='.$run->status.', source='.$source);
        $this->line('Нужен воркер: php artisan queue:work --queue=weather');

        return self::SUCCESS;
    }
}
