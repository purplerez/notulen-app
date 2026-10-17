<?php

namespace App\Contracts;

use App\DTOs\TranscriptionResult;
use App\Exceptions\RateLimitException;
use App\Exceptions\TranscriptionException;

interface SpeechToTextProvider
{
    /**
     * Transcribe an audio file into text and timestamped segments.
     *
     * @param  string  $path  Absolute local path to the audio file
     * @param  string  $mime  MIME type of the audio file
     * @param  string|null  $language  ISO language code (e.g. 'id')
     *
     * @throws RateLimitException
     * @throws TranscriptionException
     */
    public function transcribe(string $path, string $mime, ?string $language = null): TranscriptionResult;
}
