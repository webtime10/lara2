<?php

namespace Tests\Unit\Services\Plugins\Weather;

use App\Models\Language;
use App\Models\WeatherPromt;
use App\Services\Plugins\Weather\WeatherAiService;
use App\Services\Plugins\Weather\WeatherIngestService;
use App\Support\WeatherAiModelChoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class WeatherIngestServiceCacheTest extends TestCase
{
    private const PROMPT_TEXT = 'Test weather prompt for cache unit test.';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Config::set('services.plugins.weather.cache.enabled', true);
        Config::set('services.plugins.weather.cache.ttl_hours', 48);

        $this->seedDatabase();
    }

    public function test_accept_caches_ai_result_and_reuses_it_on_second_call(): void
    {
        $payload = [
            'month_name' => 'January',
            'region_name' => 'Tel Aviv',
            'language' => 'en',
        ];

        $weatherData = [
            'temperature_c' => 18,
            'summary' => 'Mild winter weather',
            'precipitation_mm' => 42,
        ];

        $aiResult = [
            'ok' => true,
            'message' => 'AI generated weather',
            'weather' => $weatherData,
            'model' => WeatherAiModelChoice::GEMINI_FLASH,
            'language' => 'en',
        ];

        $aiMock = Mockery::mock(WeatherAiService::class);
        $aiMock->shouldReceive('run')
            ->once()
            ->with($payload)
            ->andReturn($aiResult);

        $service = new WeatherIngestService($aiMock);

        $firstResponse = $service->accept($payload);
        $cacheKey = $this->expectedCacheKey($payload);

        $this->assertFalse($firstResponse['from_cache']);
        $this->assertSame($weatherData, $firstResponse['weather']);
        $this->assertNotNull(Cache::get($cacheKey), 'First accept() should store AI result in cache.');

        $secondResponse = $service->accept($payload);

        $this->assertTrue($secondResponse['from_cache']);
        $this->assertSame($weatherData, $secondResponse['weather']);
        $this->assertSame($firstResponse['weather'], $secondResponse['weather']);
    }

    private function seedDatabase(): void
    {
        if (! Schema::hasTable('languages')) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_03_21_100000_create_languages_table.php',
            ]);
        }

        if (! Schema::hasTable('weather_promt')) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_06_03_120000_create_weather_promt_table.php',
            ]);
        }

        if (Language::query()->where('code', 'en')->doesntExist()) {
            Language::create([
                'name' => 'English',
                'code' => 'en',
                'locale' => 'en_US',
                'is_active' => true,
                'is_default' => true,
                'status' => true,
            ]);
        }

        WeatherPromt::updateOrCreate(
            ['name' => WeatherAiModelChoice::SETTING_NAME],
            ['content' => WeatherAiModelChoice::GEMINI_FLASH]
        );

        WeatherPromt::updateOrCreate(
            ['name' => 'glavnyy_prompt_en'],
            ['content' => self::PROMPT_TEXT]
        );
    }

    /**
     * Mirrors cache key construction in WeatherIngestService::accept().
     */
    private function expectedCacheKey(array $payload): string
    {
        $language = strtolower(trim((string) ($payload['language'] ?? '')));
        $month = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($payload['month_name'] ?? '')) ?: ''), 'UTF-8');
        $region = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($payload['region_name'] ?? '')) ?: ''), 'UTF-8');
        $modelKey = WeatherAiModelChoice::normalize(WeatherAiModelChoice::GEMINI_FLASH);
        $promptHash = sha1(self::PROMPT_TEXT);

        return 'plugin_weather_result:'.sha1('v1|'.$language.'|'.$month.'|'.$region.'|'.$modelKey.'|'.$promptHash);
    }
}
