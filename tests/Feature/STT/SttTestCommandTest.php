<?php

namespace Tests\Feature\STT;

use App\Contracts\SpeechToTextProvider;
use App\DTOs\TranscriptionResult;
use Tests\TestCase;

class SttTestCommandTest extends TestCase
{
    public function test_stt_test_command_fails_if_file_does_not_exist(): void
    {
        $this->artisan('stt:test', ['file' => '/path/to/missing/audio.webm'])
            ->assertFailed()
            ->expectsOutputToContain('Berkas audio tidak ditemukan');
    }

    public function test_stt_test_command_succeeds_with_valid_file(): void
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_cmd_');
        file_put_contents($tempPath, 'fake-audio-bytes');

        try {
            $mockProvider = $this->createMock(SpeechToTextProvider::class);
            $mockProvider->expects($this->once())
                ->method('transcribe')
                ->willReturn(new TranscriptionResult(
                    text: 'Halo ini uji coba transkripsi CLI.',
                    segments: [
                        ['start' => 0.0, 'end' => 3.2, 'text' => ' Halo ini uji coba'],
                        ['start' => 3.2, 'end' => 6.0, 'text' => ' transkripsi CLI.'],
                    ],
                    duration: 6.0
                ));

            $this->app->instance(SpeechToTextProvider::class, $mockProvider);

            $this->artisan('stt:test', ['file' => $tempPath])
                ->assertSuccessful()
                ->expectsOutputToContain('Transkripsi Berhasil!')
                ->expectsOutputToContain('Halo ini uji coba transkripsi CLI.');
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
