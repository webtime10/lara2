<?php

namespace App\Support;

/**
 * Страны для Weather admin / sync.
 */
final class WeatherCountry
{
    public const CH = 'ch';

    public const JP = 'jp';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::CH, self::JP];
    }

    public static function normalize(?string $country): string
    {
        $country = strtolower(trim((string) $country));

        return in_array($country, self::all(), true) ? $country : self::CH;
    }

    public static function label(string $country): string
    {
        return match (self::normalize($country)) {
            self::JP => 'Япония',
            default => 'Швейцария',
        };
    }

    /**
     * @return class-string
     */
    public static function regionsClass(string $country): string
    {
        return self::normalize($country) === self::JP
            ? JapanWeatherPrefectures::class
            : SwissWeatherCantons::class;
    }

    public static function promptPrefix(string $country): string
    {
        return self::normalize($country) === self::JP
            ? 'japan_glavnyy_prompt_'
            : 'glavnyy_prompt_';
    }

    public static function legacyPromptName(string $country): string
    {
        return self::normalize($country) === self::JP
            ? 'japan_glavnyy_prompt'
            : 'glavnyy_prompt';
    }

    public static function promptAdminPath(string $country): string
    {
        return self::normalize($country) === self::JP
            ? 'Промты → Япония → Погода'
            : 'Промты → Швейцария → Погода';
    }
}
