<?php

namespace Tests\Feature\Api\Plugins;

use App\Models\Language;
use App\Services\Plugins\Budget\BudgetIngestService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BudgetPluginApiTest extends TestCase
{
    private const API_KEY = 'test-budget-api-key';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.plugins.budget.api_key', self::API_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'language' => 'en',
            'answers' => [
                'catalog' => [
                    'trip_dates' => ['dateMode' => 'approx', 'dateFrom' => '2026-06-01', 'dateTo' => '2026-06-10'],
                    'travelers' => ['quantity' => '2'],
                    'region' => ['region' => 'Galilee'],
                ],
            ],
        ];
    }

    public function test_request_without_api_key_header_returns_401(): void
    {
        $response = $this->postJson('/api/plugins/budget', $this->validPayload());

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthorized.']);
    }

    public function test_request_with_valid_api_key_returns_200(): void
    {
        $this->seedLanguagesTable();

        $this->mock(BudgetIngestService::class, function ($mock): void {
            $mock->shouldReceive('accept')
                ->once()
                ->andReturn([
                    'ok' => true,
                    'plugin' => 'budget',
                    'message' => 'Accepted',
                    'from_cache' => false,
                ]);
        });

        $response = $this->postJson('/api/plugins/budget', $this->validPayload(), [
            'X-Plugin-Api-Key' => self::API_KEY,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('plugin', 'budget');
    }

    private function seedLanguagesTable(): void
    {
        if (! Schema::hasTable('languages')) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_03_21_100000_create_languages_table.php',
            ]);
        }

        if (! Schema::hasTable('quiz_answers')) {
            $this->artisan('migrate', [
                '--path' => 'database/migrations/2026_06_05_120000_create_quiz_answers_table.php',
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
