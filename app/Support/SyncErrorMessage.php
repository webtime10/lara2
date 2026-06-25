<?php

namespace App\Support;

final class SyncErrorMessage
{
    public static function format(\Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'Data too long for column')) {
            return 'Слишком длинное название в ответе API. Обновите страницу и повторите заливку.';
        }

        if (str_contains($message, 'Duplicate entry')) {
            return 'Дубликат в ответе API. Повторите импорт: существующая запись будет обновлена.';
        }

        if (mb_strlen($message) > 240) {
            return mb_substr($message, 0, 240).'…';
        }

        return $message;
    }
}
