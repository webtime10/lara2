<?php
// Тег PHP

namespace App\Services\Plugins\Weather;
// Пакет сервиса погоды

use App\Models\Language;
// Таблица languages — проверка code (en, he, ar)

use App\Models\WeatherPromt;
// Таблица weather_promt — тексты промтов и настройка модели

use App\Services\GeminiProService;
// Клиент Gemini Pro (отдельный ключ GEMINI_PRO_API_KEY)

use App\Services\GeminiService;
// Клиент Gemini Flash (ключи GEMINI_API_KEY, round-robin)

use App\Services\OpenAiService;
// Клиент OpenAI (GPT-4o и др.)

use App\Support\WeatherAiModelChoice;
// Константы моделей и normalize() выбора из админки

use App\Support\WeatherCountry;
use Illuminate\Support\Facades\Log;
// Логи ошибок/предупреждений AI

use RuntimeException;
// Исключение при неизвестной модели

class WeatherAiService
// Вызов нейросети и разбор ответа в структуру weather
{
    public function __construct(
        private GeminiService $gemini,
        // Flash — основной для калькулятора
        private GeminiProService $geminiPro,
        // Pro — если выбрано в админке
        private OpenAiService $openAi,
        // OpenAI — если выбран GPT
    ) {}

    /**
     * @param  array{month_name: string, region_name: string, language: string, month?: int, region?: int}  $payload
     * @return array{ok: bool, message: string, model?: string, language?: string}
     */
    public function run(array $payload): array
    // Точка входа: из IngestService приходит month_name, region_name, language
    {
        $language = strtolower(trim((string) ($payload['language'] ?? '')));
        // Язык с WP — должен совпадать с languages.code

        if (! in_array($language, Language::activeCodes(), true)) {
            // code нет среди активных в БД — не вызываем AI
            return ['ok' => false, 'message' => 'Неподдерживаемый язык: '.$language.' (нет в languages)'];
        }

        $monthName = trim((string) ($payload['month_name'] ?? ''));
        // Название месяца как прислал пользователь (для промта и AI)

        $regionName = trim((string) ($payload['region_name'] ?? ''));
        // Название региона

        if ($monthName === '' || $regionName === '') {
            // Без месяца или региона смысла нет
            return ['ok' => false, 'message' => 'Не заданы месяц или регион.'];
        }

        $instruction = $this->loadMainPrompt($language, WeatherCountry::normalize((string) ($payload['country'] ?? WeatherCountry::CH)));
        // Главный промт из БД для glavnyy_prompt_{code} / japan_glavnyy_prompt_{code}

        if ($instruction === '') {
            $country = WeatherCountry::normalize((string) ($payload['country'] ?? WeatherCountry::CH));
            // Промт не заполнен в админке
            return [
                'ok' => false,
                'message' => 'Главный промт для языка '.$language.' не задан (админка → '.WeatherCountry::promptAdminPath($country).').',
            ];
        }

        $instruction = $this->applyPlaceholders($instruction, $monthName, $regionName, $language);
        // Подставляем {month_name}, {region_name}, {language} в текст промта

        $instruction = $this->appendOutputRules($instruction, $language);
        // Дописываем жёсткие правила: только JSON, все поля заполнены

        $material = $this->buildMaterial($monthName, $regionName, $language);
        // «Исходный текст» для модели: Month/Region/Language

        $modelKey = $this->loadSelectedModelKey();
        // Какая модель выбрана в админке (weather_ai_model в weather_promt)

        try {
            $result = $this->weatherFromModelChain($modelKey, $material, $instruction, $language);
        } catch (RuntimeException $e) {
            Log::error('[plugin:weather] AI', ['error' => $e->getMessage(), 'model' => $modelKey]);
            // Неизвестная модель или ошибка клиента

            return ['ok' => false, 'message' => $e->getMessage(), 'model' => $modelKey, 'language' => $language];
        }

        $weather = $result['weather'];
        // Массив: temperature, precipitation, sunny_days, season, summary

        return [
            'ok' => true,
            'message' => $weather['summary'],
            // Краткий текст для message
            'weather' => $weather,
            // Данные для WP — блоки на странице
            'model' => $result['model'],
            'language' => $language,
        ];
    }

    /**
     * @return array{weather: array{temperature: string, precipitation: string, sunny_days: string, season: string, summary: string}, model: string}
     */
    private function weatherFromModelChain(string $selectedModel, string $material, string $instruction, string $language): array
    {
        $lastMessage = null;

        foreach ($this->fallbackModelChain($selectedModel) as $modelKey) {
            try {
                $request = $this->requestFromModel($modelKey, $material, $instruction);
            } catch (RuntimeException $e) {
                $lastMessage = $e->getMessage();
                Log::warning('[plugin:weather] model_exception', [
                    'model' => $modelKey,
                    'error' => $e->getMessage(),
                    'language' => $language,
                ]);
                continue;
            }

            $answer = $request['text'];
            if ($answer === null || trim($answer) === '') {
                $status = $request['http_status'];
                $lastMessage = 'Модель '.$modelKey.' не вернула текст.'
                    .($status !== null ? ' (HTTP '.$status.')' : '');
                Log::warning('[plugin:weather] empty_response', [
                    'model' => $modelKey,
                    'http_status' => $status,
                    'language' => $language,
                ]);
                continue;
            }

            $parsed = $this->parseWeatherJson($answer, $language);
            if ($parsed === null) {
                $lastMessage = 'Модель '.$modelKey.' вернула невалидный JSON.';
                Log::warning('[plugin:weather] invalid_json', [
                    'model' => $modelKey,
                    'preview' => mb_substr(trim($answer), 0, 500),
                    'language' => $language,
                ]);
                continue;
            }

            if ($parsed['error'] !== null) {
                $lastMessage = $parsed['error'];
                Log::warning('[plugin:weather] invalid_weather', [
                    'model' => $modelKey,
                    'reason' => $parsed['error'],
                    'preview' => mb_substr(trim($answer), 0, 500),
                    'language' => $language,
                ]);
                continue;
            }

            return ['weather' => $parsed['weather'], 'model' => $modelKey];
        }

        throw new RuntimeException($lastMessage ?: 'Все AI-модели не смогли рассчитать погоду.');
    }

    private function loadMainPrompt(string $language, string $country = WeatherCountry::CH): string
    // Загрузка промта по коду языка из weather_promt
    {
        $country = WeatherCountry::normalize($country);
        $name = WeatherCountry::promptPrefix($country).$language;
        // Имя строки в БД, напр. glavnyy_prompt_he / japan_glavnyy_prompt_he

        $content = WeatherPromt::where('name', $name)->value('content');
        // SELECT content WHERE name = ...

        if (is_string($content) && trim($content) !== '') {
            return trim($content);
            // Промт найден — отдаём
        }

        $defaultCode = strtolower((string) (Language::getDefault()?->code ?? ''));
        // Язык по умолчанию из languages (is_default)

        if ($defaultCode !== '' && $language === $defaultCode) {
            // Только для языка по умолчанию — fallback
            $legacy = WeatherPromt::where('name', WeatherCountry::legacyPromptName($country))->value('content');
            // Старое единое имя промта

            if (is_string($legacy) && trim($legacy) !== '') {
                return trim($legacy);
            }
        }

        return '';
        // Промта нет — run() вернёт ошибку
    }

    private function loadSelectedModelKey(): string
    // Какая AI-модель включена в админке
    {
        $raw = WeatherPromt::where('name', WeatherAiModelChoice::SETTING_NAME)->value('content');
        // name = weather_ai_model, content = gemini-flash / openai-gpt-4o…

        return WeatherAiModelChoice::normalize(is_string($raw) ? $raw : null);
        // Безопасное значение или дефолт Flash
    }

    private function applyPlaceholders(string $prompt, string $monthName, string $regionName, string $language): string
    // Замена плейсхолдеров в тексте промта
    {
        return str_replace(
            ['{month_name}', '{region_name}', '{month}', '{region}', '{language}'],
            // Что ищем в промте (из подсказки в админке)
            [$monthName, $regionName, $monthName, $regionName, $language],
            // На что меняем — фактические значения с формы
            $prompt
        );
    }

    private function appendOutputRules(string $instruction, string $language): string
    // Добавить системные правила формата ответа
    {
        $suffix = match ($language) {
            'ar' => <<<'TXT'

---
SYSTEM (обязательно):
По Month/Region из SOURCE TEXT опиши типичную погоду для туриста.
Все поля JSON — непустые строки на арабском. Пустые "" недопустимы.
temperature / average_temperature: РОВНО 4 температуры — день (макс мин) и ночь (макс мин).
Формат строго: "+12° +8°|+3° -2°". Внутри пары: сначала выше, потом ниже.
precipitation: только "منخفض" или "متوسط" или "مرتفع"
season: только "ربيع" или "صيف" или "خريف" или "شتاء"
Верни только один JSON-объект, без Markdown и без текста до/после.
TXT,
            'he' => <<<'TXT'

---
SYSTEM (обязательно):
По Month/Region из SOURCE TEXT опиши типичную погоду для туриста.
Все поля JSON — непустые строки на иврите. Пустые "" недопустимы.
temperature / average_temperature: РОВНО 4 температуры — день (макс мин) и ночь (макс мин).
Формат строго: "+12° +8°|+3° -2°". Внутри пары: сначала выше, потом ниже.
precipitation: только "נמוך" или "בינוני" или "גבוה"
season: только "אביב" или "קיץ" или "סתיו" или "חורף"
Верни только один JSON-объект, без Markdown и без текста до/после.
TXT,
            default => <<<'TXT'

---
SYSTEM (обязательно):
По Month/Region из SOURCE TEXT опиши типичную погоду для туриста.
Все поля JSON должны быть заполнены непустыми строками. Пустые "" недопустимы.
temperature / average_temperature: РОВНО 4 температуры — день (макс мин) и ночь (макс мин).
Формат строго: "+12° +8°|+3° -2°". Внутри пары: сначала выше, потом ниже.
Верни только один JSON-объект, без Markdown и без текста до/после.
TXT,
        };

        return rtrim($instruction).$suffix;
    }

    /**
     * @return array{weather: array{temperature: string, precipitation: string, sunny_days: string, season: string, summary: string}, error: string|null}|null null = невалидный JSON
     */
    private function parseWeatherJson(string $raw, string $language = 'ru'): ?array
    // Разбор сырого ответа модели в структуру weather
    {
        $raw = trim($raw);
        // Убираем пробелы по краям

        if ($raw === '') {
            return null;
            // Пустая строка — не JSON
        }

        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $raw, $m)) {
            $raw = trim($m[1]);
            // Если модель обернула ответ в ```json ... ``` — берём содержимое
        }

        $start = strpos($raw, '{');
        // Первая фигурная скобка — начало объекта JSON

        $end = strrpos($raw, '}');
        // Последняя } — конец объекта (если модель добавила текст до/после)

        if ($start !== false && $end !== false && $end > $start) {
            $raw = substr($raw, $start, $end - $start + 1);
            // Оставляем только подстроку от { до }
        }

        $raw = preg_replace('/,\s*([}\]])/s', '$1', $raw) ?? $raw;
        // Убираем лишние запятые перед } ] (частая ошибка AI)

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            // Декод в ассоциативный массив; при ошибке — исключение
        } catch (\JsonException) {
            return null;
            // Невалидный JSON
        }

        if (! is_array($data)) {
            return null;
            // Корень должен быть объектом {}
        }

        $pick = static function (array $source, array $keys): string {
            // Взять первое непустое поле из списка имён (разные промты пишут по-разному)
            foreach ($keys as $key) {
                if (! array_key_exists($key, $source) || ! is_scalar($source[$key])) {
                    continue;
                    // Нет ключа или не строка/число — следующий вариант
                }
                $value = trim((string) $source[$key]);
                if ($value !== '') {
                    return $value;
                    // Нашли значение
                }
            }

            return '';
            // Ни один ключ не дал значения
        };

        $temperature = $this->normalizeDayNightTemperature(
            $pick($data, ['temperature', 'average_temperature', 'temp']),
            $language
        );
        // Температура — несколько возможных имён в JSON

        $precipitation = $pick($data, ['precipitation', 'precip']);
        // Осадки

        $sunnyDays = $pick($data, ['sunny_days', 'sunny']);
        // Солнечные дни

        $season = $pick($data, ['season']);
        // Сезон

        $summary = $pick($data, ['summary']);
        // Краткое описание (попадает в message)

        $tempParts = $this->splitDayNightTemperature($temperature);

        $weather = [
            'temperature' => $temperature,
            'temperature_day' => $tempParts['day'],
            'temperature_night' => $tempParts['night'],
            'precipitation' => $precipitation,
            'sunny_days' => $sunnyDays,
            'season' => $season,
            'summary' => $summary,
        ];
        // Единый формат для WP независимо от имён в ответе AI

        if ($temperature === '' && $precipitation === '' && $sunnyDays === '' && $season === '') {
            return [
                'weather' => $weather,
                'error' => 'Модель вернула пустые поля. Уберите из промта «не выдумывай значения» — month/region это только вход; погоду нужно описать самой.',
            ];
            // JSON есть, но данных нет — ошибка для пользователя
        }

        return [
            'weather' => $weather,
            'error' => null,
        ];
        // Успешный разбор
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

        if (preg_match('/^([+-]?\d+)\s*°?\s+([+-]?\d+)\s*°?$/u', $raw, $m)) {
            $day = (int) $m[1];
            $night = (int) $m[2];
            $spread = max(2, (int) round(abs($day - $night) * 0.35));

            return $this->formatDayNightRanges($day, $day - $spread, $night + $spread, $night);
        }

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

    private function formatDayNightRanges(int $d1, int $d2, int $n1, int $n2): string
    {
        $dMax = max($d1, $d2);
        $dMin = min($d1, $d2);
        $nMax = max($n1, $n2);
        $nMin = min($n1, $n2);

        return sprintf('%+d° %+d°|%+d° %+d°', $dMax, $dMin, $nMax, $nMin);
    }

    private function buildMaterial(string $monthName, string $regionName, string $language): string
    // Текст «исходника», который Gemini/OpenAI видит как SOURCE TEXT
    {
        return "Month: {$monthName}\nRegion: {$regionName}\nLanguage: {$language}";
        // Три строки — входные данные калькулятора
    }

    /** @return list<string> */
    private function fallbackModelChain(string $selectedModel): array
    {
        return [WeatherAiModelChoice::GEMINI_FLASH];
    }

    /**
     * @return array{text: ?string, http_status: ?int}
     */
    private function requestFromModel(string $modelKey, string $material, string $instruction): array
    {
        if ($modelKey === WeatherAiModelChoice::GEMINI_FLASH) {
            $timeout = max(60, (int) config('services.gemini.chat_timeout', 900));

            return [
                'text' => $this->gemini->chat($material, $instruction, $timeout),
                'http_status' => $this->gemini->lastHttpStatus(),
            ];
        }

        if ($modelKey === WeatherAiModelChoice::GEMINI_PRO) {
            $timeout = max(60, (int) config('services.gemini_pro.chat_timeout', 1800));
            $maxOutputTokens = (int) config('services.gemini_pro.max_output_tokens', 65536);

            return [
                'text' => $this->geminiPro->chat($material, $instruction, $timeout, [
                    'maxOutputTokens' => max(8192, $maxOutputTokens),
                ]),
                'http_status' => $this->geminiPro->lastHttpStatus(),
            ];
        }

        if (WeatherAiModelChoice::isOpenAi($modelKey)) {
            $openAiModel = WeatherAiModelChoice::openAiModelId($modelKey);

            return [
                'text' => $this->openAi->askOpenAiWithModel(
                    $instruction,
                    $material,
                    $openAiModel,
                    'WeatherAi:'.$modelKey
                ),
                'http_status' => null,
            ];
        }

        throw new RuntimeException('Неизвестная модель: '.$modelKey);
    }

    private function isGeminiModel(string $modelKey): bool
    {
        return in_array($modelKey, [
            WeatherAiModelChoice::GEMINI_FLASH,
            WeatherAiModelChoice::GEMINI_PRO,
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
