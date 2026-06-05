<?php

namespace App\Support;

/**
 * Модели для weather-калькулятора (админка → WeatherAiService).
 */
final class WeatherAiModelChoice
{
    public const GEMINI_FLASH = 'gemini-flash';

    public const GEMINI_PRO = 'gemini-pro';

    public const OPENAI_GPT_4O = 'openai-gpt-4o';

    public const OPENAI_GPT_4O_MINI = 'openai-gpt-4o-mini';

    public const OPENAI_GPT_54 = 'openai-gpt-5.4';

    public const SETTING_NAME = 'weather_ai_model';

    /** @return list<string> */
    public static function keys(): array
    {
        return [
            self::GEMINI_FLASH,
            self::GEMINI_PRO,
            self::OPENAI_GPT_4O,
            self::OPENAI_GPT_4O_MINI,
            self::OPENAI_GPT_54,
        ];
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::GEMINI_FLASH => 'Gemini Flash',
            self::GEMINI_PRO => 'Gemini Pro',
            self::OPENAI_GPT_4O => 'OpenAI GPT-4o',
            self::OPENAI_GPT_4O_MINI => 'GPT-4o Mini',
            self::OPENAI_GPT_54 => 'GPT-5.4',
        ];
    }

    public static function default(): string
    {
        return self::GEMINI_FLASH;
    }

    public static function normalize(?string $raw): string
    {
        $key = is_string($raw) ? trim($raw) : '';
        $allowed = array_flip(self::keys());

        return isset($allowed[$key]) ? $key : self::default();
    }

    public static function isOpenAi(string $modelKey): bool
    {
        return str_starts_with($modelKey, 'openai-');
    }

    public static function openAiModelId(string $modelKey): string
    {
        return match ($modelKey) {
            self::OPENAI_GPT_4O => 'gpt-4o',
            self::OPENAI_GPT_4O_MINI => 'gpt-4o-mini',
            self::OPENAI_GPT_54 => trim((string) config('services.openai.model', 'gpt-5.4')) ?: 'gpt-5.4',
            default => throw new \InvalidArgumentException('Не OpenAI ключ модели: '.$modelKey),
        };
    }
}
