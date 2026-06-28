<?php

namespace App\Services;

use App\Support\GeminiApiKeys;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Проверка отдельных ключей Gemini из админки (без round-robin).
 */
class GeminiKeyProbeService
{
    /**
     * @return array{
     *     index: int,
     *     mask: string,
     *     ok: bool,
     *     status: int|null,
     *     model: string,
     *     message: string,
     *     answer?: string
     * }
     */
    public function probeKey(string $apiKey, int $index = 1, ?string $model = null): array
    {
        $model = trim((string) ($model ?? config('services.gemini.model', 'gemini-2.5-flash')));
        if ($model === '') {
            $model = 'gemini-2.5-flash';
        }

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => 'Reply with exactly: OK'],
                    ],
                ],
            ],
        ];

        $result = $this->postGenerate($apiKey, $model, $payload, 45);

        return [
            'index' => $index,
            'mask' => GeminiApiKeys::mask($apiKey),
            'ok' => $result['ok'],
            'status' => $result['status'],
            'model' => $result['model'],
            'message' => $result['message'],
            'answer' => $result['answer'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function probeAllConfigured(): array
    {
        $keys = GeminiApiKeys::fromConfig();
        $out = [];

        foreach ($keys as $i => $key) {
            $out[] = $this->probeKey($key, $i + 1);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function probeConfiguredProKey(): array
    {
        $keys = GeminiApiKeys::fromConfig('services.gemini_pro.key');
        if ($keys === []) {
            return [
                'index' => 1,
                'mask' => '—',
                'ok' => false,
                'status' => null,
                'model' => (string) config('services.gemini_pro.model', 'gemini-2.5-pro'),
                'message' => 'GEMINI_PRO_API_KEY пуст в .env',
                'balance' => null,
                'balance_message' => 'Баланс Gemini API через GEMINI_PRO_API_KEY получить нельзя: у Google нет такого billing endpoint для Generative Language API key.',
            ];
        }

        $result = $this->probeKey(
            $keys[0],
            1,
            (string) config('services.gemini_pro.model', 'gemini-2.5-pro')
        );

        $result['balance'] = null;
        $result['balance_message'] = 'Баланс Gemini API через GEMINI_PRO_API_KEY получить нельзя: Google показывает расходы в Cloud Console / AI Studio Billing, но не отдаёт остаток денег этим API key.';

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: int|null, model: string, message: string, answer?: string}
     */
    private function postGenerate(string $apiKey, string $model, array $payload, int $timeoutSeconds): array
    {
        $modelsToTry = array_values(array_unique([$model, 'gemini-2.5-flash', 'gemini-2.0-flash']));

        $last = [
            'ok' => false,
            'status' => null,
            'model' => $model,
            'message' => 'Не удалось подключиться',
        ];

        foreach ($modelsToTry as $tryModel) {
            $last = $this->attemptModel($apiKey, $tryModel, $payload, $timeoutSeconds);
            if ($last['ok']) {
                return $last;
            }
            if ($last['status'] === 429) {
                return $last;
            }
        }

        return $last;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: int|null, model: string, message: string, answer?: string}
     */
    private function attemptModel(string $apiKey, string $model, array $payload, int $timeoutSeconds): array
    {
        $base = 'https://generativelanguage.googleapis.com/v1beta/models/'
            .rawurlencode($model)
            .':generateContent';

        try {
            $response = Http::timeout($timeoutSeconds)
                ->acceptJson()
                ->asJson()
                ->post($base.'?key='.$apiKey, $payload);

            if (! $response->successful() && $this->shouldTryBearer($apiKey, $response->status())) {
                $response = Http::timeout($timeoutSeconds)
                    ->acceptJson()
                    ->asJson()
                    ->withToken($apiKey)
                    ->post($base, $payload);
            }

            $status = $response->status();

            if ($response->successful()) {
                $text = trim((string) $response->json('candidates.0.content.parts.0.text'));

                return [
                    'ok' => true,
                    'status' => $status,
                    'model' => $model,
                    'message' => 'OK',
                    'answer' => $text !== '' ? $text : '(пустой текст)',
                ];
            }

            $body = $response->json();
            $err = is_array($body) ? ($body['error']['message'] ?? $response->body()) : $response->body();
            $err = is_string($err) ? mb_substr(trim($err), 0, 300) : 'HTTP '.$status;

            return [
                'ok' => false,
                'status' => $status,
                'model' => $model,
                'message' => $err,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'status' => null,
                'model' => $model,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function shouldTryBearer(string $apiKey, ?int $status): bool
    {
        if (! str_starts_with($apiKey, 'AQ.')) {
            return false;
        }

        return $status === null || in_array($status, [401, 403, 400], true);
    }
}
