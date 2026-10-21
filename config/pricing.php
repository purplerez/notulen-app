<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Foreign Exchange Rate (USD to IDR)
    |--------------------------------------------------------------------------
    | Fallback exchange rate used when no record exists in the fx_rates table.
    */
    'default_fx_rate' => (float) env('PRICING_DEFAULT_FX_RATE', 16000.00),

    /*
    |--------------------------------------------------------------------------
    | Default Service Rates (USD)
    |--------------------------------------------------------------------------
    | Initial baseline prices used when no specific price_rates record matches.
    */
    'defaults' => [
        'stt' => [
            'provider' => 'groq',
            'model' => 'whisper-large-v3-turbo',
            'unit' => 'detik_audio',
            'price_usd' => 0.00005000, // ~$0.18 per hour of audio
        ],
        'llm' => [
            'provider' => 'groq',
            'model' => 'llama-3.3-70b-versatile',
            'unit' => 'token',
            'price_usd' => 0.00000080, // ~$0.80 per 1M tokens
        ],
        'storage' => [
            'provider' => 'local_disk',
            'model' => 'audio_storage',
            'unit' => 'gb_bulan',
            'price_usd' => 0.02300000, // ~$0.023 per GB-month (standard cloud storage tier)
        ],
    ],
];
