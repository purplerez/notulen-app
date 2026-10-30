<?php

namespace Tests\Feature\Recording;

use App\Models\AudioSegment;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordingQuotaGracePeriodTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $notulis;

    protected Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('recording.disk', 'local'));

        // Organization with 5 minutes quota
        $this->org = Organization::factory()->create([
            'quota_minutes' => 5,
        ]);

        $this->notulis = User::factory()->notulis()->create([
            'organization_id' => $this->org->id,
        ]);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->notulis->id,
            'status' => 'recording',
        ]);

        config(['quota.grace_period_minutes' => 15]);
    }

    public function test_recording_screen_includes_quota_and_grace_period_parameters(): void
    {
        $response = $this->actingAs($this->notulis)
            ->get(route('meetings.record', $this->meeting));

        $response->assertOk();
        $response->assertSee('initialAvailableQuotaMinutes: 5', false);
        $response->assertSee('gracePeriodMinutes: 15', false);
        $response->assertSee('+Top-up');
    }

    public function test_upload_segment_returns_quota_payload_with_remaining_minutes(): void
    {
        $content = 'fake audio content for test';
        $checksum = hash('sha256', $content);
        $file = UploadedFile::fake()->createWithContent('segment1.webm', $content);

        // Segment duration 60s = 1 minute used
        $response = $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
            'file' => $file,
            'seq' => 1,
            'checksum' => $checksum,
            'duration_sec' => 60,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('quota.available_minutes', 4);
        $response->assertJsonPath('quota.is_in_grace_period', false);
        $response->assertJsonPath('quota.grace_expired', false);
    }

    public function test_upload_segment_triggers_grace_period_when_quota_exhausted(): void
    {
        // Simulate existing segments consuming all 5 minutes
        AudioSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'seq' => 1,
            'path' => 'dummy/path/seg1.webm',
            'mime' => 'audio/webm',
            'size_bytes' => 1024,
            'duration_sec' => 300, // 5 minutes
            'checksum' => str_repeat('a', 64),
            'status' => 'uploaded',
        ]);

        // Upload another segment that pushes usage past the 5-minute quota into grace period
        $content = 'extra audio in grace period';
        $checksum = hash('sha256', $content);
        $file = UploadedFile::fake()->createWithContent('segment2.webm', $content);

        $response = $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
            'file' => $file,
            'seq' => 2,
            'checksum' => $checksum,
            'duration_sec' => 60,
        ]);

        $response->assertStatus(201);
        // Quota is 0, in grace period
        $response->assertJsonPath('quota.available_minutes', 0);
        $response->assertJsonPath('quota.is_in_grace_period', true);
        $response->assertJsonPath('quota.grace_expired', false);

        // Audit log for grace period start must be recorded
        $audit = AuditLog::withoutGlobalScopes()
            ->where('action', 'recording_grace_period_start')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->meeting->id, $audit->meeting_id);
    }

    public function test_upload_segment_still_succeeds_even_when_grace_period_exceeded(): void
    {
        // 5 min quota + 15 min grace = 20 min total buffer.
        // Simulate existing usage of 22 minutes (1320 seconds)
        AudioSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'seq' => 1,
            'path' => 'dummy/path/seg1.webm',
            'mime' => 'audio/webm',
            'size_bytes' => 1024,
            'duration_sec' => 1320,
            'checksum' => str_repeat('b', 64),
            'status' => 'uploaded',
        ]);

        $content = 'last audio segment';
        $checksum = hash('sha256', $content);
        $file = UploadedFile::fake()->createWithContent('segment3.webm', $content);

        $response = $this->actingAs($this->notulis)->postJson(route('meetings.record.segments', $this->meeting), [
            'file' => $file,
            'seq' => 3,
            'checksum' => $checksum,
            'duration_sec' => 60,
        ]);

        // Segment upload STILL succeeds (201) so audio is not lost
        $response->assertStatus(201);
        $response->assertJsonPath('quota.grace_expired', true);
    }
}
