<?php

namespace App\Services\Plugins\Budget;

use App\Models\BudgetPromt;
use App\Models\Language;
use App\Services\GeminiProService;
use App\Services\GeminiService;
use App\Services\OpenAiService;
use App\Support\BudgetAiModelChoice;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BudgetAiService
{
    public function __construct(
        private GeminiService $gemini,
        private GeminiProService $geminiPro,
        private OpenAiService $openAi,
    ) {}

    /**
     * @param  array{language: string, answers: array<string, mixed>}  $payload
     * @return array<string, mixed>
     */
    public function run(array $payload): array
    {
        $language = strtolower(trim((string) ($payload['language'] ?? '')));
        if (! in_array($language, Language::activeCodes(), true)) {
            return ['ok' => false, 'message' => 'Неподдерживаемый язык: '.$language.' (нет в languages)'];
        }

        $answers = $payload['answers'] ?? null;
        if (! is_array($answers) || $answers === []) {
            return ['ok' => false, 'message' => 'Не переданы ответы опроса (answers).'];
        }

        $instruction = $this->loadMainPrompt($language);
        if ($instruction === '') {
            return [
                'ok' => false,
                'message' => 'Главный промт для языка '.$language.' не задан (админка → Промты WP → Budget).',
            ];
        }

        $instruction = $this->applyPlaceholders($instruction, $language);
        $instruction = $this->appendOutputRules($instruction);
        $material = $this->buildMaterial($answers, $language);
        $modelKey = $this->loadSelectedModelKey();

        try {
            $request = $this->requestFromModel($modelKey, $material, $instruction);
            $answer = $request['text'];
            $httpStatus = $request['http_status'];

            if ($this->isGeminiModel($modelKey) && $this->shouldFallbackToOpenAiMini($answer, $httpStatus)) {
                $fallbackKey = BudgetAiModelChoice::OPENAI_GPT_4O_MINI;
                Log::warning('[plugin:budget] Gemini недоступен, fallback на OpenAI Mini', [
                    'primary_model' => $modelKey,
                    'http_status' => $httpStatus,
                    'fallback_model' => $fallbackKey,
                    'language' => $language,
                ]);
                $fallbackRequest = $this->requestFromModel($fallbackKey, $material, $instruction);
                $answer = $fallbackRequest['text'];
                $modelKey = $fallbackKey;
            }
        } catch (RuntimeException $e) {
            Log::error('[plugin:budget] AI', ['error' => $e->getMessage(), 'model' => $modelKey]);

            return ['ok' => false, 'message' => $e->getMessage(), 'model' => $modelKey, 'language' => $language];
        }

        if ($answer === null || trim($answer) === '') {
            return [
                'ok' => false,
                'message' => 'Модель не вернула текст. Проверьте ключи API и логи Laravel.',
                'model' => $modelKey,
                'language' => $language,
            ];
        }

        $parsed = $this->parseBudgetJson($answer);
        if ($parsed === null) {
            return [
                'ok' => false,
                'message' => 'Ответ модели не является валидным JSON. Проверьте промт (только JSON, без Markdown).',
                'model' => $modelKey,
                'language' => $language,
            ];
        }

        if ($parsed['error'] !== null) {
            return [
                'ok' => false,
                'message' => $parsed['error'],
                'model' => $modelKey,
                'language' => $language,
            ];
        }

        $budget = $parsed['budget'];

        return [
            'ok' => true,
            'message' => $budget['summary'],
            'budget' => $budget,
            'model' => $modelKey,
            'language' => $language,
        ];
    }

    private function loadMainPrompt(string $language): string
    {
        $name = 'glavnyy_prompt_'.$language;
        $content = BudgetPromt::where('name', $name)->value('content');
        if (is_string($content) && trim($content) !== '') {
            return trim($content);
        }

        $defaultCode = strtolower((string) (Language::getDefault()?->code ?? ''));
        if ($defaultCode !== '' && $language === $defaultCode) {
            $legacy = BudgetPromt::where('name', 'glavnyy_prompt')->value('content');
            if (is_string($legacy) && trim($legacy) !== '') {
                return trim($legacy);
            }
        }

        return '';
    }

    private function loadSelectedModelKey(): string
    {
        $raw = BudgetPromt::where('name', BudgetAiModelChoice::SETTING_NAME)->value('content');

        return BudgetAiModelChoice::normalize(is_string($raw) ? $raw : null);
    }

    private function applyPlaceholders(string $prompt, string $language): string
    {
        return str_replace('{language}', $language, $prompt);
    }

    private function appendOutputRules(string $instruction): string
    {
        $suffix = <<<'TXT'

---
SYSTEM (обязательно):
По данным опроса из SOURCE TEXT рассчитай примерный бюджет поездки для туриста.
Верни только один JSON-объект, без Markdown и без текста до/после.
Обязательные поля:
- "total" — общая сумма (строка, напр. "53 260$")
- "per_person" — на человека (строка, напр. "3 260$")
- "summary" — краткий комментарий (строка)
- "rows" — массив из 4 объектов: {"label":"...", "price":"..."} (транспорт, проживание, питание, развлечения)
Все строковые поля непустые.
TXT;

        return rtrim($instruction).$suffix;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function buildMaterial(array $answers, string $language): string
    {
        $json = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return "Language: {$language}\nSurvey answers JSON:\n".(is_string($json) ? $json : '{}');
    }

    /**
     * @return array{budget: array{total: string, per_person: string, summary: string, rows: list<array{label: string, price: string}>}, error: string|null}|null
     */
    private function parseBudgetJson(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $raw, $m)) {
            $raw = trim($m[1]);
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $raw = substr($raw, $start, $end - $start + 1);
        }

        $raw = preg_replace('/,\s*([}\]])/s', '$1', $raw) ?? $raw;

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        $pick = static function (array $source, array $keys): string {
            foreach ($keys as $key) {
                if (! array_key_exists($key, $source) || ! is_scalar($source[$key])) {
                    continue;
                }
                $value = trim((string) $source[$key]);
                if ($value !== '') {
                    return $value;
                }
            }

            return '';
        };

        $total = $pick($data, ['total', 'total_budget', 'total_cost']);
        $perPerson = $pick($data, ['per_person', 'per_person_budget', 'per_person_cost']);
        $summary = $pick($data, ['summary', 'comment', 'description']);

        $rows = [];
        $rawRows = $data['rows'] ?? $data['breakdown'] ?? $data['items'] ?? null;
        if (is_array($rawRows)) {
            foreach ($rawRows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $label = $pick($row, ['label', 'name', 'title']);
                $price = $pick($row, ['price', 'amount', 'cost']);
                if ($label !== '' || $price !== '') {
                    $rows[] = ['label' => $label, 'price' => $price];
                }
            }
        }

        $budget = [
            'total' => $total,
            'per_person' => $perPerson,
            'summary' => $summary,
            'rows' => $rows,
        ];

        if ($total === '' && $perPerson === '' && $rows === []) {
            return [
                'budget' => $budget,
                'error' => 'Модель вернула пустой бюджет. Уточните промт: нужны total, per_person и rows.',
            ];
        }

        return [
            'budget' => $budget,
            'error' => null,
        ];
    }

    /**
     * @return array{text: ?string, http_status: ?int}
     */
    private function requestFromModel(string $modelKey, string $material, string $instruction): array
    {
        if ($modelKey === BudgetAiModelChoice::GEMINI_FLASH) {
            $timeout = max(60, (int) config('services.gemini.chat_timeout', 900));

            return [
                'text' => $this->gemini->chat($material, $instruction, $timeout),
                'http_status' => $this->gemini->lastHttpStatus(),
            ];
        }

        if ($modelKey === BudgetAiModelChoice::GEMINI_PRO) {
            $timeout = max(60, (int) config('services.gemini_pro.chat_timeout', 1800));
            $maxOutputTokens = (int) config('services.gemini_pro.max_output_tokens', 65536);

            return [
                'text' => $this->geminiPro->chat($material, $instruction, $timeout, [
                    'maxOutputTokens' => max(8192, $maxOutputTokens),
                ]),
                'http_status' => $this->geminiPro->lastHttpStatus(),
            ];
        }

        if (BudgetAiModelChoice::isOpenAi($modelKey)) {
            $openAiModel = BudgetAiModelChoice::openAiModelId($modelKey);

            return [
                'text' => $this->openAi->askOpenAiWithModel(
                    $instruction,
                    $material,
                    $openAiModel,
                    'BudgetAi:'.$modelKey
                ),
                'http_status' => null,
            ];
        }

        throw new RuntimeException('Неизвестная модель: '.$modelKey);
    }

    private function isGeminiModel(string $modelKey): bool
    {
        return in_array($modelKey, [
            BudgetAiModelChoice::GEMINI_FLASH,
            BudgetAiModelChoice::GEMINI_PRO,
        ], true);
    }

    private function shouldFallbackToOpenAiMini(?string $answer, ?int $httpStatus): bool
    {
        if ($answer !== null && trim($answer) !== '') {
            return false;
        }

        if ($httpStatus === null) {
            return true;
        }

        return $httpStatus >= 500 && $httpStatus <= 503;
    }
}
