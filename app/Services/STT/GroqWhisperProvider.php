<?php

namespace App\Services\STT;

use App\Contracts\SpeechToTextProvider;
use App\DTOs\TranscriptionResult;
use App\Exceptions\RateLimitException;
use App\Exceptions\TranscriptionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class GroqWhisperProvider implements SpeechToTextProvider
{
    /**
     * Transcribe an audio file using Groq's OpenAI-compatible Whisper endpoint.
     */
    public function transcribe(string $path, string $mime, ?string $language = null): TranscriptionResult
    {
        if (! file_exists($path)) {
            throw new TranscriptionException("Berkas audio tidak ditemukan di jalur: {$path}");
        }

        $apiKey = trim((string) config('stt.drivers.groq.key'));
        $apiKey = trim($apiKey, "\"' \t\n\r\0\x0B");

        if (empty($apiKey)) {
            $apiKey = trim((string) env('GROQ_API_KEY', ''));
            $apiKey = trim($apiKey, "\"' \t\n\r\0\x0B");
        }

        if (empty($apiKey)) {
            throw new TranscriptionException('GROQ_API_KEY belum dikonfigurasi di lingkungan aplikasi (.env).');
        }

        $endpoint = config('stt.drivers.groq.endpoint', 'https://api.groq.com/openai/v1/audio/transcriptions');
        $model = config('stt.drivers.groq.model', 'whisper-large-v3-turbo');
        $timeout = (int) config('stt.drivers.groq.timeout', 120);
        $lang = $language ?: config('stt.language', 'id');

        $fileResource = fopen($path, 'r');
        if ($fileResource === false) {
            throw new TranscriptionException("Gagal membaca berkas audio di jalur: {$path}");
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->attach('file', $fileResource, basename($path))
                ->post($endpoint, array_filter([
                    'model' => $model,
                    'language' => $lang,
                    'response_format' => 'verbose_json',
                ]));
        } catch (ConnectionException $e) {
            throw new TranscriptionException("Koneksi ke endpoint STT timeout atau gagal: {$e->getMessage()}", 504, $e);
        } finally {
            if (is_resource($fileResource)) {
                fclose($fileResource);
            }
        }

        if ($response->status() === 429) {
            $this->handleRateLimitResponse($response);
        }

        if (! $response->successful()) {
            $errorJson = $response->json();
            $errorMessage = $errorJson['error']['message'] ?? ($response->body() ?: 'Galat API Groq');
            throw new TranscriptionException(
                "STT Groq menghasilkan galat HTTP {$response->status()}: {$errorMessage}",
                $response->status(),
                null,
                ['body' => $errorJson]
            );
        }

        $json = $response->json();
        $text = (string) ($json['text'] ?? '');
        $duration = (float) ($json['duration'] ?? 0.0);

        $segments = [];
        if (! empty($json['segments']) && is_array($json['segments'])) {
            foreach ($json['segments'] as $seg) {
                $segments[] = [
                    'start' => (float) ($seg['start'] ?? 0.0),
                    'end' => (float) ($seg['end'] ?? 0.0),
                    'text' => (string) ($seg['text'] ?? ''),
                ];
            }
        }

        return new TranscriptionResult(
            text: $text,
            segments: $segments,
            duration: $duration,
            raw: $json
        );
    }

    /**
     * Parse 429 response and throw structured RateLimitException.
     *
     * @throws RateLimitException
     */
    protected function handleRateLimitResponse(Response $response): never
    {
        $json = $response->json() ?? [];
        $message = $json['error']['message'] ?? 'Batas panggilan API Groq tercapai (Rate limit exceeded).';

        // 1. Determine Retry-After seconds
        $retryAfterHeader = $response->header('Retry-After');
        $retryAfterSeconds = 15; // default fallback

        if (! empty($retryAfterHeader)) {
            if (is_numeric($retryAfterHeader)) {
                $retryAfterSeconds = (int) $retryAfterHeader;
            } else {
                try {
                    $retryAfterDate = Carbon::parse($retryAfterHeader);
                    $diff = (int) now()->diffInSeconds($retryAfterDate, false);
                    if ($diff > 0) {
                        $retryAfterSeconds = $diff;
                    }
                } catch (\Throwable) {
                    // ignore parse exception and use fallback
                }
            }
        }

        // 2. Check if this is a daily quota exhaustion
        $lowerMsg = strtolower($message);
        $isDailyQuota = str_contains($lowerMsg, 'per day')
            || str_contains($lowerMsg, 'daily limit')
            || str_contains($lowerMsg, 'rpd') // requests per day
            || str_contains($lowerMsg, 'tpd') // tokens per day
            || $retryAfterSeconds > 3600;

        throw new RateLimitException(
            message: $message,
            retryAfterSeconds: max(1, $retryAfterSeconds),
            isDailyQuota: $isDailyQuota,
            responseBody: $json
        );
    }
}
