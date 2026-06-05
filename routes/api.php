<?php

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
});
