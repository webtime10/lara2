<?php

namespace Tests\Feature\Api\Plugins;

use App\Models\Language;
use App\Services\Plugins\Weather\WeatherIngestService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WeatherPluginApiTest extends TestCase
{
    private const API_KEY = 'test-weather-api-key';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.plugins.weather.api_key', self::API_KEY);
    }

    /**
     * @return array<string, string>
     */
    private function validPayload(): array
    {
        return [
            'month_name' => 'January',
            'region_name' => 'Tel Aviv',
            'language' => 'en',
        ];
    }

    public function test_request_without_api_key_header_returns_401(): void
    {
        $response = $this->postJson('/api/plugins/weather', $this->validPayload());

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_request_with_invalid_api_key_returns_401(): void
    {
        $response = $this->postJson('/api/plugins/weather', $this->validPayload(), [
            'X-Plugin-Api-Key' => 'wrong-key',
        ]);

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_request_with_valid_api_key_returns_200(): void
    {
        $this->seedLanguagesTable();

        $this->mock(WeatherIngestService::class, function ($mock): void {
            $mock->shouldReceive('accept')
                ->once()
                ->andReturn([
                    'ok' => true,
                    'plugin' => 'weather',
                    'message' => 'Accepted',
                    'from_cache' => false,
                ]);
        });

        $response = $this->postJson('/api/plugins/weather', $this->validPayload(), [
            'X-Plugin-Api-Key' => self::API_KEY,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('plugin', 'weather');
    }

    private function seedLanguagesTable(): void
    {
        if (! Schema::hasTable('languages')) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_03_21_100000_create_languages_table.php',
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
    }
}
