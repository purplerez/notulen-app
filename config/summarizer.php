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
    | Driver Configurations
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'fake' => [
            // Deterministic testing and local development driver
        ],
        'llm' => [
            'provider' => env('LLM_PROVIDER', null), // Unassigned provider per architectural plan
            'model' => env('LLM_MODEL', null),
            'timeout' => (int) env('LLM_TIMEOUT', 120),
            'max_retries' => (int) env('LLM_MAX_RETRIES', 3),
            'max_chunk_words' => (int) env('LLM_MAX_CHUNK_WORDS', 2500),
        ],
    ],
];
