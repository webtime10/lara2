<?php

namespace App\Services;

use App\Exceptions\WeatherGeminiRateLimitedException;
use App\Models\Language;
use App\Models\WeatherMonthStat;
use App\Models\WeatherPromt;
use App\Support\SwissWeatherCantons;
use RuntimeException;

/**
 * Заполнение Weather: промт из Промты → Швейцария → Погода, только бесплатный Gemini.
 * Очередь: 1 клетка (кантон × месяц).
 */
class WeatherMonthStatAiService
{
    public const MODEL_GEMINI_FREE = FoodSourceGeminiPriceService::MODEL_GEMINI_FREE;

    public function __construct(
        private GeminiService $gemini,
    ) {}

    /**
     * @param  array{slug: string, name_ru: string, name_ar: string}  $canton
     */
    public function refreshMonth(
        array $canton,
        int $month,
        ?string $model = null,
        bool $skipIfFilled = false,
    ): WeatherMonthStat {
        if ($month < 1 || $month > 12) {
            throw new RuntimeException('Месяц должен быть от 1 до 12.');
        }

        $model = $this->normalizeModel($model);
        $slug = $canton['slug'];
        $regionName = $canton['name_ru'];

        if ($skipIfFilled) {
            $existing = WeatherMonthStat::query()
                ->where('region_slug', $slug)
                ->where('month', $month)
                ->first();
            if ($existing instanceof WeatherMonthStat && $existing->isFilled()) {
                return $existing;
            }
        }

        $monthName = SwissWeatherCantons::monthNamesRu()[$month] ?? (string) $month;
        $instructionTemplate = $this->loadMainPrompt();

        $answer = $this->askGemini(
            $this->sourceText($monthName, $regionName),
            $this->buildInstruction($instructionTemplate, $monthName, $regionName)
        );

        if ($answer === null || trim($answer) === '') {
            $status = $this->gemini->lastHttpStatus();
            if ($status === 429) {
                throw new WeatherGeminiRateLimitedException(45, 'HTTP 429: квота Gemini');
            }

            throw new RuntimeException(
                'пустой ответ Gemini'.($status !== null ? ' (HTTP '.$status.')' : ' (сеть/ключ/лимит API)')
            );
        }

        $parsed = $this->parseWeatherJson($answer);
        if ($parsed === null) {
            throw new RuntimeException('невалидный JSON от модели');
        }
        if (($parsed['error'] ?? null) !== null) {
            throw new RuntimeException((string) $parsed['error']);
        }

        $weather = $parsed['weather'];

        return WeatherMonthStat::query()->updateOrCreate(
            [
                'region_slug' => $slug,
                'month' => $month,
            ],
            [
                'region_name_ru' => $regionName,
                'average_temperature' => $weather['average_temperature'],
                'precipitation' => $weather['precipitation'],
                'sunny_days' => $weather['sunny_days'],
                'season' => $weather['season'],
                'ai_model' => $model,
                'last_checked' => now(),
            ]
        );
    }

    /**
     * @param  array{slug: string, name_ru: string, name_ar: string}  $canton
     * @return array{stats: array<int, WeatherMonthStat>, model: string, filled: int, failed: list<string>}
     */
    public function refreshCanton(array $canton, ?string $model = null): array
    {
        set_time_limit(600);

        $model = $this->normalizeModel($model);
        $saved = [];
        $failed = [];
        $monthNames = SwissWeatherCantons::monthNamesRu();

        foreach (SwissWeatherCantons::months() as $month) {
            $monthName = $monthNames[$month] ?? (string) $month;
            try {
                $saved[$month] = $this->refreshMonth($canton, $month, $model, false);
            } catch (WeatherGeminiRateLimitedException $e) {
                $failed[] = $monthName.': '.$e->getMessage();
                sleep(min(60, max(5, $e->retryAfterSeconds)));
            } catch (\Throwable $e) {
                $failed[] = $monthName.': '.$e->getMessage();
            }
        }

        if ($saved === []) {
            throw new RuntimeException(
                $this->modelLabel($model).' не вернул погоду для '.$canton['name_ru']
                .($failed !== [] ? ' ('.implode('; ', array_slice($failed, 0, 3)).')' : '.')
            );
        }

        return [
            'stats' => $saved,
            'model' => $model,
            'filled' => count($saved),
            'failed' => $failed,
        ];
    }

    /** @return array<string, string> */
    public static function modelLabels(): array
    {
        return FoodSourceGeminiPriceService::modelLabels();
    }

