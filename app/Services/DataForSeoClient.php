<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class DataForSeoClient
{
    public const USER_DATA_URL = 'https://api.dataforseo.com/v3/appendix/user_data';

    public const HOTEL_SEARCHES_URL = 'https://api.dataforseo.com/v3/business_data/google/hotel_searches/live';

    public const HOTEL_INFO_URL = 'https://api.dataforseo.com/v3/business_data/google/hotel_info/live/advanced';

    public const GOOGLE_IMAGES_URL = 'https://api.dataforseo.com/v3/serp/google/images/live/advanced';

    public const GOOGLE_MAPS_URL = 'https://api.dataforseo.com/v3/serp/google/maps/live/advanced';

    public function credentialsConfigured(): bool
    {
        return $this->login() !== '' && $this->password() !== '';
    }

    public function login(): string
    {
        return trim((string) config('services.dataforseo.login'));
    }

    public function password(): string
    {
        return trim((string) config('services.dataforseo.password'));
    }

    public function maskLogin(?string $login = null): string
    {
        $login = $login ?? $this->login();

        if ($login === '') {
            return '—';
        }

        if (strlen($login) <= 3) {
            return str_repeat('*', strlen($login));
        }

        return substr($login, 0, 3).str_repeat('*', max(3, strlen($login) - 3));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $url, int $timeoutSeconds = 60): array
    {
        $response = Http::withBasicAuth($this->login(), $this->password())
            ->timeout($timeoutSeconds)
            ->acceptJson()
            ->get($url);

        return $this->decodeResponse($response->status(), $response->body(), $response->json() ?? []);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     * @return array<string, mixed>
     */
    public function post(string $url, array $payload, int $timeoutSeconds = 120): array
    {
        $response = Http::withBasicAuth($this->login(), $this->password())
            ->timeout($timeoutSeconds)
            ->acceptJson()
            ->post($url, $payload);

        return $this->decodeResponse($response->status(), $response->body(), $response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function decodeResponse(int $httpStatus, string $body, array $data): array
    {
        if ($httpStatus < 200 || $httpStatus >= 300) {
            throw new \RuntimeException(
                'DataForSEO HTTP '.$httpStatus.': '.$body
            );
        }

        $taskStatus = $data['tasks'][0]['status_code'] ?? null;

        if ($taskStatus !== null && (int) $taskStatus !== 20000) {
            throw new \RuntimeException(
                'DataForSEO task error '.$taskStatus.': '.($data['tasks'][0]['status_message'] ?? 'unknown')
            );
        }

        $statusCode = $data['status_code'] ?? null;

        if ($statusCode !== null && (int) $statusCode !== 20000) {
            throw new \RuntimeException(
                'DataForSEO error '.$statusCode.': '.($data['status_message'] ?? 'unknown')
            );
        }

        return $data;
    }
}
