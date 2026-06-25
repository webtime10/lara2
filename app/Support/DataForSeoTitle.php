<?php

namespace App\Support;

final class DataForSeoTitle
{
    public const MAX_LENGTH = 255;

    public static function normalize(mixed $title): ?string
    {
        $title = trim((string) $title);
        if ($title === '') {
            return null;
        }

        if (mb_strlen($title) > self::MAX_LENGTH) {
            return mb_substr($title, 0, self::MAX_LENGTH);
        }

        return $title;
    }
}
