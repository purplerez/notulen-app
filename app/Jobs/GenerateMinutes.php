<?php

namespace App\Jobs;

use App\Contracts\Summarizer;
use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\Minute;
use App\Support\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMinutes implements ShouldQueue
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
    public function handle(Summarizer $summarizer): void
    {
        // Check if draft or final version 1 already exists
        $existingV1 = Minute::withoutGlobalScopes()
            ->where('meeting_id', $this->meeting->id)
            ->where('version', 1)
            ->first();

        if ($existingV1) {
            Log::info("GenerateMinutes: Minute version 1 already exists for meeting {$this->meeting->id}. Skipping duplicate generation.");

            return;
        }

        // 1. Gather transcript segments (preferring edited_text if available)
        $segments = $this->meeting->transcriptSegments()
            ->orderBy('start_sec')
            ->get();

        $textParts = [];
        foreach ($segments as $segment) {
            $effectiveText = trim($segment->getEffectiveText());
            if (! empty($effectiveText)) {
                $textParts[] = $effectiveText;
            }
        }

        $fullTranscript = implode("\n", $textParts);

        // 2. Gather manual markers
        $markers = $this->meeting->markers()
            ->orderBy('at_sec')
            ->get();

        // 3. Summarize via provider
        $options = [
            'style' => $this->meeting->style,
            'use_followup' => (bool) $this->meeting->use_followup,
            'markers' => $markers,
        ];

        $summary = $summarizer->summarize($fullTranscript, $options);

        // 4. Create Minute version 1 (draft)
        $minute = Minute::create([
            'organization_id' => $this->meeting->organization_id,
            'meeting_id' => $this->meeting->id,
            'version' => 1,
            'status' => 'draft',
            'content_json' => $summary,
            'created_by' => $this->meeting->created_by,
            'finalized_at' => null,
            'revision_reason' => null,
        ]);

        // 5. Create ActionItems if use_followup is active
        if ($this->meeting->use_followup && ! empty($summary['tindak_lanjut'])) {
            foreach ($summary['tindak_lanjut'] as $item) {
                if (! empty($item['description'])) {
                    ActionItem::create([
                        'organization_id' => $this->meeting->organization_id,
                        'meeting_id' => $this->meeting->id,
                        'minute_id' => $minute->id,
                        'description' => $item['description'],
                        'assignee_name' => $item['assignee_name'] ?? null,
                        'due_date' => $item['due_date'] ?? null,
                        'status' => 'pending',
                    ]);
                }
            }
        }

        Audit::record('minute_draft_created', $this->meeting->id, [
            'minute_id' => $minute->id,
            'version' => 1,
        ]);
    }
}
