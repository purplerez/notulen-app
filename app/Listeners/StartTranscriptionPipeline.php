<?php

namespace App\Listeners;

use App\Events\MeetingAudioUploaded;
use App\Jobs\TranscribeSegment;
use App\Support\Audit;

class StartTranscriptionPipeline
{
    /**
     * Handle the event.
     */
    public function handle(MeetingAudioUploaded $event): void
    {
        $meeting = $event->meeting;

        $meeting->update([
            'status' => 'transcribing',
        ]);

        Audit::record('transcription_pipeline_start', $meeting->id, [
            'expected_segments' => $meeting->expected_segments,
        ]);

        // Dispatch transcription jobs for each segment in sequence
        $segments = $meeting->segments()->orderBy('seq')->get();
        foreach ($segments as $segment) {
            TranscribeSegment::dispatch($segment);
        }
    }
}
