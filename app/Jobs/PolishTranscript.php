<?php

namespace App\Jobs;

use App\Contracts\LlmProvider;
use App\Exceptions\LlmException;
use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Support\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class PolishTranscript implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public AudioSegment $segment
    ) {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(LlmProvider $llm): void
    {
        $meeting = $this->segment->meeting;

        // Load all transcript segments for this audio segment, ordered by time
        $transcriptSegments = TranscriptSegment::where('audio_segment_id', $this->segment->id)
            ->orderBy('start_sec')
            ->get();

        if ($transcriptSegments->isEmpty()) {
            // Nothing to polish, move on
            $this->segment->update(['status' => 'polished']);
            $this->checkAndDispatchAssembly($meeting);

            return;
        }

        $this->segment->update(['status' => 'polishing']);

        // Combine all segment texts into one block for polishing
        $rawText = $transcriptSegments->map(fn ($s) => trim($s->text))->filter()->implode("\n");

        $prompt = $this->buildPolishPrompt($rawText);

        try {
            $polishedText = $llm->complete($prompt, ['temperature' => 0.2, 'max_tokens' => 4096]);
        } catch (LlmException $e) {
            Log::warning("PolishTranscript: LLM gagal untuk segmen {$this->segment->id}: {$e->getMessage()}");

            if ($this->attempts() < $this->tries) {
                $this->release(30);

                return;
            }

            // Exhausted retries: mark as polish_failed and continue pipeline with raw text
            $this->segment->update(['status' => 'polish_failed']);

            Audit::record('transcript_polish_failed', $meeting->id, [
                'segment_id' => $this->segment->id,
                'error' => $e->getMessage(),
            ]);

            $this->checkAndDispatchAssembly($meeting);

            return;
        }

        // Distribute polished text back to individual transcript segments
        $this->distributePolishedText($transcriptSegments->all(), $rawText, $polishedText);

        $this->segment->update(['status' => 'polished']);

        Audit::record('transcript_polished', $meeting->id, [
            'segment_id' => $this->segment->id,
            'transcript_segments' => $transcriptSegments->count(),
        ]);

        $this->checkAndDispatchAssembly($meeting);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        $meeting = $this->segment->meeting;

        $this->segment->update(['status' => 'polish_failed']);

        Audit::record('transcript_polish_failed', $meeting->id, [
            'segment_id' => $this->segment->id,
            'error' => $exception->getMessage(),
        ]);

        // Do not block the pipeline — check if assembly can proceed
        $this->checkAndDispatchAssembly($meeting);
    }

    /**
     * Build the LLM prompt for transcript cleaning.
     */
    protected function buildPolishPrompt(string $rawText): string
    {
        return <<<PROMPT
Anda adalah editor transkrip rapat profesional berbahasa Indonesia.
Bersihkan transkrip rapat berikut dengan aturan ini:

1. Hapus kata atau suara filler yang tidak bermakna: "eh", "ehm", "hmm", "hm", "hehe",
   "haha", "hihi", "anu", "ee", "eee", "eeh", "ya ya ya", "ya ya", "iya iya",
   "gitu", "gitu loh", "gitu kan", "kayak gitu", "kan", "lah", "tuh", "deh",
   "nah", "sih" — HANYA jika berdiri sendiri dan tidak punya makna dalam kalimat.
2. Perbaiki tanda baca dan kapitalisasi awal kalimat.
3. Gabungkan kalimat terpotong yang jelas bermakna sama menjadi satu kalimat utuh.
4. JANGAN mengubah fakta, nama orang, nama tempat, angka, tanggal, atau keputusan apapun.
5. JANGAN menambah informasi yang tidak ada di transkrip asli.
6. Pertahankan jumlah baris (paragraf) yang sama dengan aslinya sebisa mungkin.
7. Kembalikan HANYA teks yang sudah dibersihkan, tanpa penjelasan atau komentar apapun.

Transkrip mentah:
{$rawText}
PROMPT;
    }

    /**
     * Distribute the polished text back to individual TranscriptSegment records.
     *
     * Strategy: split polished text by newlines. If the count matches the number of
     * segments exactly, assign one-to-one. Otherwise, distribute proportionally by
     * character length of the original text.
     *
     * @param  TranscriptSegment[]  $segments
     */
    protected function distributePolishedText(array $segments, string $rawText, string $polishedText): void
    {
        $polishedLines = array_values(
            array_filter(
                explode("\n", $polishedText),
                fn ($line) => trim($line) !== ''
            )
        );

        $count = count($segments);

        // Exact line-count match: one polished line per segment
        if (count($polishedLines) === $count) {
            foreach ($segments as $i => $segment) {
                $segment->update(['edited_text' => trim($polishedLines[$i])]);
            }

            return;
        }

        // Proportional distribution by original text character lengths
        $totalRawChars = max(1, array_sum(array_map(fn ($s) => mb_strlen($s->text), $segments)));
        $polishedFull = trim($polishedText);
        $totalPolished = mb_strlen($polishedFull);

        $offset = 0;
        foreach ($segments as $i => $segment) {
            $isLast = ($i === $count - 1);

            if ($isLast) {
                $slice = mb_substr($polishedFull, $offset);
            } else {
                $ratio = mb_strlen($segment->text) / $totalRawChars;
                $length = (int) round($ratio * $totalPolished);
                $slice = mb_substr($polishedFull, $offset, $length);
                $offset += $length;
            }

            $segment->update(['edited_text' => trim($slice)]);
        }
    }

    /**
     * Check whether all audio segments for the meeting are done (polished or failed or ready),
     * and dispatch AssembleTranscript if so.
     */
    protected function checkAndDispatchAssembly(Meeting $meeting): void
    {
        $doneCount = AudioSegment::where('meeting_id', $meeting->id)
            ->whereIn('status', ['polished', 'polish_failed', 'ready'])
            ->count();

        if ($meeting->expected_segments > 0 && $doneCount >= $meeting->expected_segments) {
            AssembleTranscript::dispatch($meeting);
        }
    }
}
