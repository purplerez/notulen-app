<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Speech-to-Text Driver
    |--------------------------------------------------------------------------
    */
    'driver' => env('STT_DRIVER', 'groq'),

    /*
    |--------------------------------------------------------------------------
    | Default Language for Transcription
    |--------------------------------------------------------------------------
    | ISO language code (e.g. 'id' for Indonesian).
    */
    'language' => env('STT_LANGUAGE', 'id'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting & Quota Thresholds
    |--------------------------------------------------------------------------
    | - rpm_limit: Max requests per minute sent to the STT API (default 18, Groq free tier limit is 20).
    | - ash_limit: Max audio seconds processed per hour across the platform (default 7000, Groq limit is 7200).
    */
    'rpm_limit' => (int) env('STT_RPM_LIMIT', 18),
    'ash_limit' => (int) env('STT_ASH_LIMIT', 7000),

    /*
    |--------------------------------------------------------------------------
    | Driver Configurations
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'groq' => [
            'key' => env('GROQ_API_KEY'),
            'endpoint' => env('GROQ_STT_ENDPOINT', 'https://api.groq.com/openai/v1/audio/transcriptions'),
            'model' => env('GROQ_STT_MODEL', 'whisper-large-v3-turbo'),
            'timeout' => (int) env('GROQ_STT_TIMEOUT', 120),
        ],
    ],
];
