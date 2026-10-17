<?php

namespace Tests\Feature\Recording;

use App\Events\MeetingAudioUploaded;
use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SegmentUploadTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $notulis;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('recording.disk', 'local'));
        Storage::fake('public');

        $this->org = Organization::factory()->create(['quota_minutes' => 600]);
        $this->notulis = User::factory()->notulis()->create(['organization_id' => $this->org->id]);
        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->notulis->id,
            'status' => 'recording',
            'started_at' => now(),
        ]);
    }

    public function test_upload_segment_successfully_and_stores_in_private_disk(): void
    {
        $fileContent = 'fake-audio-binary-data-for-segment-1';
        $file = UploadedFile::fake()->createWithContent('seg-1.webm', $fileContent);
        $checksum = hash('sha256', $fileContent);

        $response = $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
            'file' => $file,
            'seq' => 1,
            'checksum' => $checksum,
            'duration_sec' => 59.8,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message', 'Segmen audio berhasil disimpan.');

        $this->assertDatabaseHas('audio_segments', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'seq' => 1,
            'checksum' => $checksum,
            'duration_sec' => 59.8,
            'status' => 'uploaded',
        ]);

        $disk = config('recording.disk', 'local');
        $segment = AudioSegment::where('meeting_id', $this->meeting->id)->where('seq', 1)->firstOrFail();

        // Must exist in private disk
        Storage::disk($disk)->assertExists($segment->path);

        // Must strictly NOT exist in public disk
        Storage::disk('public')->assertMissing($segment->path);
    }

    public function test_upload_segment_is_idempotent_for_identical_seq_and_checksum(): void
    {
        $fileContent = 'fake-audio-binary-data-for-idempotency';
        $checksum = hash('sha256', $fileContent);

        // Initial upload
        $file1 = UploadedFile::fake()->createWithContent('seg-1.webm', $fileContent);
        $res1 = $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
            'file' => $file1,
            'seq' => 1,
            'checksum' => $checksum,
            'duration_sec' => 60.0,
        ]);
        $res1->assertStatus(201);

        // Duplicate upload with same seq and checksum
        $file2 = UploadedFile::fake()->createWithContent('seg-1.webm', $fileContent);
        $res2 = $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
            'file' => $file2,
            'seq' => 1,
            'checksum' => $checksum,
            'duration_sec' => 60.0,
        ]);

        $res2->assertStatus(200);
        $res2->assertJsonPath('idempotent', true);
        $res2->assertJsonPath('message', 'Segmen audio sudah tersimpan.');

        // Database row must not be duplicated
        $this->assertEquals(1, AudioSegment::where('meeting_id', $this->meeting->id)->where('seq', 1)->count());
    }

    public function test_upload_segment_rejects_corrupted_checksum(): void
    {
        $fileContent = 'real-audio-data';
        $wrongChecksum = hash('sha256', 'tampered-data-with-different-hash');
        $file = UploadedFile::fake()->createWithContent('seg-1.webm', $fileContent);

        $response = $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
            'file' => $file,
            'seq' => 1,
            'checksum' => $wrongChecksum,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Verifikasi checksum gagal. Berkas audio korup atau tidak sesuai.');

        $this->assertDatabaseMissing('audio_segments', [
            'meeting_id' => $this->meeting->id,
            'seq' => 1,
        ]);
    }

    public function test_upload_segment_rejects_conflicting_checksum_for_same_seq(): void
    {
        $contentA = 'first-version-of-seq-1';
        $fileA = UploadedFile::fake()->createWithContent('seg-1.webm', $contentA);
        $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
            'file' => $fileA,
            'seq' => 1,
            'checksum' => hash('sha256', $contentA),
        ])->assertStatus(201);

        // Attempt upload same seq 1 with different content
        $contentB = 'conflicting-version-of-seq-1';
        $fileB = UploadedFile::fake()->createWithContent('seg-1.webm', $contentB);
        $response = $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
            'file' => $fileB,
            'seq' => 1,
            'checksum' => hash('sha256', $contentB),
        ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Segmen dengan seq 1 sudah ada dengan checksum berbeda.');
    }

    public function test_finish_recording_succeeds_when_all_segments_present(): void
    {
        Event::fake([MeetingAudioUploaded::class]);

        // Upload segments 1, 2, 3
        for ($seq = 1; $seq <= 3; $seq++) {
            $content = "segment-content-{$seq}";
            $file = UploadedFile::fake()->createWithContent("seg-{$seq}.webm", $content);
            $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
                'file' => $file,
                'seq' => $seq,
                'checksum' => hash('sha256', $content),
                'duration_sec' => 60.0,
            ])->assertStatus(201);
        }

        $response = $this->actingAs($this->notulis)->postJson(route('meetings.record.finish', $this->meeting), [
            'expected_segments' => 3,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('completed', true);

        $this->meeting->refresh();
        $this->assertEquals('uploaded', $this->meeting->status);
        $this->assertEquals(3, $this->meeting->expected_segments);
        $this->assertNotNull($this->meeting->ended_at);
        $this->assertNotNull($this->meeting->audio_saved_at);
        $this->assertNotNull($this->meeting->audio_expires_at);

        // Verify audio_expires_at matches config retention (1 month)
        $expectedRetentionMonths = (int) config('recording.retention_months', 1);
        $this->assertEquals(
            $this->meeting->audio_saved_at->copy()->addMonths($expectedRetentionMonths)->toDateString(),
            $this->meeting->audio_expires_at->toDateString()
        );

        // Verify event was dispatched
        Event::assertDispatched(MeetingAudioUploaded::class, function ($event) {
            return $event->meeting->id === $this->meeting->id;
        });

        // Verify audit log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'recording_finish',
        ]);
    }

    public function test_finish_recording_fails_when_segments_are_missing(): void
    {
        Event::fake([MeetingAudioUploaded::class]);

        // Only upload seq 1 and seq 3 (seq 2 is missing)
        foreach ([1, 3] as $seq) {
            $content = "segment-content-{$seq}";
            $file = UploadedFile::fake()->createWithContent("seg-{$seq}.webm", $content);
            $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
                'file' => $file,
                'seq' => $seq,
                'checksum' => hash('sha256', $content),
            ])->assertStatus(201);
        }

        $response = $this->actingAs($this->notulis)->postJson(route('meetings.record.finish', $this->meeting), [
            'expected_segments' => 3,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('completed', false);
        $response->assertJsonPath('missing_segments', [2]);

        $this->meeting->refresh();
        $this->assertEquals('recording', $this->meeting->status);
        Event::assertNotDispatched(MeetingAudioUploaded::class);
    }
}
