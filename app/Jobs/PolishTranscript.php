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
    public function buildPolishPrompt(string $rawText): string
    {
        return <<<PROMPT
Anda adalah editor transkrip rapat profesional berbahasa Indonesia.
Tugas Anda adalah merapikan transkrip alur ucapan percakapan rapat menjadi teks transkrip lengkap yang bersih dengan aturan ketat berikut:

1. HAPUS KATA FILLER & SUARA TAWA:
   - Hilangkan kata atau suara filler tanpa makna: "hmmmm", "hmm", "hm", "ehm", "eh", "ee", "eee", "eeh", "anu".
   - Hilangkan transkripsi suara tertawa: "hahahaha", "hahaha", "haha", "hehehe", "hehe", "hihi", "wkwk", "[tertawa]", "(tertawa)", "[laugh]".
   - Hilangkan repetisi tanpa makna seperti: "ya ya ya", "iya iya iya".
   - Hilangkan partikel filler ("gitu loh", "kayak gitu", "kan", "lah", "tuh", "deh", "nah", "sih") HANYA jika berdiri sendiri tanpa arti gramatikal dalam kalimat.

2. PERTAHANKAN BAHASA CAMPURAN (BAHASA DAERAH & BAHASA INGGRIS):
   - Peserta rapat sering menggunakan bahasa campuran seperti bahasa daerah (misalnya bahasa Jawa: monggo, sampun, matur nuwun, inggih, sae, nggih; bahasa Sunda: mangga, punten, dll) serta istilah atau ungkapan bahasa Inggris (misalnya: by the way, clear, on track, follow up, deadline, as soon as possible, dll).
   - JANGAN menghapus, mengubah, atau menerjemahkan paksa ungkapan bahasa daerah dan bahasa Inggris tersebut! Pertahankan persis seperti ungkapan asli yang diucapkan penutur.

3. PERTAHANKAN ALUR UCAPAN LENGKAP (BUKAN RINGKASAN):
   - Pertahankan seluruh alur ucapan dan isi percakapan penutur per kalimat/dialog. JANGAN meringkas atau memotong poin pembicaraan. Ini adalah transkrip versi lengkap.

4. PERBAIKI TATA TULIS:
   - Perbaiki tanda baca (titik, koma, tanda tanya) dan kapitalisasi awal kalimat agar rapi dan mudah dibaca.
   - Gabungkan kalimat terpotong yang jelas bermakna sama menjadi satu kalimat utuh.

5. INTEGRITAS FAKTA:
   - JANGAN mengubah fakta, nama orang, angka, persentase, tanggal, nama lembaga, atau keputusan apapun.
   - JANGAN menambah informasi yang tidak ada di transkrip asli.
   - Pertahankan jumlah baris/paragraf yang sama dengan aslinya sebisa mungkin.

Kembalikan HANYA teks transkrip lengkap yang sudah dibersihkan, tanpa penjelasan atau komentar apapun.

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
