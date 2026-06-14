<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Читать только через config('services.openai.*') / config('services.gemini.*').
    */
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'keys_csv' => env('OPENAI_API_KEYS', ''),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'extraction_model' => env('OPENAI_EXTRACTION_MODEL', 'gpt-4o-mini'),
        'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 16384),
        'rate_limit_retries' => (int) env('OPENAI_RATE_LIMIT_RETRIES', 8),
        'rate_limit_wait_base_sec' => (int) env('OPENAI_RATE_LIMIT_WAIT_BASE_SEC', 10),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'chat_timeout' => (int) env('GEMINI_CHAT_TIMEOUT', 900),
        'extraction_timeout' => (int) env('GEMINI_EXTRACTION_TIMEOUT', 1800),
    ],

    'gemini_pro' => [
        'key' => env('GEMINI_PRO_API_KEY'),
        'model' => env('GEMINI_CREATIVE_MODEL', 'gemini-2.5-pro'),
        'max_output_tokens' => (int) env('GEMINI_PRO_MAX_OUTPUT_TOKENS', 65536),
        'chat_timeout' => (int) env('GEMINI_PRO_CHAT_TIMEOUT', 1800),
    ],

    /*
    | Ключи входящих webhook от WP-плагинов (заголовок X-Plugin-Api-Key).
    */
    'plugins' => [
        'default_api_key' => env('PLUGIN_API_KEY', env('WORDPRESS_WEBHOOK_SECRET', '')),
        'weather' => [
            'api_key' => env('PLUGIN_WEATHER_API_KEY', env('AI_CALCULATOR_LARA_API_KEY', env('PLUGIN_API_KEY', env('WORDPRESS_WEBHOOK_SECRET', '')))),
            'cache' => [
                'enabled' => filter_var(env('WEATHER_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),
                'ttl_hours' => (int) env('WEATHER_CACHE_TTL_HOURS', 48),
            ],
        ],
        'budget' => [
            'api_key' => env('PLUGIN_BUDGET_API_KEY', env('PLUGIN_API_KEY', env('WORDPRESS_WEBHOOK_SECRET', ''))),
            'cache' => [
                'enabled' => filter_var(env('BUDGET_CACHE_ENABLED', true), FILTER_VALIDATE_BOOL),
                'ttl_hours' => (int) env('BUDGET_CACHE_TTL_HOURS', 48),
            ],
        ],
    ],

];
