<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Summarizer Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "fake", "llm"
    | In development and testing, "fake" provides deterministic rule-based output.
    |
    */
    'driver' => env('SUMMARIZER_DRIVER', 'fake'),

    /*
    |--------------------------------------------------------------------------
    | AI Transcript Polishing
    |--------------------------------------------------------------------------
    |
    | When enabled, a PolishTranscript job will run after each STT segment
    | completes, cleaning filler words and improving punctuation via LLM
    | before minutes are generated.
    |
    */
    'polish_enabled' => (bool) env('LLM_POLISH_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Driver Configurations
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'fake' => [
            // Deterministic testing and local development driver
        ],
        'llm' => [
            'provider' => env('LLM_PROVIDER', 'groq'),
            'model' => env('LLM_MODEL', 'openai/gpt-oss-120b'),
            // api_key: if empty, falls back to GROQ_API_KEY (stt.drivers.groq.key)
            'api_key' => env('LLM_API_KEY', null),
            'timeout' => (int) env('LLM_TIMEOUT', 120),
            'max_retries' => (int) env('LLM_MAX_RETRIES', 3),
            'max_chunk_words' => (int) env('LLM_MAX_CHUNK_WORDS', 2500),
        ],
    ],
];
