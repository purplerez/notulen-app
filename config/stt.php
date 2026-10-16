<?php

return [
    'driver' => env('STT_DRIVER', 'groq'),
    'language' => env('STT_LANGUAGE', 'id'),
    'drivers' => [
        'groq' => [
            'key' => env('GROQ_API_KEY'),
            'endpoint' => 'https://api.groq.com/openai/v1/audio/transcriptions',
            'model' => env('GROQ_STT_MODEL', 'whisper-large-v3-turbo'),
            'timeout' => 120,
        ],
    ],
];
