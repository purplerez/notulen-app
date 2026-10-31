<?php

namespace App\Services\Minutes;

use App\Contracts\LlmProvider;
use App\Contracts\Summarizer;
use JsonException;
use RuntimeException;

class LlmSummarizer implements Summarizer
{
    /**
     * Create a new LlmSummarizer instance.
     */
    public function __construct(private readonly LlmProvider $llmProvider) {}

    /**
     * Summarize transcript using LLM architecture (chunking, validation, retries).
     */
    public function summarize(string $transcript, array $options = []): array
    {
        $transcript = trim($transcript);
        $style = $options['style'] ?? 'ringkas';
        $useFollowup = (bool) ($options['use_followup'] ?? true);
        $markers = $options['markers'] ?? [];

        if (empty($transcript)) {
            return [
                'ringkasan' => '',
                'agenda' => [],
                'pembahasan' => '',
                'keputusan' => [],
                'tindak_lanjut' => [],
            ];
        }

        $maxChunkWords = (int) config('summarizer.drivers.llm.max_chunk_words', 2500);
        $maxRetries = (int) config('summarizer.drivers.llm.max_retries', 3);

        $chunks = $this->chunkTranscript($transcript, $maxChunkWords);

        // If multi-chunk, summarize each chunk then combine
        if (count($chunks) > 1) {
            $chunkSummaries = [];
            foreach ($chunks as $idx => $chunk) {
                $chunkOptions = array_merge($options, ['is_chunk' => true, 'chunk_index' => $idx + 1]);
                $chunkSummaries[] = $this->executeSummarizationWithRetry($chunk, $chunkOptions, $maxRetries);
            }

            $combinedTranscript = $this->combineChunkSummaries($chunkSummaries);

            return $this->executeSummarizationWithRetry($combinedTranscript, $options, $maxRetries);
        }

        return $this->executeSummarizationWithRetry($transcript, $options, $maxRetries);
    }

    /**
     * Execute LLM summarization with schema validation and retry loop.
     *
     * @param  array<string, mixed>  $options
     * @return array{
     *     ringkasan: string,
     *     agenda: array<int, string>,
     *     pembahasan: array<int, string>|string,
     *     keputusan: array<int, string>,
     *     tindak_lanjut: array<int, array{description: string, assignee_name: ?string, due_date: ?string}>
     * }
     */
    protected function executeSummarizationWithRetry(string $text, array $options, int $maxRetries): array
    {
        $style = $options['style'] ?? 'ringkas';
        $useFollowup = (bool) ($options['use_followup'] ?? true);
        $prompt = $this->buildPrompt($text, $options);

        $attempt = 0;
        $lastError = null;

        while ($attempt < $maxRetries) {
            $attempt++;
            try {
                $rawResponse = $this->callLlmApi($prompt);
                $parsed = $this->parseAndValidateJson($rawResponse);

                if ($parsed !== null) {
                    return $this->enforceStyleAndToggles($parsed, $style, $useFollowup);
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        throw new RuntimeException("Gagal menghasilkan ringkasan notulen valid setelah {$maxRetries} percobaan. Terakhir: {$lastError}");
    }

    /**
     * Split a transcript into blocks of words.
     *
     * @return list<string>
     */
    public function chunkTranscript(string $transcript, int $maxWords = 2500): array
    {
        $words = preg_split('/\s+/u', trim($transcript), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) <= $maxWords) {
            return [$transcript];
        }

        $chunks = [];
        $totalWords = count($words);

        for ($i = 0; $i < $totalWords; $i += $maxWords) {
            $slice = array_slice($words, $i, $maxWords);
            $chunks[] = implode(' ', $slice);
        }

        return $chunks;
    }

    /**
     * Combine intermediate chunk summaries into unified text for final reduction.
     *
     * @param  list<array<string, mixed>>  $summaries
     */
    protected function combineChunkSummaries(array $summaries): string
    {
        $parts = [];
        foreach ($summaries as $i => $s) {
            $num = $i + 1;
            $ringkasan = $s['ringkasan'] ?? '';
            $keputusan = implode('; ', $s['keputusan'] ?? []);
            $parts[] = "Bagian {$num}:\n{$ringkasan}\nKeputusan bagian ini: {$keputusan}";
        }

        return implode("\n\n---\n\n", $parts);
    }

    /**
     * Parse raw string from LLM, extract JSON, and validate against expected schema.
     *
     * @return array<string, mixed>|null
     */
    public function parseAndValidateJson(string $raw): ?array
    {
        $jsonStr = $raw;

        // Strip markdown code block if present
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $raw, $matches)) {
            $jsonStr = $matches[1];
        } elseif (preg_match('/(\{.*\})/s', $raw, $matches)) {
            $jsonStr = $matches[1];
        }

        try {
            $data = json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($data)) {
            return null;
        }

        // Schema validation: keys must be present or defaults provided
        if (! array_key_exists('ringkasan', $data) || ! is_string($data['ringkasan'])) {
            return null;
        }

        if (! array_key_exists('agenda', $data) || ! is_array($data['agenda'])) {
            return null;
        }

        if (! array_key_exists('keputusan', $data) || ! is_array($data['keputusan'])) {
            return null;
        }

        if (! array_key_exists('tindak_lanjut', $data) || ! is_array($data['tindak_lanjut'])) {
            return null;
        }

        return [
            'ringkasan' => (string) $data['ringkasan'],
            'agenda' => array_values(array_map('strval', $data['agenda'])),
            'pembahasan' => is_array($data['pembahasan'] ?? null)
                ? implode("\n\n", array_map('strval', $data['pembahasan']))
                : (string) ($data['pembahasan'] ?? ''),
            'keputusan' => array_values(array_map('strval', $data['keputusan'])),
            'tindak_lanjut' => array_values(array_filter($data['tindak_lanjut'], 'is_array')),
        ];
    }

