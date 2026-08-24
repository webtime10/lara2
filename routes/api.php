<?php

use App\Http\Controllers\Api\Plugins\RagController;
use App\Http\Controllers\Api\Plugins\BudgetController;
use App\Http\Controllers\Api\Plugins\IdealRegionController;
use App\Http\Controllers\Api\Plugins\WeatherController;
use Illuminate\Support\Facades\Route;

/*
| Входящие webhook от WordPress-плагинов.
| Каждый плагин — свой URL. Заголовок: X-Plugin-Api-Key (или X-Laravel-Api-Key).
*/
Route::prefix('plugins')->group(function () {
    Route::post('weather', [WeatherController::class, 'store'])
        ->middleware('plugin.api:weather')
        ->name('api.plugins.weather.store');

    Route::post('budget', [BudgetController::class, 'store'])
        ->middleware('plugin.api:budget')
        ->name('api.plugins.budget.store');

    Route::get('budget/status/{quizAnswerId}', [BudgetController::class, 'status'])
        ->middleware('plugin.api:budget')
        ->name('api.plugins.budget.status');

    Route::post('ideal_region', [IdealRegionController::class, 'store'])
        ->middleware('plugin.api:ideal_region')
        ->name('api.plugins.ideal_region.store');

    Route::prefix('rag')->group(function () {
        Route::post('query', [RagController::class, 'query'])
            ->middleware('plugin.api:rag')
            ->name('api.plugins.rag.query');

        Route::post('upsert', [RagController::class, 'upsert'])
            ->middleware('plugin.api:rag')
            ->name('api.plugins.rag.upsert');

        Route::post('clear', [RagController::class, 'clear'])
            ->middleware('plugin.api:rag')
            ->name('api.plugins.rag.clear');

        Route::post('fetch', [RagController::class, 'fetch'])
            ->middleware('plugin.api:rag')
            ->name('api.plugins.rag.fetch');

        Route::get('stats', [RagController::class, 'stats'])
            ->middleware('plugin.api:rag')
            ->name('api.plugins.rag.stats');
    });
});
