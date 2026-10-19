<?php

namespace Tests\Unit\Services;

use App\Services\Minutes\LlmSummarizer;
use RuntimeException;
use Tests\TestCase;

class LlmSummarizerTest extends TestCase
{
    public function test_chunk_transcript_splits_by_max_words(): void
    {
        $summarizer = new LlmSummarizer;
        $words = array_fill(0, 500, 'kata');
        $transcript = implode(' ', $words);

        $chunks = $summarizer->chunkTranscript($transcript, 200);

        $this->assertCount(3, $chunks);
        $this->assertSame(200, count(explode(' ', $chunks[0])));
        $this->assertSame(200, count(explode(' ', $chunks[1])));
        $this->assertSame(100, count(explode(' ', $chunks[2])));
    }

    public function test_parse_and_validate_json_handles_plain_and_markdown_json(): void
    {
        $summarizer = new LlmSummarizer;

        $validPayload = json_encode([
            'ringkasan' => 'Ringkasan rapat.',
            'agenda' => ['Agenda 1'],
            'pembahasan' => 'Pembahasan materi.',
            'keputusan' => ['Keputusan 1'],
            'tindak_lanjut' => [
                ['description' => 'Tugas A', 'assignee_name' => 'Budi', 'due_date' => '2026-10-25'],
            ],
        ]);

        $markdownWrapped = "Berikut adalah notulen:\n```json\n{$validPayload}\n```\nSemoga membantu.";

        $result1 = $summarizer->parseAndValidateJson($validPayload);
        $result2 = $summarizer->parseAndValidateJson($markdownWrapped);

        $this->assertNotNull($result1);
        $this->assertSame('Ringkasan rapat.', $result1['ringkasan']);
        $this->assertSame(['Agenda 1'], $result1['agenda']);

        $this->assertNotNull($result2);
        $this->assertSame('Ringkasan rapat.', $result2['ringkasan']);
    }

    public function test_parse_and_validate_json_rejects_malformed_or_missing_keys(): void
    {
        $summarizer = new LlmSummarizer;

        $missingKeys = json_encode([
            'ringkasan' => 'Hanya ringkasan',
        ]);

        $invalidJson = '{ tidak valid json ';

        $this->assertNull($summarizer->parseAndValidateJson($missingKeys));
        $this->assertNull($summarizer->parseAndValidateJson($invalidJson));
    }

    public function test_enforce_style_and_toggles_cleans_fields(): void
    {
        $summarizer = new LlmSummarizer;

        $raw = [
            'ringkasan' => 'Inti rapat.',
            'agenda' => ['Agenda A'],
            'pembahasan' => 'Uraian detail',
            'keputusan' => ['Sepakat'],
            'tindak_lanjut' => [
                ['description' => 'Kirim berkas', 'assignee_name' => 'Siti', 'due_date' => null],
            ],
        ];

        // Ringkas + without followup
        $cleaned1 = $summarizer->enforceStyleAndToggles($raw, 'ringkas', false);
        $this->assertSame([], $cleaned1['agenda']);
        $this->assertSame('', $cleaned1['pembahasan']);
        $this->assertSame([], $cleaned1['tindak_lanjut']);
        $this->assertSame(['Sepakat'], $cleaned1['keputusan']);

        // Lengkap + with followup
        $cleaned2 = $summarizer->enforceStyleAndToggles($raw, 'lengkap', true);
        $this->assertSame(['Agenda A'], $cleaned2['agenda']);
        $this->assertSame('Uraian detail', $cleaned2['pembahasan']);
        $this->assertCount(1, $cleaned2['tindak_lanjut']);
    }

    public function test_retry_mechanism_retries_on_invalid_output_and_throws_after_exhaustion(): void
    {
        // Create an anonymous subclass to mock callLlmApi
        $mockSummarizer = new class extends LlmSummarizer
        {
            public int $callCount = 0;

            protected function callLlmApi(string $prompt): string
            {
                $this->callCount++;

                // Always return invalid response
                return 'Maaf, saya tidak dapat membuat JSON.';
            }
        };

        config()->set('summarizer.drivers.llm.max_retries', 3);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gagal menghasilkan ringkasan notulen valid setelah 3 percobaan');

        try {
            $mockSummarizer->summarize('Percakapan rapat', ['style' => 'ringkas']);
        } finally {
            $this->assertSame(3, $mockSummarizer->callCount);
        }
    }

    public function test_successful_retry_on_second_attempt(): void
    {
        $validJson = json_encode([
            'ringkasan' => 'Ringkasan sukses pada percobaan kedua.',
            'agenda' => [],
            'pembahasan' => '',
            'keputusan' => ['Keputusan sah'],
            'tindak_lanjut' => [],
        ]);

        $mockSummarizer = new class($validJson) extends LlmSummarizer
        {
            public int $callCount = 0;

            public function __construct(private string $validJson) {}

            protected function callLlmApi(string $prompt): string
            {
                $this->callCount++;
                if ($this->callCount === 1) {
                    return 'Not json';
                }

                return $this->validJson;
            }
        };

        config()->set('summarizer.drivers.llm.max_retries', 3);

        $result = $mockSummarizer->summarize('Percakapan rapat', ['style' => 'ringkas', 'use_followup' => false]);

        $this->assertSame(2, $mockSummarizer->callCount);
        $this->assertSame('Ringkasan sukses pada percobaan kedua.', $result['ringkasan']);
        $this->assertSame(['Keputusan sah'], $result['keputusan']);
    }
}
