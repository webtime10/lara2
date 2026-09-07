<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Регистрация кастомных middleware
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminAccess::class,
            'plugin.api' => \App\Http\Middleware\VerifyPluginApiKey::class,
        ]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // 1-го числа: полная заливка заново; при fail — автодозаливка пустых волнами
        $schedule->command('weather:sync --force --scheduled --country=ch')
            ->monthlyOn(1, '03:00')
            ->withoutOverlapping();

        $schedule->command('weather:sync --force --scheduled --country=jp')
            ->monthlyOn(1, '04:00')
            ->withoutOverlapping();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