    public static function defaultModel(): string
    {
        return self::MODEL_GEMINI_FREE;
    }

    private function normalizeModel(?string $model): string
    {
        $model = trim((string) $model);
        $allowed = array_flip(array_keys(self::modelLabels()));

        return isset($allowed[$model]) ? $model : self::defaultModel();
    }

    private function modelLabel(string $model): string
    {
        return self::modelLabels()[$model] ?? $model;
    }

    private function loadMainPrompt(): string
    {
        $codes = [];
        $default = strtolower((string) (Language::getDefault()?->code ?? ''));
        if ($default !== '') {
            $codes[] = $default;
        }
        foreach (['ru', 'en', 'ar', 'he'] as $code) {
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        foreach ($codes as $code) {
            $content = WeatherPromt::query()
                ->where('name', 'glavnyy_prompt_'.$code)
                ->value('content');
            if (is_string($content) && trim($content) !== '') {
                return trim($content);
            }
        }

        $legacy = WeatherPromt::query()->where('name', 'glavnyy_prompt')->value('content');
        if (is_string($legacy) && trim($legacy) !== '') {
            return trim($legacy);
        }

        throw new RuntimeException(
            'Главный промт Weather не задан. Заполните его в админке: Промты → Швейцария → Погода.'
        );
    }

    private function buildInstruction(string $template, string $monthName, string $regionName): string
    {
        $instruction = str_replace(
            ['{month_name}', '{region_name}', '{month}', '{region}', '{language}'],
            [$monthName, $regionName, $monthName, $regionName, 'ru'],
            $template
        );

        $suffix = <<<'TXT'

---
SYSTEM (обязательно):
Опиши типичную погоду для туриста по Month/Region из SOURCE TEXT.
Все поля JSON должны быть заполнены непустыми строками. Пустые "" недопустимы.
precipitation: только «низкий», «средний» или «высокий».
season: только «весна», «лето», «осень» или «зима».
average_temperature — число/диапазон без символа °C (например «-1…3» или «18»).
Верни только один JSON-объект без Markdown:
{"average_temperature":"...","precipitation":"...","sunny_days":"...","season":"..."}
TXT;

        return rtrim($instruction)."\n".$suffix;
    }

    private function sourceText(string $monthName, string $regionName): string
    {
        return "Month: {$monthName}\nRegion: {$regionName}\nLanguage: ru";
    }

    private function askGemini(string $material, string $instruction): ?string
    {
        return $this->gemini->chat(
            $material,
            $instruction,
            max(60, (int) config('services.gemini.chat_timeout', 900)),
            ['temperature' => 0.2],
        );
    }

    /**
     * @return array{weather: array{average_temperature: string, precipitation: string, sunny_days: string, season: string}, error: string|null}|null
     */
    private function parseWeatherJson(string $raw): ?array
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

        $temperature = $pick($data, ['average_temperature', 'temperature', 'temp']);
        $precipitation = mb_strtolower($pick($data, ['precipitation', 'precip']));
        $sunnyDays = $pick($data, ['sunny_days', 'sunny']);
        $season = mb_strtolower($pick($data, ['season']));

        $precipitation = match (true) {
            str_contains($precipitation, 'высок') || str_contains($precipitation, 'high') => 'высокий',
            str_contains($precipitation, 'средн') || str_contains($precipitation, 'medium') || str_contains($precipitation, 'moderate') => 'средний',
            str_contains($precipitation, 'низк') || str_contains($precipitation, 'low') => 'низкий',
            default => $precipitation,
        };

        $season = match (true) {
            str_contains($season, 'весн') || str_contains($season, 'spring') => 'весна',
            str_contains($season, 'лет') || str_contains($season, 'summer') => 'лето',
            str_contains($season, 'осен') || str_contains($season, 'autumn') || str_contains($season, 'fall') => 'осень',
            str_contains($season, 'зим') || str_contains($season, 'winter') => 'зима',
            default => $season,
        };

        $weather = [
            'average_temperature' => $temperature,
            'precipitation' => $precipitation,
            'sunny_days' => $sunnyDays,
            'season' => $season,
        ];

        if ($temperature === '' || $precipitation === '' || $sunnyDays === '' || $season === '') {
            return [
                'weather' => $weather,
                'error' => 'модель вернула пустые поля',
            ];
        }

        return [
            'weather' => $weather,
            'error' => null,
        ];
    }
}
