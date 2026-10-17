<?php

namespace Tests\Feature\STT;

use App\Contracts\SpeechToTextProvider;
use App\DTOs\TranscriptionResult;
use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\TranscriptSegment;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscriptionPipelineEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_meeting_from_p4_recording_finish_produces_assembled_sequential_transcripts(): void
    {
        Storage::fake(config('recording.disk', 'local'));

        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'status' => 'recording',
            'started_at' => now()->subMinutes(5),
        ]);

        // Upload 3 segments via P4 endpoint
        $segmentTexts = [
            1 => ['duration' => 60.0, 'text' => 'Rapat koordinasi awal dibuka oleh pimpinan.'],
            2 => ['duration' => 60.0, 'text' => 'Pembahasan agenda kurikulum baru berjalan lancar.'],
            3 => ['duration' => 30.0, 'text' => 'Kesimpulan disepakati dan rapat resmi ditutup.'],
        ];

        foreach ($segmentTexts as $seq => $info) {
            $content = "fake-audio-bytes-for-seq-{$seq}";
            $file = UploadedFile::fake()->createWithContent("seg-{$seq}.webm", $content);
            $checksum = hash('sha256', $content);

            $this->actingAs($notulis)->postJson(route('meetings.record.segments', $meeting), [
                'file' => $file,
                'seq' => $seq,
                'checksum' => $checksum,
                'duration_sec' => $info['duration'],
            ])->assertStatus(201);
        }

        // Mock STT Provider returning realistic responses for each segment
        $mockProvider = $this->createMock(SpeechToTextProvider::class);
        $mockProvider->expects($this->exactly(3))
            ->method('transcribe')
            ->willReturnCallback(function (string $path, string $mime, ?string $language) use ($segmentTexts) {
                // Determine seq from filename in path (e.g. seg-1.webm)
                preg_match('/seg-(\d+)\./', $path, $matches);
                $seq = isset($matches[1]) ? (int) $matches[1] : 1;
                $data = $segmentTexts[$seq];

                return new TranscriptionResult(
                    text: $data['text'],
                    segments: [
                        ['start' => 0.0, 'end' => $data['duration'], 'text' => $data['text']],
                    ],
                    duration: $data['duration']
                );
            });

        $this->app->instance(SpeechToTextProvider::class, $mockProvider);

        // Call finish endpoint (P4 finish action)
        $finishResponse = $this->actingAs($notulis)->postJson(route('meetings.record.finish', $meeting), [
            'expected_segments' => 3,
        ]);

        $finishResponse->assertOk();
        $finishResponse->assertJsonPath('completed', true);

        // Verify meeting transitioned all the way to ready
        $meeting->refresh();
        $this->assertEquals('ready', $meeting->status);

        // Verify all 3 audio segments are 'ready'
        $this->assertEquals(3, AudioSegment::where('meeting_id', $meeting->id)->where('status', 'ready')->count());

        // Verify transcript segments are ordered and offset correctly
        $transcripts = TranscriptSegment::where('meeting_id', $meeting->id)
            ->orderBy('start_sec', 'asc')
            ->get();

        $this->assertCount(3, $transcripts);

        // Seg 1: 0.0s to 60.0s
        $this->assertEquals(0.0, $transcripts[0]->start_sec);
        $this->assertEquals(60.0, $transcripts[0]->end_sec);
        $this->assertEquals('Rapat koordinasi awal dibuka oleh pimpinan.', $transcripts[0]->text);

        // Seg 2: 60.0s to 120.0s (offset by 60s)
        $this->assertEquals(60.0, $transcripts[1]->start_sec);
        $this->assertEquals(120.0, $transcripts[1]->end_sec);
        $this->assertEquals('Pembahasan agenda kurikulum baru berjalan lancar.', $transcripts[1]->text);

        // Seg 3: 120.0s to 150.0s (offset by 120s)
        $this->assertEquals(120.0, $transcripts[2]->start_sec);
        $this->assertEquals(150.0, $transcripts[2]->end_sec);
        $this->assertEquals('Kesimpulan disepakati dan rapat resmi ditutup.', $transcripts[2]->text);

        // Verify 3 successful usage log entries
        $this->assertEquals(3, UsageLog::where('meeting_id', $meeting->id)->where('status', 'sukses')->count());

        // Verify audit trail
        $this->assertDatabaseHas('audit_logs', [
            'meeting_id' => $meeting->id,
            'action' => 'recording_finish',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'meeting_id' => $meeting->id,
            'action' => 'transcription_pipeline_start',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'meeting_id' => $meeting->id,
            'action' => 'transcript_assemble',
        ]);
    }
}
