<?php

namespace Tests\Feature\STT;

use App\Contracts\SpeechToTextProvider;
use App\DTOs\TranscriptionResult;
use App\Exceptions\RateLimitException;
use App\Exceptions\TranscriptionException;
use App\Jobs\AssembleTranscript;
use App\Jobs\TranscribeSegment;
use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\TranscriptSegment;
use App\Models\UsageLog;
use App\Models\User;
use App\Notifications\SttQuotaExceededNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscribeSegmentTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $notulis;

    private Meeting $meeting;

    private AudioSegment $segment1;

    private AudioSegment $segment2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('recording.disk', 'local'));

        $this->org = Organization::factory()->create();
        $this->notulis = User::factory()->notulis()->create(['organization_id' => $this->org->id]);
        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->notulis->id,
            'status' => 'uploaded',
            'expected_segments' => 2,
        ]);

        $disk = config('recording.disk', 'local');
        $path1 = "orgs/{$this->org->id}/meetings/{$this->meeting->id}/seg-1.webm";
        $path2 = "orgs/{$this->org->id}/meetings/{$this->meeting->id}/seg-2.webm";
        Storage::disk($disk)->put($path1, 'fake-audio-seg-1');
        Storage::disk($disk)->put($path2, 'fake-audio-seg-2');

        $this->segment1 = AudioSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'seq' => 1,
            'path' => $path1,
            'mime' => 'audio/webm',
            'size_bytes' => 1024,
            'duration_sec' => 60.0,
            'checksum' => hash('sha256', 'fake-audio-seg-1'),
            'status' => 'uploaded',
        ]);

        $this->segment2 = AudioSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'seq' => 2,
            'path' => $path2,
            'mime' => 'audio/webm',
            'size_bytes' => 1024,
            'duration_sec' => 45.0,
            'checksum' => hash('sha256', 'fake-audio-seg-2'),
            'status' => 'uploaded',
        ]);
    }

    public function test_transcribe_segment_saves_transcript_segments_with_correct_offsets(): void
    {
        $mockProvider = $this->createMock(SpeechToTextProvider::class);

        // Segment 2 (starts after segment 1 duration of 60.0s)
        $mockProvider->expects($this->once())
            ->method('transcribe')
            ->willReturn(new TranscriptionResult(
                text: 'Pembahasan kedua dimulai.',
                segments: [
                    ['start' => 0.0, 'end' => 4.5, 'text' => ' Pembahasan kedua'],
                    ['start' => 4.5, 'end' => 10.0, 'text' => ' dimulai.'],
                ],
                duration: 45.0
            ));

        $this->app->instance(SpeechToTextProvider::class, $mockProvider);

        $job = new TranscribeSegment($this->segment2);
        $job->handle($mockProvider);

        $this->segment2->refresh();
        $this->assertEquals('ready', $this->segment2->status);

        // Verify correct offset (+60.0s from segment 1)
        $transcripts = TranscriptSegment::where('audio_segment_id', $this->segment2->id)
            ->orderBy('start_sec')
            ->get();

        $this->assertCount(2, $transcripts);
        $this->assertEquals(60.0, $transcripts[0]->start_sec);
        $this->assertEquals(64.5, $transcripts[0]->end_sec);
        $this->assertEquals('Pembahasan kedua', $transcripts[0]->text);

        $this->assertEquals(64.5, $transcripts[1]->start_sec);
        $this->assertEquals(70.0, $transcripts[1]->end_sec);
        $this->assertEquals('dimulai.', $transcripts[1]->text);

        // Verify usage log
        $this->assertDatabaseHas('usage_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'kind' => 'stt',
            'status' => 'sukses',
            'http_status' => 200,
            'audio_sec' => 45.0,
        ]);
    }

    public function test_transcribe_segment_is_idempotent_and_does_not_duplicate_rows(): void
    {
        $mockProvider = $this->createMock(SpeechToTextProvider::class);
        $mockProvider->method('transcribe')
            ->willReturn(new TranscriptionResult(
                text: 'Idempotent text.',
                segments: [
                    ['start' => 0.0, 'end' => 5.0, 'text' => ' Idempotent text.'],
                ],
                duration: 60.0
            ));

        $this->app->instance(SpeechToTextProvider::class, $mockProvider);

        // Execute twice
        (new TranscribeSegment($this->segment1))->handle($mockProvider);
        (new TranscribeSegment($this->segment1))->handle($mockProvider);

        // Exactly one row should exist for this segment
        $this->assertEquals(1, TranscriptSegment::where('audio_segment_id', $this->segment1->id)->count());
    }

    public function test_transcribe_segment_respects_ash_limit_and_holds_job(): void
    {
        Config::set('stt.ash_limit', 7000);

        // Simulate 6980 seconds used in past hour
        UsageLog::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'kind' => 'stt',
            'provider' => 'groq',
            'model' => 'whisper-large-v3-turbo',
            'audio_sec' => 6980,
            'status' => 'sukses',
            'created_at' => now()->subMinutes(15),
        ]);

        $mockProvider = $this->createMock(SpeechToTextProvider::class);
        $mockProvider->expects($this->never())->method('transcribe');

        $this->app->instance(SpeechToTextProvider::class, $mockProvider);

        // Segment 1 is 60s, which pushes total to 7040 > 7000
        $job = $this->getMockBuilder(TranscribeSegment::class)
            ->setConstructorArgs([$this->segment1])
            ->onlyMethods(['release'])
            ->getMock();

        $job->expects($this->once())
            ->method('release')
            ->with($this->greaterThanOrEqual(15));

        $job->handle($mockProvider);

        // Must record 'ditahan' in usage_logs
        $this->assertDatabaseHas('usage_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'status' => 'ditahan',
            'audio_sec' => 60.0,
        ]);
    }

    public function test_transcribe_segment_handles_daily_quota_and_sets_waiting_quota_with_notification(): void
    {
        Notification::fake();

        $mockProvider = $this->createMock(SpeechToTextProvider::class);
        $mockProvider->method('transcribe')
            ->willThrowException(new RateLimitException(
                'Daily token quota limit exceeded for whisper model.',
                14400, // 4 hours
                true // isDailyQuota
            ));

        $this->app->instance(SpeechToTextProvider::class, $mockProvider);

        $job = $this->getMockBuilder(TranscribeSegment::class)
            ->setConstructorArgs([$this->segment1])
            ->onlyMethods(['release'])
            ->getMock();

        $job->expects($this->once())
            ->method('release')
            ->with(14400);

        $job->handle($mockProvider);

        $this->meeting->refresh();
        $this->assertEquals('waiting_quota', $this->meeting->status);

        // Segment should NOT be marked failed
        $this->segment1->refresh();
        $this->assertNotEquals('failed', $this->segment1->status);

        // In-app notification sent to notulis
        Notification::assertSentTo($this->notulis, SttQuotaExceededNotification::class);

        // Usage log recorded
        $this->assertDatabaseHas('usage_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'status' => 'gagal',
            'http_status' => 429,
        ]);
    }

    public function test_transcribe_segment_retries_on_5xx_and_marks_failed_after_max_attempts(): void
    {
        $mockProvider = $this->createMock(SpeechToTextProvider::class);
        $mockProvider->method('transcribe')
            ->willThrowException(new TranscriptionException('Groq 500 Server Error', 500));

        $this->app->instance(SpeechToTextProvider::class, $mockProvider);

        $job = $this->getMockBuilder(TranscribeSegment::class)
            ->setConstructorArgs([$this->segment1])
            ->onlyMethods(['attempts'])
            ->getMock();

        // Simulate 3rd and final attempt
        $job->method('attempts')->willReturn(3);

        try {
            $job->handle($mockProvider);
            $this->fail('Expected TranscriptionException was not thrown');
        } catch (TranscriptionException $e) {
            $this->assertEquals(500, $e->getCode());
        }

        $this->segment1->refresh();
        $this->assertEquals('failed', $this->segment1->status);

        $this->meeting->refresh();
        $this->assertEquals('failed', $this->meeting->status);

        // Audio file must still exist in private storage!
        $disk = config('recording.disk', 'local');
        Storage::disk($disk)->assertExists($this->segment1->path);
    }

    public function test_transcribe_segment_triggers_assemble_transcript_when_all_segments_ready(): void
    {
        Queue::fake([AssembleTranscript::class]);

        // Mark segment 1 as already ready
        $this->segment1->update(['status' => 'ready']);

        $mockProvider = $this->createMock(SpeechToTextProvider::class);
        $mockProvider->method('transcribe')
            ->willReturn(new TranscriptionResult(
                text: 'Segmen terakhir selesai.',
                segments: [['start' => 0.0, 'end' => 10.0, 'text' => 'Segmen terakhir selesai.']],
                duration: 45.0
            ));

        $this->app->instance(SpeechToTextProvider::class, $mockProvider);

        // Transcribe segment 2 (the last segment of 2 expected)
        (new TranscribeSegment($this->segment2))->handle($mockProvider);

        $this->segment2->refresh();
        $this->assertEquals('ready', $this->segment2->status);

        // AssembleTranscript job must be dispatched
        Queue::assertPushed(AssembleTranscript::class, function ($job) {
            return $job->meeting->id === $this->meeting->id;
        });
    }

    public function test_assemble_transcript_sets_meeting_status_to_ready(): void
    {
        $this->segment1->update(['status' => 'ready']);
        $this->segment2->update(['status' => 'ready']);

        $job = new AssembleTranscript($this->meeting);
        $job->handle();

        $this->meeting->refresh();
        $this->assertEquals('ready', $this->meeting->status);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'transcript_assemble',
        ]);
    }
}
