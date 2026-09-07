<?php

namespace App\Services;

use App\Exceptions\WeatherGeminiRateLimitedException;
use App\Models\Language;
use App\Models\WeatherMonthStat;
use App\Models\WeatherPromt;
use App\Support\SwissWeatherCantons;
use App\Support\WeatherCountry;
use RuntimeException;

/**
 * Заполнение Weather: промт из Промты → {страна} → Погода, только бесплатный Gemini.
 * Очередь: 1 клетка (регион × месяц).
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
        string $country = WeatherCountry::CH,
    ): WeatherMonthStat {
        if ($month < 1 || $month > 12) {
            throw new RuntimeException('Месяц должен быть от 1 до 12.');
        }

        $country = WeatherCountry::normalize($country);
        $model = $this->normalizeModel($model);
        $slug = $canton['slug'];
        $regionName = $canton['name_ru'];

        if ($skipIfFilled) {
            $existing = WeatherMonthStat::query()
                ->where('country', $country)
                ->where('region_slug', $slug)
                ->where('month', $month)
                ->first();
            if ($existing instanceof WeatherMonthStat && $existing->isFilled()) {
                return $existing;
            }
        }

        $monthName = SwissWeatherCantons::monthNamesRu()[$month] ?? (string) $month;
        $prompt = $this->loadMainPrompt($country);
        $language = $prompt['language'];

        $answer = $this->askGemini(
            $this->sourceText($monthName, $regionName, $language),
            $this->buildInstruction($prompt['content'], $monthName, $regionName, $language)
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

        $parsed = $this->parseWeatherJson($answer, $language);
        if ($parsed === null) {
            throw new RuntimeException('невалидный JSON от модели');
        }
        if (($parsed['error'] ?? null) !== null) {
            throw new RuntimeException((string) $parsed['error']);
        }

        $weather = $parsed['weather'];

        return WeatherMonthStat::query()->updateOrCreate(
            [
                'country' => $country,
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

    private function loadMainPrompt(string $country = WeatherCountry::CH): array
    {
        $country = WeatherCountry::normalize($country);
        $prefix = WeatherCountry::promptPrefix($country);
        $legacyName = WeatherCountry::legacyPromptName($country);

        $codes = [];
        $default = strtolower((string) (Language::getDefault()?->code ?? ''));
        if ($default !== '') {
            $codes[] = $default;
        }
        foreach (['ar', 'he', 'ru', 'en'] as $code) {
            if (! in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }

        foreach ($codes as $code) {
            $content = WeatherPromt::query()
                ->where('name', $prefix.$code)
                ->value('content');
            if (is_string($content) && trim($content) !== '') {
                return ['content' => trim($content), 'language' => $code];
            }
        }

        $legacy = WeatherPromt::query()->where('name', $legacyName)->value('content');
        if (is_string($legacy) && trim($legacy) !== '') {
            return ['content' => trim($legacy), 'language' => ($default !== '' ? $default : 'ru')];
        }

        throw new RuntimeException(
            'Главный промт Weather не задан. Заполните его в админке: '.WeatherCountry::promptAdminPath($country).'.'
        );
    }

    private function buildInstruction(string $template, string $monthName, string $regionName, string $language): string
    {
        $instruction = str_replace(
            ['{month_name}', '{region_name}', '{month}', '{region}', '{language}'],
            [$monthName, $regionName, $monthName, $regionName, $language],
            $template
        );

        $suffix = $this->systemSuffix($language);

        return rtrim($instruction)."\n".$suffix;
    }

    private function systemSuffix(string $language): string
    {
        return match ($language) {
            'ar' => <<<'TXT'

---
SYSTEM (обязательно):
Опиши типичную погоду для туриста по Month/Region из SOURCE TEXT.
Все поля JSON — непустые строки на арабском. Пустые "" недопустимы.
average_temperature: РОВНО 4 температуры — день (макс мин) и ночь (макс мин).
Формат строго: "+12° +8°|+3° -2°" (пара дня, затем |, пара ночи). Внутри пары: сначала выше, потом ниже.
Примеры: "+15° +10°|+6° +1°", зимой: "+2° -1°|-4° -9°".
ЗАПРЕЩЕНО: одно/два числа, "...", °C, слова, арабский текст в температуре.
precipitation: только "منخفض" или "متوسط" или "مرتفع"
season: только "ربيع" или "صيف" или "خريف" или "شتاء"
Верни только один JSON-объект без Markdown:
{"average_temperature":"...","precipitation":"...","sunny_days":"...","season":"..."}
TXT,
            'he' => <<<'TXT'

---
SYSTEM (обязательно):
Опиши типичную погоду для туриста по Month/Region из SOURCE TEXT.
Все поля JSON — непустые строки на иврите. Пустые "" недопустимы.
average_temperature: РОВНО 4 температуры — день (макс мин) и ночь (макс мин).
Формат строго: "+12° +8°|+3° -2°" (пара дня, затем |, пара ночи). Внутри пары: сначала выше, потом ниже.
Примеры: "+15° +10°|+6° +1°", зимой: "+2° -1°|-4° -9°".
ЗАПРЕЩЕНО: одно/два числа, "...", °C, слова, иврит в температуре.
precipitation: только "נמוך" или "בינוני" или "גבוה"
season: только "אביב" или "קיץ" или "סתיו" или "חורף"
Верни только один JSON-объект без Markdown:
{"average_temperature":"...","precipitation":"...","sunny_days":"...","season":"..."}
TXT,
            default => <<<'TXT'

---
SYSTEM (обязательно):
Опиши типичную погоду для туриста по Month/Region из SOURCE TEXT.
Все поля JSON должны быть заполнены непустыми строками. Пустые "" недопустимы.
average_temperature: РОВНО 4 температуры — день (макс мин) и ночь (макс мин).
Формат строго: "+12° +8°|+3° -2°" (пара дня, затем |, пара ночи). Внутри пары: сначала выше, потом ниже.
Примеры: "+15° +10°|+6° +1°", зимой: "+2° -1°|-4° -9°".
ЗАПРЕЩЕНО: одно/два числа, "...", °C, слова в температуре.
precipitation: только «низкий», «средний» или «высокий».
season: только «весна», «лето», «осень» или «зима».
Верни только один JSON-объект без Markdown:
{"average_temperature":"...","precipitation":"...","sunny_days":"...","season":"..."}
TXT,
        };
    }

    private function sourceText(string $monthName, string $regionName, string $language): string
    {
        return "Month: {$monthName}\nRegion: {$regionName}\nLanguage: {$language}";
    }

    private function askGemini(string $material, string $instruction): ?string
    {
        // Gemini 3.x отклоняет temperature/topK/topP — без generationConfig.
        return $this->gemini->chat(
            $material,
            $instruction,
            max(60, (int) config('services.gemini.chat_timeout', 900)),
            null,
        );
    }

    /**
     * @return array{weather: array{average_temperature: string, precipitation: string, sunny_days: string, season: string}, error: string|null}|null
     */
    private function parseWeatherJson(string $raw, string $language = 'ru'): ?array
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

        $temperature = $this->normalizeDayNightTemperature(
            $pick($data, ['average_temperature', 'temperature', 'temp']),
            $language
        );
        $precipitationRaw = $pick($data, ['precipitation', 'precip']);
        $sunnyDays = $pick($data, ['sunny_days', 'sunny']);
        $seasonRaw = $pick($data, ['season']);

        $precipitation = $this->normalizePrecipitation($precipitationRaw);
        $season = $this->normalizeSeason($seasonRaw);
        $tempParts = $this->splitDayNightTemperature($temperature);

        $weather = [
            'average_temperature' => $temperature,
            'temperature_day' => $tempParts['day'],
            'temperature_night' => $tempParts['night'],
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

    /**
     * Привести к "+12° +8°|+3° -2°" (день макс/мин | ночь макс/мин).
     */
    private function normalizeDayNightTemperature(string $raw, string $language = 'ru'): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (preg_match_all('/([+-]?\d+)/u', $raw, $m) && count($m[1]) >= 4) {
            $n = array_map('intval', array_slice($m[1], 0, 4));

            return $this->formatDayNightRanges($n[0], $n[1], $n[2], $n[3]);
        }

        // Старый формат "+15° +6°" (день / ночь) → пары вокруг каждой
        if (preg_match('/^([+-]?\d+)\s*°?\s+([+-]?\d+)\s*°?$/u', $raw, $m)) {
            $day = (int) $m[1];
            $night = (int) $m[2];
            $spread = max(2, (int) round(abs($day - $night) * 0.35));

            return $this->formatDayNightRanges($day, $day - $spread, $night + $spread, $night);
        }

        // Один диапазон — день = диапазон, ночь ниже
        if (preg_match('/([+-]?\d+)\s*(?:\.\.\.|…|-|–|—|до|to|إلى|עד)\s*([+-]?\d+)/ui', $raw, $m)) {
            $a = (int) $m[1];
            $b = (int) $m[2];
            $dMin = min($a, $b);
            $dMax = max($a, $b);

            return $this->formatDayNightRanges($dMax, $dMin, $dMax - 5, $dMin - 8);
        }

        if (preg_match('/^([+-]?\d+)\s*°?$/u', $raw, $m)) {
            $day = (int) $m[1];

            return $this->formatDayNightRanges($day + 2, $day - 2, $day - 4, $day - 8);
        }

        return $raw;
    }

    private function formatDayNightRanges(int $d1, int $d2, int $n1, int $n2): string
    {
        $dMax = max($d1, $d2);
        $dMin = min($d1, $d2);
        $nMax = max($n1, $n2);
        $nMin = min($n1, $n2);

        return sprintf('%+d° %+d°|%+d° %+d°', $dMax, $dMin, $nMax, $nMin);
    }

    /**
     * @return array{day: string, night: string}
     */
    private function splitDayNightTemperature(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['day' => '', 'night' => ''];
        }

        if (str_contains($raw, '|')) {
            [$day, $night] = array_pad(explode('|', $raw, 2), 2, '');

            return [
                'day' => trim($day),
                'night' => trim($night),
            ];
        }

        if (preg_match_all('/([+-]?\d+)\s*°?/u', $raw, $m) && count($m[1]) >= 4) {
            $n = array_map('intval', array_slice($m[1], 0, 4));

            return [
                'day' => sprintf('%+d° %+d°', max($n[0], $n[1]), min($n[0], $n[1])),
                'night' => sprintf('%+d° %+d°', max($n[2], $n[3]), min($n[2], $n[3])),
            ];
        }

        if (preg_match('/^([+-]?\d+)\s*°?\s+([+-]?\d+)\s*°?$/u', $raw, $m)) {
            return [
                'day' => sprintf('%+d°', (int) $m[1]),
                'night' => sprintf('%+d°', (int) $m[2]),
            ];
        }

        return ['day' => $raw, 'night' => ''];
    }

    private function normalizePrecipitation(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $lower = mb_strtolower($raw);

        return match (true) {
            str_contains($lower, 'высок') || str_contains($lower, 'high') || str_contains($raw, 'مرتفع') || str_contains($raw, 'גבוה') => $this->pickLocalizedWord($raw, 'высокий', 'مرتفع', 'גבוה'),
            str_contains($lower, 'средн') || str_contains($lower, 'medium') || str_contains($lower, 'moderate') || str_contains($raw, 'متوسط') || str_contains($raw, 'בינוני') => $this->pickLocalizedWord($raw, 'средний', 'متوسط', 'בינוני'),
            str_contains($lower, 'низк') || str_contains($lower, 'low') || str_contains($raw, 'منخفض') || str_contains($raw, 'נמוך') => $this->pickLocalizedWord($raw, 'низкий', 'منخفض', 'נמוך'),
            default => $raw,
        };
    }

    private function normalizeSeason(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }

        $lower = mb_strtolower($raw);

        return match (true) {
            str_contains($lower, 'весн') || str_contains($lower, 'spring') || str_contains($raw, 'ربيع') || str_contains($raw, 'אביב') => $this->pickLocalizedWord($raw, 'весна', 'ربيع', 'אביב'),
            str_contains($lower, 'лет') || str_contains($lower, 'summer') || str_contains($raw, 'صيف') || str_contains($raw, 'קיץ') => $this->pickLocalizedWord($raw, 'лето', 'صيف', 'קיץ'),
            str_contains($lower, 'осен') || str_contains($lower, 'autumn') || str_contains($lower, 'fall') || str_contains($raw, 'خريف') || str_contains($raw, 'סתיו') => $this->pickLocalizedWord($raw, 'осень', 'خريف', 'סתיו'),
            str_contains($lower, 'зим') || str_contains($lower, 'winter') || str_contains($raw, 'شتاء') || str_contains($raw, 'חורף') => $this->pickLocalizedWord($raw, 'зима', 'شتاء', 'חורף'),
            default => $raw,
        };
    }

    /**
     * Сохраняем язык ответа модели (ar/he), не переводим всё в русский.
     */
    private function pickLocalizedWord(string $raw, string $ru, string $ar, string $he): string
    {
        if (preg_match('/[\x{0590}-\x{05FF}]/u', $raw)) {
            return $he;
        }
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $raw)) {
            return $ar;
        }

        return $ru;
    }
}
