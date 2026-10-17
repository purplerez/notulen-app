<?php

namespace Tests\Unit\Services;

use App\Exceptions\RateLimitException;
use App\Exceptions\TranscriptionException;
use App\Services\STT\GroqWhisperProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GroqWhisperProviderTest extends TestCase
{
    private string $tempFilePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempFilePath = tempnam(sys_get_temp_dir(), 'test_audio_');
        file_put_contents($this->tempFilePath, 'fake-audio-bytes');

        Config::set('stt.drivers.groq.key', 'fake-groq-key');
        Config::set('stt.drivers.groq.endpoint', 'https://api.groq.com/openai/v1/audio/transcriptions');
        Config::set('stt.drivers.groq.model', 'whisper-large-v3-turbo');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }

        parent::tearDown();
    }

    public function test_transcribe_parses_verbose_json_response_into_transcription_result(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/audio/transcriptions' => Http::response([
                'task' => 'transcribe',
                'language' => 'indonesian',
                'duration' => 60.0,
                'text' => 'Halo semuanya, selamat pagi.',
                'segments' => [
                    [
                        'id' => 0,
                        'start' => 0.0,
                        'end' => 3.5,
                        'text' => ' Halo semuanya,',
                    ],
                    [
                        'id' => 1,
                        'start' => 3.5,
                        'end' => 6.0,
                        'text' => ' selamat pagi.',
                    ],
                ],
            ], 200),
        ]);

        $provider = new GroqWhisperProvider;
        $result = $provider->transcribe($this->tempFilePath, 'audio/webm', 'id');

        $this->assertEquals('Halo semuanya, selamat pagi.', $result->text);
        $this->assertEquals(60.0, $result->duration);
        $this->assertCount(2, $result->segments);
        $this->assertEquals(0.0, $result->segments[0]['start']);
        $this->assertEquals(3.5, $result->segments[0]['end']);
        $this->assertEquals(' Halo semuanya,', $result->segments[0]['text']);
    }

    public function test_transcribe_throws_rate_limit_exception_on_429_with_retry_after(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/audio/transcriptions' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached for requests per minute (RPM).',
                    'type' => 'requests',
                ],
            ], 429, ['Retry-After' => '25']),
        ]);

        $provider = new GroqWhisperProvider;

        try {
            $provider->transcribe($this->tempFilePath, 'audio/webm', 'id');
            $this->fail('Expected RateLimitException was not thrown.');
        } catch (RateLimitException $e) {
            $this->assertEquals(25, $e->getRetryAfterSeconds());
            $this->assertFalse($e->isDailyQuota());
            $this->assertStringContainsString('Rate limit reached', $e->getMessage());
        }
    }

    public function test_transcribe_detects_daily_quota_limit(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/audio/transcriptions' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached for requests per day (RPD). Please wait until reset.',
                    'type' => 'daily_limit',
                ],
            ], 429, ['Retry-After' => '14400']), // 4 hours
        ]);

        $provider = new GroqWhisperProvider;

        try {
            $provider->transcribe($this->tempFilePath, 'audio/webm', 'id');
            $this->fail('Expected RateLimitException was not thrown.');
        } catch (RateLimitException $e) {
            $this->assertEquals(14400, $e->getRetryAfterSeconds());
            $this->assertTrue($e->isDailyQuota());
        }
    }

    public function test_transcribe_throws_transcription_exception_on_5xx(): void
    {
        Http::fake([
            'https://api.groq.com/openai/v1/audio/transcriptions' => Http::response([
                'error' => ['message' => 'Internal Server Error from Groq.'],
            ], 500),
        ]);

        $provider = new GroqWhisperProvider;

        $this->expectException(TranscriptionException::class);
        $provider->transcribe($this->tempFilePath, 'audio/webm', 'id');
    }

    public function test_transcribe_throws_transcription_exception_when_file_not_found(): void
    {
        $provider = new GroqWhisperProvider;

        $this->expectException(TranscriptionException::class);
        $provider->transcribe('/non/existent/path/to/audio.webm', 'audio/webm', 'id');
    }
}
