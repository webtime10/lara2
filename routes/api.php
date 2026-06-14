<?php

use App\Http\Controllers\Api\Plugins\BudgetController;
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
});
