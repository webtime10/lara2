<?php

namespace App\Services;

/**
 * Gemini Pro — отдельный ключ и модель (GEMINI_PRO_API_KEY, GEMINI_CREATIVE_MODEL).
 */
class GeminiProService extends GeminiService
{
    public function __construct()
    {
        parent::__construct(
            configKeyPath: 'services.gemini_pro.key',
            configModelPath: 'services.gemini_pro.model',
            logTag: 'GeminiProService',
            missingKeyEnvHint: 'GEMINI_PRO_API_KEY',
            missingModelEnvHint: 'GEMINI_CREATIVE_MODEL',
        );
    }
}
