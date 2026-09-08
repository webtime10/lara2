<?php

namespace Tests\Unit\Services\Plugins\Weather;

use App\Models\WeatherMonthStat;
use App\Services\Plugins\Weather\WeatherIngestService;
use App\Services\Plugins\Weather\WeatherMonthStatLookupService;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class WeatherIngestServiceCacheTest extends TestCase
{
    public function test_accept_returns_db_lookup_result_without_ai(): void
    {
        $payload = [
            'month_name' => 'January',
            'region_name' => 'Цуг',
            'language' => 'ar',
            'country' => 'ch',
        ];

        $weatherData = [
            'temperature' => '+1° -2°|-4° -8°',
            'temperature_day' => '+1° -2°',
            'temperature_night' => '-4° -8°',
            'precipitation' => 'متوسط',
            'sunny_days' => '5-8',
            'season' => 'شتاء',
            'summary' => '',
        ];

        $lookup = Mockery::mock(WeatherMonthStatLookupService::class);
        $lookup->shouldReceive('find')
            ->once()
            ->with($payload)
            ->andReturn([
                'ok' => true,
                'message' => '',
                'weather' => $weatherData,
                'model' => 'weather_month_stats',
                'language' => 'ar',
                'from_db' => true,
            ]);

        $service = new WeatherIngestService($lookup);
        $response = $service->accept($payload);

        $this->assertTrue($response['ok']);
        $this->assertTrue($response['from_db']);
        $this->assertFalse($response['from_cache']);
        $this->assertSame($weatherData, $response['weather']);
        $this->assertSame('weather_month_stats', $response['model']);
    }

    public function test_lookup_finds_filled_stat_by_region_and_month_name(): void
    {
        if (! Schema::hasTable('weather_month_stats')) {
            $this->markTestSkipped('weather_month_stats missing');
        }

        WeatherMonthStat::query()->updateOrCreate(
            [
                'country' => 'ch',
                'region_slug' => 'zug',
                'month' => 1,
            ],
            [
                'region_name_ru' => 'Цуг',
                'average_temperature' => '+2° +0°|-3° -7°',
                'precipitation' => 'средний',
                'sunny_days' => '6',
                'season' => 'зима',
                'ai_model' => 'test',
                'last_checked' => now(),
            ]
        );

        $lookup = new WeatherMonthStatLookupService();
        $result = $lookup->find([
            'country' => 'ch',
            'region_name' => 'Цуг',
            'month_name' => 'January',
            'language' => 'en',
            'month' => 9999,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['from_db'] ?? false);
        $this->assertSame('+2° +0°', $result['weather']['temperature_day']);
        $this->assertSame('-3° -7°', $result['weather']['temperature_night']);
    }
}
