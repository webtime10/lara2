<?php
use App\Http\Controllers\Admin\MainController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\ManufacturerController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\PromptsWp\WeatherPromptController;
use App\Http\Controllers\Auth\AdminLoginController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// --- АВТОРИЗАЦИЯ ---
// Страница входа и обработка формы
Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AdminLoginController::class, 'login'])->name('login.post');
Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');


// --- АДМИНКА (Защищенная) ---
// Middleware 'auth' проверяет, залогинен ли пользователь вообще.
// Если ты уже создал Middleware 'AdminAccess' (про который мы говорили раньше), 
// то добавь его сюда: ->middleware(['auth', 'admin'])
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth']) 
    ->group(function () {
        
        // Главная страница админки
        Route::get('/', [MainController::class, 'index'])->name('index');
        Route::post('gemini-keys/test-all', [MainController::class, 'testAllGeminiKeys'])->name('gemini-keys.test-all');
        Route::post('gemini-keys/test-next', [MainController::class, 'testNextGeminiKey'])->name('gemini-keys.test-next');
        Route::post('gemini-keys/reset-rotation', [MainController::class, 'resetGeminiKeyRotation'])->name('gemini-keys.reset-rotation');

        // Ресурсы
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('languages', LanguageController::class)->except(['show']);
        Route::resource('products', ProductController::class)->except(['show']);
        Route::resource('manufacturers', ManufacturerController::class)->except(['show']);

        Route::prefix('prompts-wp')->name('prompts-wp.')->group(function () {
            Route::get('weather', [WeatherPromptController::class, 'edit'])->name('weather');
            Route::post('weather/save', [WeatherPromptController::class, 'save'])->name('weather.save');
        });
        
        // Только для админов
        Route::middleware(['admin'])->group(function () {
            Route::resource('roles', RoleController::class)->except(['show']);
            Route::resource('users', UserController::class)->except(['show']);
        });
        
    });