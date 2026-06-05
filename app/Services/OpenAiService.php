<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use OpenAI;
use OpenAI\Client;
use OpenAI\Exceptions\ErrorException;
use OpenAI\Exceptions\RateLimitException;
use OpenAI\Exceptions\UnserializableResponse;
use RuntimeException;
use Throwable;

/**
 * Общий клиент OpenAI. Плагины вызывают chat() / askOpenAi().
 */
class OpenAiService
{
    /**
     * @return list<string>
     */
    public function collectApiKeys(): array
    {
        $seen = [];
        $out = [];
        $push = function (string $k) use (&$seen, &$out): void {
            $k = trim($k, " \t\n\r\0\x0B\"'");
            if ($k === '' || isset($seen[$k])) {
                return;
            }
            $seen[$k] = true;
            $out[] = $k;
        };

        $push((string) config('services.openai.key', ''));
        foreach (explode(',', (string) config('services.openai.keys_csv', '')) as $part) {
            $push(trim($part));
        }

        return $out;
    }

    public function askOpenAi(string $prompt, string $sourceText, string $logCallSite = 'askOpenAi', string $purpose = 'generation'): ?string
    {
        return $this->askOpenAiWithModel(
            $prompt,
            $sourceText,
            $this->resolvedModel($purpose),
            $logCallSite
        );
    }

    public function askOpenAiWithModel(string $prompt, string $sourceText, string $model, string $logCallSite = 'askOpenAiWithModel'): ?string
    {
        $prompt = trim($prompt);
        $sourceText = trim($sourceText);
        $model = trim($model);

        if ($prompt === '' || $sourceText === '' || $model === '') {
            return null;
        }

        $maxOut = (int) config('services.openai.max_output_tokens', 16384);
        $userContent = $prompt."\n\n--- SOURCE TEXT ---\n".$sourceText;

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Следуй инструкциям пользователя точно. Возвращай только запрошенный результат.',
                ],
                ['role' => 'user', 'content' => $userContent],
            ],
        ];
        $this->applyOutputTokenLimit($payload, $maxOut);

        $response = $this->chatWithKeyRotation($payload, $logCallSite.':'.$model);
        if ($response === null) {
            return null;
        }

        $content = $response->choices[0]->message->content ?? null;
        if (! is_string($content)) {
            return null;
        }

        $content = trim($content);

        return $content !== '' ? $content : null;
    }

    public function chat(string $material, string $instruction): ?string
    {
        return $this->askOpenAi(trim($instruction), trim($material), 'openai.chat', 'generation');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function chatWithKeyRotation(array $payload, string $step): mixed
    {
        $keys = $this->collectApiKeys();
        if ($keys === []) {
            Log::error('[OpenAiService] no API keys configured');

            return null;
        }

        foreach ($keys as $index => $apiKey) {
            $client = OpenAI::client($apiKey);
            $outcome = $this->tryChatCompletionWithRetries($client, $payload, $step);
            if ($outcome['response'] !== null) {
                return $outcome['response'];
            }
            if (! $outcome['try_next_key']) {
                return null;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{response: mixed, try_next_key: bool, reason?: string}
     */
    private function tryChatCompletionWithRetries(Client $client, array $payload, string $step): array
    {
        $maxAttempts = (int) config('services.openai.rate_limit_retries', 8);
        $baseWait = (int) config('services.openai.rate_limit_wait_base_sec', 10);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return ['response' => $client->chat()->create($payload), 'try_next_key' => false];
            } catch (RateLimitException $e) {
                sleep($baseWait * $attempt);
            } catch (ErrorException $e) {
                $code = $e->getStatusCode();
                if (in_array($code, [401, 403], true)) {
                    return ['response' => null, 'try_next_key' => true, 'reason' => 'http_'.$code];
                }
                if ($code === 429) {
                    sleep($baseWait * $attempt);
                    continue;
                }

                return ['response' => null, 'try_next_key' => false, 'reason' => 'http_'.$code];
            } catch (Throwable $e) {
                if ($e instanceof UnserializableResponse && $attempt < $maxAttempts) {
                    sleep(min(2 * $attempt, 8));
                    continue;
                }

                return ['response' => null, 'try_next_key' => false, 'reason' => 'exception'];
            }
        }

        return ['response' => null, 'try_next_key' => true, 'reason' => 'rate_limit_exhausted'];
    }

    /**
     * @param  'generation'|'extraction'  $purpose
     */
    private function resolvedModel(string $purpose = 'generation'): string
    {
        $configKey = $purpose === 'extraction'
            ? 'services.openai.extraction_model'
            : 'services.openai.model';
        $envHint = $purpose === 'extraction' ? 'OPENAI_EXTRACTION_MODEL' : 'OPENAI_MODEL';
        $model = trim((string) config($configKey));
        if ($model === '') {
            throw new RuntimeException("Задайте {$envHint} в .env.");
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyOutputTokenLimit(array &$payload, int $maxOut): string
    {
        $model = (string) ($payload['model'] ?? '');
        $key = preg_match('/^gpt-5/i', $model) === 1 ? 'max_completion_tokens' : 'max_tokens';
        $payload[$key] = $maxOut;

        return $key;
    }
}
