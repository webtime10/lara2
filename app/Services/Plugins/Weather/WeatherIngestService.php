<?php

/*
Получает данные от WordPress, пытается найти готовый результат в кэше, если не находит обращается к AI, сохраняет результат и отдаёт ответ обратно.
Да. Все пользователи будут получать один и тот же сохранённый результат, пока не истечёт TTL (у тебя сейчас 48 часов) или пока не изменятся параметры, из которых строится ключ кэша. 🚀

*/
// Открывающий тег PHP — точка входа в файл

namespace App\Services\Plugins\Weather;
// Пространство имён: сервисы плагина «погода» в Laravel

use App\Models\Language;
// Модель таблицы languages (колонка code: en, he, ar…)

use App\Models\WeatherPromt;
// Модель таблицы weather_promt — промты и выбор модели AI из админки

use App\Support\WeatherAiModelChoice;
// Список допустимых моделей (Gemini Flash/Pro, OpenAI…) и normalize()

use Illuminate\Support\Facades\Cache;
// Фасад кэша Laravel (file/redis — из CACHE_STORE в .env)

use Illuminate\Support\Facades\Log;
// Запись в storage/logs/laravel.log

use Illuminate\Support\Str;
// Хелперы строк; здесь — генерация UUID для request_id

class WeatherIngestService
// Сервис приёма запроса от WordPress: кэш + AI + ответ JSON
{
    public const PLUGIN = 'weather';
    // Имя плагина в ответе API (поле plugin)

    private const CACHE_KEY_PREFIX = 'plugin_weather_result:';
    // Префикс ключей кэша, чтобы не пересекаться с другими данными

    public function __construct(
        private WeatherAiService $ai,
        // Внедряем сервис, который ходит в Gemini/OpenAI
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function accept(array $payload): array
    // Главный метод: принять данные с WP, вернуть массив для JSON-ответа
    {
        $requestId = (string) Str::uuid();
        // Уникальный id этого HTTP-запроса (для логов и отладки)

        Log::info('[plugin:weather] incoming', [
            'request_id' => $requestId,
            'month_name' => $payload['month_name'] ?? null,
            'region_name' => $payload['region_name'] ?? null,
            'language' => $payload['language'] ?? null,
        ]);
        // Пишем в лог: пришёл запрос и с какими полями

        $fromCache = false;
        // Потом в ответе: true = ответ из кэша, false = свежий AI

        $aiResult = null;
        // Результат AI (или из кэша): ok, message, weather, model, language

        $cacheKey = null;
        // Строка-ключ в кэше; null если кэш выключен или данные невалидны

        $cacheEnabled = filter_var(config('services.plugins.weather.cache.enabled', true), FILTER_VALIDATE_BOOL);
        // Читает WEATHER_CACHE_ENABLED из .env (через config/services.php)

        $cacheTtl = max(3600, (int) config('services.plugins.weather.cache.ttl_hours', 48) * 3600);
        // Срок жизни записи в секундах (минимум 1 час); по умолчанию 48 ч

        if ($cacheEnabled) {
            // Блок: попытаться отдать ответ без вызова AI
            $language = strtolower(trim((string) ($payload['language'] ?? '')));
            // Код языка из WP — как в languages.code (en, he, ar)

            $month = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($payload['month_name'] ?? '')) ?: ''), 'UTF-8');
            // Месяц: без лишних пробелов, нижний регистр — для одинакового ключа кэша

            $region = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) ($payload['region_name'] ?? '')) ?: ''), 'UTF-8');
            // Регион — так же нормализуем для кэша

            if (in_array($language, Language::activeCodes(), true) && $month !== '' && $region !== '') {
                // Кэш только для активных языков из БД и непустых month/region
                $modelRaw = WeatherPromt::where('name', WeatherAiModelChoice::SETTING_NAME)->value('content');
                // Из БД: какая модель выбрана в админке (weather_ai_model)

                $modelKey = WeatherAiModelChoice::normalize(is_string($modelRaw) ? $modelRaw : null);
                // Приводим к внутреннему ключу (gemini-flash, openai-gpt-4o…)

                $promptName = 'glavnyy_prompt_'.$language;
                // Имя записи промта для языка, напр. glavnyy_prompt_en

                $promptText = WeatherPromt::where('name', $promptName)->value('content');
                // Текст промта из таблицы weather_promt

                $defaultCode = strtolower((string) (Language::getDefault()?->code ?? ''));
                // code языка по умолчанию (is_default=1 в languages)

                if ((! is_string($promptText) || trim($promptText) === '') && $defaultCode !== '' && $language === $defaultCode) {
                    // Если промта для языка нет — для языка по умолчанию берём старый ключ
                    $promptText = WeatherPromt::where('name', 'glavnyy_prompt')->value('content');
                    // Легаси-имя одного общего промта до разбивки по языкам
                }

                $promptHash = sha1(is_string($promptText) ? trim($promptText) : '');
                // Хэш промта: сменили текст в админке — другой кэш

                $cacheKey = self::CACHE_KEY_PREFIX.sha1('v1|'.$language.'|'.$month.'|'.$region.'|'.$modelKey.'|'.$promptHash);
                // Итоговый ключ: язык + месяц + регион + модель + версия промта

                $stored = Cache::get($cacheKey);
                // Читаем сохранённый ответ из кэша (file/database/redis)

                if (is_array($stored) && ! empty($stored['ok']) && is_array($stored['weather'] ?? null)) {
                    // В кэше есть успешный ответ с блоком weather
                    $fromCache = true;
                    // Помечаем, что Gemini/OpenAI не вызывали
                    $aiResult = [
                        'ok' => true,
                        'message' => (string) ($stored['message'] ?? ''),
                        'weather' => $stored['weather'],
                        'model' => isset($stored['model']) ? (string) $stored['model'] : null,
                        'language' => isset($stored['language']) ? (string) $stored['language'] : null,
                    ];
                    // Собираем тот же формат, что отдаёт WeatherAiService::run()
                    Log::info('[plugin:weather] cache hit', ['cache_key' => $cacheKey]);
                    // Лог: ответ отдан из кэша
                }
            }
        }

        if ($aiResult === null) {
            // Кэша нет или промах — идём в AI
            $aiResult = $this->ai->run($payload);
            // WeatherAiService: промт из БД → Gemini/OpenAI → разбор JSON

            if ($cacheEnabled && $cacheKey !== null && ! empty($aiResult['ok']) && is_array($aiResult['weather'] ?? null)) {
                // Успешный ответ AI — сохраняем на следующий раз
                Cache::put($cacheKey, [
                    'ok' => true,
                    'message' => (string) ($aiResult['message'] ?? ''),
                    'weather' => $aiResult['weather'],
                    'model' => $aiResult['model'] ?? null,
                    'language' => $aiResult['language'] ?? null,
                    'cached_at' => now()->toIso8601String(),
                ], $cacheTtl);
                // put: данные + TTL в секундах
                Log::info('[plugin:weather] cache stored', ['cache_key' => $cacheKey, 'ttl_seconds' => $cacheTtl]);
                // Лог: записали в кэш
            }
        }

        $response = [
            'ok' => (bool) ($aiResult['ok'] ?? false),
            // Успех операции для WP
            'plugin' => self::PLUGIN,
            // Всегда "weather"
            'request_id' => $requestId,
            // UUID этого запроса
            'message' => (string) ($aiResult['message'] ?? ''),
            // Текст (summary или ошибка)
            'model' => $aiResult['model'] ?? null,
            // Какая модель отвечала (ключ из админки)
            'language' => $aiResult['language'] ?? ($payload['language'] ?? null),
            // Код языка en/he/ar
            'from_cache' => $fromCache,
            // true = без вызова API
            'received' => $payload,
            // Эхо входящих данных (для отладки на WP)
        ];

        if (! empty($aiResult['weather']) && is_array($aiResult['weather'])) {
            // Блок для фронта: температура, осадки, сезон…
            $response['weather'] = $aiResult['weather'];
        }

        return $response;
        // Контроллер отдаст это как JSON WordPress
    }
}
