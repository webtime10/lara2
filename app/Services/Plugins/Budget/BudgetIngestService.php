<?php

namespace App\Services\Plugins\Budget;

use App\Models\BudgetPromt;
use App\Models\Language;
use App\Support\BudgetAiModelChoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BudgetIngestService
{
    public const PLUGIN = 'budget';

    private const CACHE_KEY_PREFIX = 'plugin_budget_result:';

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

        $fromCache = false;
        $aiResult = null;
        $cacheKey = null;
        $cacheEnabled = filter_var(config('services.plugins.budget.cache.enabled', true), FILTER_VALIDATE_BOOL);
        $cacheTtl = max(3600, (int) config('services.plugins.budget.cache.ttl_hours', 48) * 3600);

        if ($cacheEnabled) {
            $language = strtolower(trim((string) ($payload['language'] ?? '')));
            $answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];

            if (in_array($language, Language::activeCodes(), true) && $answers !== []) {
                $modelRaw = BudgetPromt::where('name', BudgetAiModelChoice::SETTING_NAME)->value('content');
                $modelKey = BudgetAiModelChoice::normalize(is_string($modelRaw) ? $modelRaw : null);

                $promptName = 'glavnyy_prompt_'.$language;
                $promptText = BudgetPromt::where('name', $promptName)->value('content');
                $defaultCode = strtolower((string) (Language::getDefault()?->code ?? ''));
                if ((! is_string($promptText) || trim($promptText) === '') && $defaultCode !== '' && $language === $defaultCode) {
                    $promptText = BudgetPromt::where('name', 'glavnyy_prompt')->value('content');
                }
                $promptHash = sha1(is_string($promptText) ? trim($promptText) : '');
                $answersHash = sha1(json_encode($this->normalizeAnswers($answers), JSON_UNESCAPED_UNICODE) ?: '');

                $cacheKey = self::CACHE_KEY_PREFIX.sha1('v1|'.$language.'|'.$answersHash.'|'.$modelKey.'|'.$promptHash);
                $stored = Cache::get($cacheKey);

                if (is_array($stored) && ! empty($stored['ok']) && is_array($stored['budget'] ?? null)) {
                    $fromCache = true;
                    $aiResult = [
                        'ok' => true,
                        'message' => (string) ($stored['message'] ?? ''),
                        'budget' => $stored['budget'],
                        'model' => isset($stored['model']) ? (string) $stored['model'] : null,
                        'language' => isset($stored['language']) ? (string) $stored['language'] : null,
                    ];
                    Log::info('[plugin:budget] cache hit', ['cache_key' => $cacheKey]);
                }
            }
        }

        if ($aiResult === null) {
            $aiResult = $this->ai->run($payload);

            if ($cacheEnabled && $cacheKey !== null && ! empty($aiResult['ok']) && is_array($aiResult['budget'] ?? null)) {
                Cache::put($cacheKey, [
                    'ok' => true,
                    'message' => (string) ($aiResult['message'] ?? ''),
                    'budget' => $aiResult['budget'],
                    'model' => $aiResult['model'] ?? null,
                    'language' => $aiResult['language'] ?? null,
                    'cached_at' => now()->toIso8601String(),
                ], $cacheTtl);
                Log::info('[plugin:budget] cache stored', ['cache_key' => $cacheKey, 'ttl_seconds' => $cacheTtl]);
            }
        }

        $response = [
            'ok' => (bool) ($aiResult['ok'] ?? false),
            'plugin' => self::PLUGIN,
            'request_id' => $requestId,
            'message' => (string) ($aiResult['message'] ?? ''),
            'model' => $aiResult['model'] ?? null,
            'language' => $aiResult['language'] ?? ($payload['language'] ?? null),
            'from_cache' => $fromCache,
            'received' => $payload,
        ];

        if (! empty($aiResult['budget']) && is_array($aiResult['budget'])) {
            $response['budget'] = $aiResult['budget'];
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function normalizeAnswers(array $answers): array
    {
        ksort($answers);

        return $answers;
    }
}
