<?php

namespace Tests\Unit\Services\Plugins\Weather;

use App\Models\Language;
use App\Models\WeatherPromt;
use App\Services\GeminiProService;
use App\Services\GeminiService;
use App\Services\OpenAiService;
use App\Services\Plugins\Weather\WeatherAiService;
use App\Support\WeatherAiModelChoice;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class WeatherAiServiceFallbackTest extends TestCase
{
    private const PROMPT_TEXT = 'Describe weather for {month_name} in {region_name}. Language: {language}.';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDatabase();
    }

    public function test_falls_back_to_openai_mini_when_gemini_returns_503(): void
    {
        $validJson = json_encode([
            'temperature' => '12°C',
            'precipitation' => '80 mm',
            'sunny_days' => '10',
            'season' => 'spring',
            'summary' => 'Mild spring weather',
        ], JSON_THROW_ON_ERROR);

        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('chat')->once()->andReturn(null);
        $gemini->shouldReceive('lastHttpStatus')->andReturn(503);

        $geminiPro = Mockery::mock(GeminiProService::class);

        $openAi = Mockery::mock(OpenAiService::class);
        $openAi->shouldReceive('askOpenAiWithModel')
            ->once()
            ->withArgs(function (string $instruction, string $material, string $model, string $logCallSite): bool {
                return $model === 'gpt-4o-mini'
                    && str_contains($material, 'Geneva')
                    && str_contains($instruction, 'Describe weather');
            })
            ->andReturn($validJson);

        $service = new WeatherAiService($gemini, $geminiPro, $openAi);

        $result = $service->run([
            'month_name' => 'April',
            'region_name' => 'Geneva',
            'language' => 'en',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(WeatherAiModelChoice::OPENAI_GPT_4O_MINI, $result['model']);
        $this->assertSame('Mild spring weather', $result['message']);
        $this->assertSame('12°C', $result['weather']['temperature']);
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
}
