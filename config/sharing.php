<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sharing & Public Access Gate Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for public share link token generation, rate limiting,
    | brute-force protection, and access gate lockouts.
    |
    */

    // Minimum token length in characters (minimum 48)
    'token_min_length' => (int) env('SHARE_TOKEN_MIN_LENGTH', 48),

    // Maximum failed verification attempts before temporary link lockout
    'max_failed_attempts' => (int) env('SHARE_MAX_FAILED_ATTEMPTS', 5),

    // Temporary lockout duration in minutes after exceeding max failed attempts
    'lockout_duration_minutes' => (int) env('SHARE_LOCKOUT_MINUTES', 15),

    // IP rate limit throttle (attempts per minute per IP)
    'rate_limit_per_minute' => (int) env('SHARE_RATE_LIMIT_PER_MINUTE', 10),

    // Session lifetime in minutes after successfully passing the verification gate
    'session_lifetime_minutes' => (int) env('SHARE_SESSION_LIFETIME_MINUTES', 60),

    // Uniform error message for gate validation failures (anti-enumeration)
    'uniform_error_message' => 'Akses ditolak. Nomor WhatsApp atau kode PIN yang Anda masukkan tidak terdaftar atau tidak sesuai.',
];
