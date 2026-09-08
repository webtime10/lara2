<?php

namespace App\Services\Plugins\Weather;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Приём запроса погоды от WordPress: сначала готовая клетка из БД, без Gemini.
 */
class WeatherIngestService
{
    public const PLUGIN = 'weather';

    public function __construct(
        private WeatherMonthStatLookupService $lookup,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function accept(array $payload): array
    {
        $requestId = (string) Str::uuid();

        Log::info('[plugin:weather] incoming', [
            'request_id' => $requestId,
            'month_name' => $payload['month_name'] ?? null,
            'region_name' => $payload['region_name'] ?? null,
            'language' => $payload['language'] ?? null,
            'country' => $payload['country'] ?? null,
            'month' => $payload['month'] ?? null,
        ]);

        $result = $this->lookup->find($payload);

        $response = [
            'ok' => (bool) ($result['ok'] ?? false),
            'plugin' => self::PLUGIN,
            'request_id' => $requestId,
            'message' => (string) ($result['message'] ?? ''),
            'model' => $result['model'] ?? null,
            'language' => $result['language'] ?? ($payload['language'] ?? null),
            'from_cache' => false,
            'from_db' => (bool) ($result['from_db'] ?? false),
            'received' => $payload,
        ];

        if (! empty($result['weather']) && is_array($result['weather'])) {
            $response['weather'] = $result['weather'];
        }

        return $response;
    }
}
