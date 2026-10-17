<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Private Storage Disk
    |--------------------------------------------------------------------------
    | The disk where meeting audio segments and merged files are stored.
    | Must be a private disk (never public).
    */
    'disk' => env('RECORDING_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Audio Retention (Months)
    |--------------------------------------------------------------------------
    | The retention period for raw audio files in months before purge.
    | Audio is deleted after this duration from when all segments are saved.
    */
    'retention_months' => (int) env('RECORDING_RETENTION_MONTHS', 1),

    /*
    |--------------------------------------------------------------------------
    | Maximum Recording Duration (Minutes)
    |--------------------------------------------------------------------------
    | Upper limit for a single recording session (default: 180 minutes / 3 hours).
    */
    'max_duration_minutes' => (int) env('RECORDING_MAX_DURATION_MINUTES', 180),

    /*
    |--------------------------------------------------------------------------
    | Maximum Segment Size (Kilobytes)
    |--------------------------------------------------------------------------
    | 25 MB = 25600 KB (aligned with Whisper / Groq file upload limit)
    */
    'max_segment_size_kb' => (int) env('RECORDING_MAX_SEGMENT_SIZE_KB', 25600),

    /*
    |--------------------------------------------------------------------------
    | Supported Audio Mime Types
    |--------------------------------------------------------------------------
    | WebM (Chrome/Firefox), MP4/AAC (Safari/iOS), WAV, Ogg.
    */
    'allowed_mimes' => [
        'audio/webm',
        'video/webm', // Browsers frequently tag WebM audio-only streams as video/webm
        'audio/mp4',
        'video/mp4',  // Safari iOS occasionally sets container mime to video/mp4
        'audio/aac',
        'audio/x-m4a',
        'audio/m4a',
        'audio/wav',
        'audio/x-wav',
        'audio/ogg',
        'audio/opus',
    ],
];