    /**
     * Enforce business rules for styles and feature toggles on the parsed minutes.
     *
     * @param  array<string, mixed>  $data
     * @return array{
     *     ringkasan: string,
     *     agenda: array<int, string>,
     *     pembahasan: array<int, string>|string,
     *     keputusan: array<int, string>,
     *     tindak_lanjut: array<int, array{description: string, assignee_name: ?string, due_date: ?string}>
     * }
     */
    public function enforceStyleAndToggles(array $data, string $style, bool $useFollowup): array
    {
        // Rule: If style is 'ringkas', agenda and pembahasan are omitted or kept empty
        if ($style === 'ringkas') {
            $data['agenda'] = [];
            $data['pembahasan'] = '';
        }

        // Rule: tindak_lanjut only requested / generated if use_followup is true
        if (! $useFollowup) {
            $data['tindak_lanjut'] = [];
        } else {
            // Normalize action item structure
            $normalizedItems = [];
            foreach ($data['tindak_lanjut'] as $item) {
                if (is_array($item) && ! empty($item['description'])) {
                    $normalizedItems[] = [
                        'description' => (string) $item['description'],
                        'assignee_name' => ! empty($item['assignee_name']) ? (string) $item['assignee_name'] : null,
                        'due_date' => ! empty($item['due_date']) ? (string) $item['due_date'] : null,
                    ];
                }
            }
            $data['tindak_lanjut'] = $normalizedItems;
        }

        /** @var array{ringkasan: string, agenda: array<int, string>, pembahasan: array<int, string>|string, keputusan: array<int, string>, tindak_lanjut: array<int, array{description: string, assignee_name: ?string, due_date: ?string}>} */
        return $data;
    }

    /**
     * Compile prompt template for LLM summarization.
     *
     * @param  array<string, mixed>  $options
     */
    protected function buildPrompt(string $text, array $options): string
    {
        $style = $options['style'] ?? 'ringkas';
        $useFollowup = (bool) ($options['use_followup'] ?? true);
        $markers = $options['markers'] ?? [];

        $markerDescriptions = [];
        foreach ($markers as $m) {
            $type = is_array($m) ? ($m['type'] ?? '') : ($m->type ?? '');
            $note = is_array($m) ? ($m['note'] ?? '') : ($m->note ?? '');
            if ($note) {
                $markerDescriptions[] = "- [{$type}] {$note}";
            }
        }

        $markerText = ! empty($markerDescriptions)
            ? "Penanda manual rapat sebagai petunjuk penting:\n".implode("\n", $markerDescriptions)."\n"
            : '';

        return <<<PROMPT
Anda adalah asisten notulensi rapat resmi. Buat notulen rapat dari transkrip di bawah ini.
Gaya notulen: {$style}.
{$markerText}
Aturan:
- Gunakan hanya isi transkrip, jangan mengarang informasi baru.
- Bahasa Indonesia formal dan baku.
- Kembalikan HANYA format JSON valid tanpa teks pengantar dengan skema:
{
  "ringkasan": "teks ringkasan utama",
  "agenda": ["poin agenda 1", "poin agenda 2"],
  "pembahasan": "penjelasan pembahasan atau poin rinci",
  "keputusan": ["butir keputusan 1", "butir keputusan 2"],
  "tindak_lanjut": [
    {
      "description": "uraian tugas",
      "assignee_name": "penanggung jawab atau null",
      "due_date": "YYYY-MM-DD atau null"
    }
  ]
}

Transkrip:
{$text}
PROMPT;
    }

    /**
     * Dispatch prompt to Groq LLM via the injected LlmProvider.
     */
    protected function callLlmApi(string $prompt): string
    {
        return $this->llmProvider->complete($prompt);
    }
}
