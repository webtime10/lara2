<?php

namespace App\Exceptions;

use RuntimeException;

class WeatherGeminiRateLimitedException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfterSeconds = 45,
        string $message = 'Gemini rate limit (429)',
    ) {
        parent::__construct($message);
    }
}
