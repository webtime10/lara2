<?php

namespace App\Services;

use App\Support\GeminiApiKeys;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Общий клиент Gemini API (Flash). Плагины и воркеры инжектят этот сервис.
 */
class GeminiService
{
    protected ?int $lastHttpStatus = null;

    public function __construct(
        protected string $configKeyPath = 'services.gemini.key',
        protected string $configModelPath = 'services.gemini.model',
        protected string $logTag = 'GeminiService',
        protected string $missingKeyEnvHint = 'GEMINI_API_KEY',
        protected string $missingModelEnvHint = 'GEMINI_MODEL',
    ) {}

    /**
     * @param  array<string, mixed>|null  $generationConfig
     */
    public function lastHttpStatus(): ?int
    {
        return $this->lastHttpStatus;
    }

    public function chat(string $material, string $instruction, int $timeoutSeconds = 180, ?array $generationConfig = null): ?string
    {
        $this->lastHttpStatus = null;
        $instruction = trim($instruction);
        $material = trim($material);

        if ($instruction === '' || $material === '') {
            Log::warning('['.$this->logTag.'] chat: пустая инструкция или материал', [
                'instruction_len' => mb_strlen($instruction),
                'material_len' => mb_strlen($material),
            ]);

            return null;
        }

        $apiKey = $this->resolveApiKey();
        if ($apiKey === '') {
            Log::error('['.$this->logTag.'] chat: не задан '.$this->missingKeyEnvHint);

            return null;
        }

        $model = trim((string) config($this->configModelPath, ''));
        if ($model === '') {
            Log::error('['.$this->logTag.'] chat: пустой '.$this->missingModelEnvHint);

            return null;
        }

        $userContent = $instruction."\n\n--- SOURCE TEXT ---\n".$material;
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
            .rawurlencode($model)
            .':generateContent?key='.$apiKey;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $userContent]],
                ],
            ],
        ];
        if ($generationConfig !== null && $generationConfig !== []) {
            $payload['generationConfig'] = $generationConfig;
        }

        try {
            $response = Http::timeout(max(30, $timeoutSeconds))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (Throwable $e) {
            Log::error('['.$this->logTag.'] chat: сеть/HTTP исключение', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $response->successful()) {
            $this->lastHttpStatus = $response->status();
            Log::error('['.$this->logTag.'] chat: неуспешный ответ API', [
                'status' => $this->lastHttpStatus,
                'body' => $this->truncateForLog($response->body()),
            ]);

            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        if (! is_string($text)) {
            return null;
        }

        $text = trim($text);

        return $text !== '' ? $text : null;
    }

    public function defaultChatTimeout(): int
    {
        return max(60, (int) config('services.gemini.chat_timeout', 900));
    }

    private function resolveApiKey(): string
    {
        return GeminiApiKeys::nextRoundRobin($this->configKeyPath);
    }

    private function truncateForLog(string $body, int $max = 4000): string
    {
        if (mb_strlen($body) <= $max) {
            return $body;
        }

        return mb_substr($body, 0, $max).'…';
    }
}
