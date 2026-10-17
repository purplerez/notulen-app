<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Support\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MergeAudio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Meeting $meeting
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $segments = $this->meeting->segments()->orderBy('seq')->get();

        if ($segments->isEmpty()) {
            Log::warning("MergeAudio: No audio segments recorded for meeting {$this->meeting->id}.");

            return;
        }

        $disk = config('recording.disk', 'local');
        $storage = Storage::disk($disk);

        // Collect existing physical segment files
        $existingSegmentPaths = [];
        foreach ($segments as $segment) {
            if ($storage->exists($segment->path)) {
                $existingSegmentPaths[] = $storage->path($segment->path);
            }
        }

        if (empty($existingSegmentPaths)) {
            Log::warning("MergeAudio: No physical audio segment files found on disk for meeting {$this->meeting->id}.");

            return;
        }

        $meetingDir = "orgs/{$this->meeting->organization_id}/meetings/{$this->meeting->id}";
        $storage->makeDirectory($meetingDir);

        $manifestRelativePath = "{$meetingDir}/concat_manifest.txt";
        $manifestFullPath = $storage->path($manifestRelativePath);

        // Prepare ffmpeg concat demuxer manifest
        $manifestLines = [];
        foreach ($existingSegmentPaths as $path) {
            $escapedPath = str_replace("'", "'\\''", $path);
            $manifestLines[] = "file '{$escapedPath}'";
        }
        file_put_contents($manifestFullPath, implode("\n", $manifestLines));

        $outputRelativePath = "{$meetingDir}/merged.m4a";
        $outputFullPath = $storage->path($outputRelativePath);

        $ffmpegBinary = (string) config('recording.ffmpeg_path', 'ffmpeg');
        $bitrate = (string) config('recording.merge_bitrate', '128k');

        // Check if ffmpeg is available when not running in faked testing mode
        if (! Process::isRecording()) {
            $whichCheck = Process::run(['which', $ffmpegBinary]);
            if ($whichCheck->failed() && ! file_exists($ffmpegBinary)) {
                Log::warning("MergeAudio: ffmpeg binary '{$ffmpegBinary}' is not installed on this system. Audio merge skipped.");

                return;
            }
        }

        // Compile ffmpeg command as an array of separate arguments (no shell string concat)
        $command = [
            $ffmpegBinary,
            '-y',
            '-f', 'concat',
            '-safe', '0',
            '-i', $manifestFullPath,
            '-c:a', 'aac',
            '-b:a', $bitrate,
            '-movflags', '+faststart',
            $outputFullPath,
        ];

        try {
            $result = Process::run($command);

            if ($result->failed()) {
                $errorMsg = $result->errorOutput() ?: $result->output();
                Log::error("MergeAudio failed for meeting {$this->meeting->id}: {$errorMsg}");
                throw new RuntimeException("Gagal menggabungkan audio rapat: {$errorMsg}");
            }

            // Update meeting with merged audio path
            $this->meeting->update([
                'merged_audio_path' => $outputRelativePath,
            ]);

            Audit::record('audio_merge', $this->meeting->id, [
                'merged_path' => $outputRelativePath,
                'segments_count' => count($existingSegmentPaths),
            ]);
        } finally {
            // Clean up temporary concat manifest file
            if (file_exists($manifestFullPath)) {
                @unlink($manifestFullPath);
            }
        }
    }
}
