<?php

namespace App\Services\Plugins\Budget;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BudgetIngestService
{
    public const PLUGIN = 'budget';

    public function __construct(
        private BudgetAiService $ai,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function accept(array $payload): array
    {
        $requestId = (string) Str::uuid();

        Log::info('[plugin:budget] incoming', [
            'request_id' => $requestId,
            'language' => $payload['language'] ?? null,
        ]);

        $aiResult = $this->ai->run($payload);

        $response = [
            'ok' => (bool) ($aiResult['ok'] ?? false),
            'plugin' => self::PLUGIN,
            'request_id' => $requestId,
            'message' => (string) ($aiResult['message'] ?? ''),
            'model' => $aiResult['model'] ?? null,
            'language' => $aiResult['language'] ?? ($payload['language'] ?? null),
            'from_cache' => false,
            'received' => $payload,
        ];

        if (! empty($aiResult['budget']) && is_array($aiResult['budget'])) {
            $response['budget'] = $aiResult['budget'];
        }

        return $response;
    }
}
