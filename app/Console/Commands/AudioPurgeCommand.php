<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Models\Scopes\OrganizationScope;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AudioPurgeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:purge {--meeting= : ID rapat spesifik yang ingin dibersihkan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Hapus berkas audio mentah, berkas gabungan, dan salinan sementara untuk rapat yang masa retensinya sudah berakhir.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $query = Meeting::withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('audio_expires_at')
            ->where('audio_expires_at', '<=', $now);

        if ($specificId = $this->option('meeting')) {
            $query->where('id', $specificId);
        } else {
            $query->whereNull('audio_deleted_at');
        }

        $meetings = $query->get();

        if ($meetings->isEmpty()) {
            $this->info('Tidak ada berkas audio yang perlu dibersihkan.');

            return self::SUCCESS;
        }

        $disk = config('recording.disk', 'local');
        $storage = Storage::disk($disk);
        $totalPurged = 0;

        foreach ($meetings as $meeting) {
            // Idempotent safeguard: if already purged and merged path is null, skip
            if ($meeting->audio_deleted_at !== null && empty($meeting->merged_audio_path)) {
                $this->line("Rapat #{$meeting->id} sudah dibersihkan sebelumnya.");

                continue;
            }

            $deletedFilesCount = 0;

            // 1. Delete physical audio segment files
            $segments = $meeting->segments()->get();
            foreach ($segments as $segment) {
                if ($storage->exists($segment->path)) {
                    $storage->delete($segment->path);
                    $deletedFilesCount++;
                }
            }

            // 2. Delete merged audio file
            if (! empty($meeting->merged_audio_path) && $storage->exists($meeting->merged_audio_path)) {
                $storage->delete($meeting->merged_audio_path);
                $deletedFilesCount++;
            }

            // 3. Clean up temporary manifest or files in the meeting directory
            $meetingDir = "orgs/{$meeting->organization_id}/meetings/{$meeting->id}";
            $manifestPath = "{$meetingDir}/concat_manifest.txt";
            if ($storage->exists($manifestPath)) {
                $storage->delete($manifestPath);
            }

            // Note: Raw transcripts in transcript_segments are NOT touched.

            // 4. Update meeting retention timestamps
            $meeting->update([
                'audio_deleted_at' => now(),
                'merged_audio_path' => null,
            ]);

            // 5. Append-only audit logging
            Audit::record('audio_purge', $meeting->id, [
                'purged_files_count' => $deletedFilesCount,
                'purged_segments_count' => $segments->count(),
                'audio_deleted_at' => $meeting->audio_deleted_at?->toIso8601String(),
            ]);

            $this->info("Audio rapat #{$meeting->id} ('{$meeting->title}') berhasil dibersihkan ({$deletedFilesCount} berkas).");
            $totalPurged++;
        }

        $this->info("Selesai. Total rapat dibersihkan: {$totalPurged}");

        return self::SUCCESS;
    }
}
