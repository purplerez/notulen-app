<?php

namespace App\DTOs;

class TranscriptionResult
{
    /**
     * @param  string  $text  Full concatenated transcription text
     * @param  array<int, array{start: float, end: float, text: string}>  $segments  Timestamped segments
     * @param  float  $duration  Audio duration in seconds
     * @param  array<string, mixed>  $raw  Raw response from the provider
     */
    public function __construct(
        public string $text,
        public array $segments,
        public float $duration,
        public array $raw = []
    ) {}
}
