<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

class OpenAiKeyProbeService
{
    public function __construct(
        private readonly OpenAiService $openAi,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function probeConfiguredKey(): array
    {
        $keys = $this->openAi->collectApiKeys();
        $model = trim((string) config('services.openai.model', 'gpt-4o-mini')) ?: 'gpt-4o-mini';

        if ($keys === []) {
            return [
                'index' => 1,
                'mask' => '—',
                'ok' => false,
                'status' => null,
                'model' => $model,
                'message' => 'OPENAI_API_KEY / OPENAI_API_KEYS пусты в .env',
                'balance' => null,
                'balance_message' => 'Баланс OpenAI через обычный API key получить нельзя. Расходы смотрятся в OpenAI Billing Dashboard.',
            ];
        }

        $result = $this->probeKey($keys[0], 1, $model);
        $result['balance'] = null;
        $result['balance_message'] = 'Баланс OpenAI через обычный API key получить нельзя. Расходы смотрятся в OpenAI Billing Dashboard.';

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function probeKey(string $apiKey, int $index, string $model): array
    {
        $modelsToTry = array_values(array_unique([$model, 'gpt-4o-mini']));
        $last = [
            'index' => $index,
            'mask' => $this->mask($apiKey),
            'ok' => false,
            'status' => null,
            'model' => $model,
            'message' => 'Не удалось подключиться',
        ];

        foreach ($modelsToTry as $tryModel) {
            $last = $this->attemptModel($apiKey, $index, $tryModel);
            if ($last['ok']) {
                return $last;
            }

            if (in_array($last['status'], [401, 403, 429], true)) {
                return $last;
            }
        }

        return $last;
    }

    /**
     * @return array<string, mixed>
     */
    private function attemptModel(string $apiKey, int $index, string $model): array
    {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Reply with exactly: OK'],
                ['role' => 'user', 'content' => 'Test'],
            ],
        ];

        $tokenKey = preg_match('/^gpt-5/i', $model) === 1 ? 'max_completion_tokens' : 'max_tokens';
        $payload[$tokenKey] = 10;

        try {
            $response = Http::timeout(45)
                ->acceptJson()
                ->asJson()
                ->withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            $status = $response->status();
            if ($response->successful()) {
                $answer = trim((string) $response->json('choices.0.message.content'));

                return [
                    'index' => $index,
                    'mask' => $this->mask($apiKey),
                    'ok' => true,
                    'status' => $status,
                    'model' => $model,
                    'message' => 'OK',
                    'answer' => $answer !== '' ? $answer : '(пустой текст)',
                ];
            }

            $body = $response->json();
            $message = is_array($body) ? ($body['error']['message'] ?? $response->body()) : $response->body();

            return [
                'index' => $index,
                'mask' => $this->mask($apiKey),
                'ok' => false,
                'status' => $status,
                'model' => $model,
                'message' => is_string($message) ? mb_substr(trim($message), 0, 300) : 'HTTP '.$status,
            ];
        } catch (Throwable $e) {
            return [
                'index' => $index,
                'mask' => $this->mask($apiKey),
                'ok' => false,
                'status' => null,
                'model' => $model,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function mask(string $key): string
    {
        $len = strlen($key);
        if ($len <= 8) {
            return '***';
        }

        return substr($key, 0, 6).'…'.substr($key, -4);
    }
}
