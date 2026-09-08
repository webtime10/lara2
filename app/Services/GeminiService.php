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

    /** Модель по умолчанию, если в .env ещё старый gemini-2.5-flash (404 у новых ключей). */
    public const FALLBACK_MODEL = 'gemini-3.6-flash';

    public function __construct(
        protected string $configKeyPath = 'services.gemini.key',
        protected string $configModelPath = 'services.gemini.model',
        protected string $logTag = 'GeminiService',
        protected string $missingKeyEnvHint = 'GEMINI_API_KEY',
        protected string $missingModelEnvHint = 'GEMINI_MODEL',
    ) {}

    public function lastHttpStatus(): ?int
    {
        return $this->lastHttpStatus;
    }

    /**
     * @param  array<string, mixed>|null  $generationConfig
     */
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

        $text = $this->requestGenerateContent($apiKey, $model, $material, $instruction, $timeoutSeconds, $generationConfig);

        // Старые ключи/хосты с gemini-2.5-flash получают 404 — пробуем актуальный Flash.
        if ($text === null && $this->lastHttpStatus === 404 && $model !== self::FALLBACK_MODEL) {
            Log::warning('['.$this->logTag.'] chat: модель '.$model.' недоступна (404), fallback → '.self::FALLBACK_MODEL);
            $text = $this->requestGenerateContent(
                $apiKey,
                self::FALLBACK_MODEL,
                $material,
                $instruction,
                $timeoutSeconds,
                $generationConfig
            );
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>|null  $generationConfig
     */
    private function requestGenerateContent(
        string $apiKey,
        string $model,
        string $material,
        string $instruction,
        int $timeoutSeconds,
        ?array $generationConfig,
    ): ?string {
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

        // Gemini 3: minimal thinking для JSON; на 2.x параметр может дать 400.
        $mergedConfig = is_array($generationConfig) ? $generationConfig : [];
        if (str_starts_with($model, 'gemini-3') && ! isset($mergedConfig['thinkingConfig'])) {
            $mergedConfig['thinkingConfig'] = ['thinkingLevel' => 'minimal'];
        }
        if ($mergedConfig !== []) {
            $payload['generationConfig'] = $mergedConfig;
        }

        try {
            $response = Http::timeout(max(30, $timeoutSeconds))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (Throwable $e) {
            Log::error('['.$this->logTag.'] chat: сеть/HTTP исключение', [
                'message' => $e->getMessage(),
                'model' => $model,
            ]);

            return null;
        }

        $this->lastHttpStatus = $response->status();

        if (! $response->successful()) {
            Log::error('['.$this->logTag.'] chat: неуспешный ответ API', [
                'status' => $this->lastHttpStatus,
                'model' => $model,
                'body' => $this->truncateForLog($response->body()),
            ]);

            return null;
        }

        $text = $this->extractTextFromResponse($response->json());
        if ($text === null) {
            Log::warning('['.$this->logTag.'] chat: пустой текст в candidates', [
                'model' => $model,
                'body' => $this->truncateForLog($response->body(), 2000),
            ]);
        }

        return $text;
    }

    /**
     * Берём все text-части без thought (Gemini 3 может отдать reasoning первым).
     *
     * @param  mixed  $json
     */
    private function extractTextFromResponse(mixed $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        $parts = $json['candidates'][0]['content']['parts'] ?? null;
        if (! is_array($parts) || $parts === []) {
            return null;
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }
            if (! empty($part['thought'])) {
                continue;
            }
            $piece = $part['text'] ?? null;
            if (is_string($piece) && trim($piece) !== '') {
                $chunks[] = trim($piece);
            }
        }

        if ($chunks === []) {
            return null;
        }

        $text = trim(implode("\n", $chunks));

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
