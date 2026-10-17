<?php

namespace App\Jobs;

use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Support\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AssembleTranscript implements ShouldQueue
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
        // Check that all segments are ready
        $readyCount = AudioSegment::where('meeting_id', $this->meeting->id)
            ->where('status', 'ready')
            ->count();

        if ($this->meeting->expected_segments > 0 && $readyCount < $this->meeting->expected_segments) {
            // Not all segments ready yet, reschedule or wait
            return;
        }

        // Update meeting status to ready (ready for minutes drafting / next stage)
        $this->meeting->update([
            'status' => 'ready',
        ]);

        Audit::record('transcript_assemble', $this->meeting->id, [
            'expected_segments' => $this->meeting->expected_segments,
            'transcript_segments_count' => $this->meeting->transcriptSegments()->count(),
        ]);
    }
}
