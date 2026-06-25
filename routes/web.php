<?php
use App\Http\Controllers\Admin\MainController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\ManufacturerController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\BudgetCalculatorController;
use App\Http\Controllers\Admin\BudgetApartmentsController;
use App\Http\Controllers\Admin\BudgetEntertainmentsController;
use App\Http\Controllers\Admin\BudgetHotelsController;
use App\Http\Controllers\Admin\FoodImportController;
use App\Http\Controllers\Admin\FoodSampleController;
use App\Http\Controllers\Admin\FoodSourceController;
use App\Http\Controllers\Admin\PromptsWp\BudgetPromptController;
use App\Http\Controllers\Admin\PromptsWp\WeatherPromptController;
use App\Http\Controllers\Admin\ZurichApartmentsController;
use App\Http\Controllers\Admin\ZurichEntertainmentController;
use App\Http\Controllers\Admin\ZurichHotelsController;
use App\Http\Controllers\Admin\ZurichPlacesTestController;
use App\Http\Controllers\Admin\ZurichRestaurantsController;
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
        Route::post('dataforseo/test', [MainController::class, 'testDataForSeo'])->name('dataforseo.test');
        Route::post('databases/test', [MainController::class, 'testDatabases'])->name('databases.test');

        Route::prefix('test/zurich')->name('test.zurich.')->group(function () {
            Route::get('hotels', [ZurichHotelsController::class, 'index'])->name('hotels');
            Route::get('apartments', [ZurichApartmentsController::class, 'index'])->name('apartments');
            Route::post('apartments/fetch', [ZurichApartmentsController::class, 'fetch'])->name('apartments.fetch');
            Route::get('entertainment', [ZurichEntertainmentController::class, 'index'])->name('entertainment');
            Route::post('entertainment/fetch', [ZurichEntertainmentController::class, 'fetch'])->name('entertainment.fetch');
            Route::get('restaurants', [ZurichRestaurantsController::class, 'index'])->name('restaurants');
            Route::post('restaurants/fetch', [ZurichRestaurantsController::class, 'fetch'])->name('restaurants.fetch');
            Route::get('places', [ZurichPlacesTestController::class, 'index'])->name('places');
        });

        // Ресурсы
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('languages', LanguageController::class)->except(['show']);
        Route::resource('products', ProductController::class)->except(['show']);
        Route::resource('manufacturers', ManufacturerController::class)->except(['show']);

        Route::get('budget-calculator', [BudgetCalculatorController::class, 'index'])->name('budget-calculator.index');
        Route::post('budget-calculator/bulk-delete', [BudgetCalculatorController::class, 'bulkDelete'])->name('budget-calculator.bulk-delete');

        Route::prefix('budget/hotels')->name('budget.hotels.')->group(function () {
            Route::get('/', [BudgetHotelsController::class, 'index'])->name('index');
            Route::post('sync-all/complete', [BudgetHotelsController::class, 'completeFullSync'])->name('sync-all.complete');
            Route::post('sync/{slug}', [BudgetHotelsController::class, 'syncRegion'])->name('sync');
            Route::get('{slug}', [BudgetHotelsController::class, 'show'])->name('show');
        });

        Route::prefix('budget/apartments')->name('budget.apartments.')->group(function () {
            Route::get('/', [BudgetApartmentsController::class, 'index'])->name('index');
            Route::post('sync-all/complete', [BudgetApartmentsController::class, 'completeFullSync'])->name('sync-all.complete');
            Route::post('sync/{slug}', [BudgetApartmentsController::class, 'syncRegion'])->name('sync');
            Route::get('{slug}', [BudgetApartmentsController::class, 'show'])->name('show');
        });

        Route::prefix('budget/entertainments')->name('budget.entertainments.')->group(function () {
            Route::get('/', [BudgetEntertainmentsController::class, 'index'])->name('index');
            Route::post('clear-all', [BudgetEntertainmentsController::class, 'clearAll'])->name('clear-all');
            Route::post('clear/{slug}', [BudgetEntertainmentsController::class, 'clearRegion'])->name('clear');
            Route::post('sync-all/complete', [BudgetEntertainmentsController::class, 'completeFullSync'])->name('sync-all.complete');
            Route::post('sync/{slug}', [BudgetEntertainmentsController::class, 'syncRegion'])->name('sync');
            Route::post('gemini/{slug}', [BudgetEntertainmentsController::class, 'sendToGemini'])->name('gemini');
            Route::get('{slug}', [BudgetEntertainmentsController::class, 'show'])->name('show');
        });

        Route::post('food-sources/{food_source}/refresh-ai', [FoodSourceController::class, 'refreshAi'])
            ->name('food-sources.refresh-ai');
        Route::resource('food-sources', FoodSourceController::class)->except(['show']);

        Route::prefix('food-imports')->name('food-imports.')->group(function () {
            Route::get('/', [FoodImportController::class, 'index'])->name('index');
            Route::post('clear-all', [FoodImportController::class, 'clearAll'])->name('clear-all');
            Route::post('clear/{slug}', [FoodImportController::class, 'clearRegion'])->name('clear');
            Route::post('sync/{slug}', [FoodImportController::class, 'syncRegion'])->name('sync');
            Route::post('classify/{slug}', [FoodImportController::class, 'classifyRegion'])->name('classify');
            Route::get('{slug}', [FoodImportController::class, 'show'])->name('show');
        });

        Route::prefix('food-samples')->name('food-samples.')->group(function () {
            Route::post('generate/{slug}', [FoodSampleController::class, 'generateRegion'])->name('generate');
            Route::post('classify/{slug}', [FoodSampleController::class, 'classifyRegion'])->name('classify');
            Route::get('{slug}', [FoodSampleController::class, 'show'])->name('show');
        });

        Route::prefix('prompts-wp')->name('prompts-wp.')->group(function () {
            Route::get('weather', [WeatherPromptController::class, 'edit'])->name('weather');
            Route::post('weather/save', [WeatherPromptController::class, 'save'])->name('weather.save');
            Route::get('budget', [BudgetPromptController::class, 'edit'])->name('budget');
            Route::post('budget/save', [BudgetPromptController::class, 'save'])->name('budget.save');
        });
        
        // Только для админов
        Route::middleware(['admin'])->group(function () {
            Route::resource('roles', RoleController::class)->except(['show']);
            Route::resource('users', UserController::class)->except(['show']);
        });
        
    });