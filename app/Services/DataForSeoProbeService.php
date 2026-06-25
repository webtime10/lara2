<?php

namespace App\Services;

use Throwable;

class DataForSeoProbeService
{
    public function __construct(
        private readonly DataForSeoClient $client,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     login_masked: string,
     *     credentials_ok: bool,
     *     api_reachable: bool,
     *     response_ms: int|null,
     *     endpoint: string,
     *     tested_at: string,
     *     balance?: float|int|string|null,
     *     currency?: string|null
     * }
     */
    public function probe(): array
    {
        $testedAt = now()->toIso8601String();
        $endpoint = DataForSeoClient::USER_DATA_URL;

        if (! $this->client->credentialsConfigured()) {
            return [
                'ok' => false,
                'message' => 'DATAFORSEO_LOGIN или DATAFORSEO_PASSWORD не заданы в .env',
                'login_masked' => '—',
                'credentials_ok' => false,
                'api_reachable' => false,
                'response_ms' => null,
                'endpoint' => $endpoint,
                'tested_at' => $testedAt,
            ];
        }

        $startedAt = microtime(true);

        try {
            $data = $this->client->get($endpoint);
            $user = $data['tasks'][0]['result'][0] ?? $data['tasks'][0]['result'] ?? [];

            if (! is_array($user)) {
                $user = [];
            }

            return [
                'ok' => true,
                'message' => 'Подключение к DataForSEO активно',
                'login_masked' => $this->client->maskLogin(),
                'credentials_ok' => true,
                'api_reachable' => true,
                'response_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'endpoint' => $endpoint,
                'tested_at' => $testedAt,
                'balance' => $user['money']['balance'] ?? $user['balance'] ?? null,
                'currency' => $user['money']['currency'] ?? $user['currency'] ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'login_masked' => $this->client->maskLogin(),
                'credentials_ok' => true,
                'api_reachable' => false,
                'response_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'endpoint' => $endpoint,
                'tested_at' => $testedAt,
            ];
        }
    }
}
