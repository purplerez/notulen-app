<?php

namespace Tests\Feature\Audio;

use App\Models\AudioSegment;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\TranscriptSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioPurgeTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $notulis;

    private Meeting $expiredMeeting;

    private Meeting $activeMeeting;

    private string $expiredSegPath;

    private string $expiredMergedPath;

    private string $activeSegPath;

    private string $activeMergedPath;

    private AudioSegment $expiredSegment;

    private TranscriptSegment $transcriptSegment;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->org = Organization::factory()->create();
        $this->notulis = User::factory()->create([
            'organization_id' => $this->org->id,
            'role' => 'notulis',
        ]);

        // 1. Expired Meeting
        $this->expiredMeeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->notulis->id,
            'audio_saved_at' => now()->subMonths(2),
            'audio_expires_at' => now()->subDay(),
            'audio_deleted_at' => null,
            'merged_audio_path' => "orgs/{$this->org->id}/meetings/10/merged.m4a",
        ]);

        $this->expiredSegPath = "orgs/{$this->org->id}/meetings/10/seg-1.webm";
        $this->expiredMergedPath = "orgs/{$this->org->id}/meetings/10/merged.m4a";
        Storage::disk('local')->put($this->expiredSegPath, 'EXPIRED_SEGMENT_DATA');
        Storage::disk('local')->put($this->expiredMergedPath, 'EXPIRED_MERGED_DATA');

        $this->expiredSegment = AudioSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->expiredMeeting->id,
            'seq' => 1,
            'path' => $this->expiredSegPath,
            'mime' => 'audio/webm',
            'size_bytes' => 20,
            'duration_sec' => 60.0,
            'checksum' => hash('sha256', 'EXPIRED_SEGMENT_DATA'),
            'status' => 'ready',
        ]);

        // Create raw transcript segment to prove it remains untouched!
        $this->transcriptSegment = TranscriptSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->expiredMeeting->id,
            'audio_segment_id' => $this->expiredSegment->id,
            'start_sec' => 0.0,
            'end_sec' => 59.8,
            'text' => 'Ini adalah teks transkrip mentah yang tidak boleh dihapus.',
        ]);

        // 2. Active Meeting (not yet expired)
        $this->activeMeeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->notulis->id,
            'audio_saved_at' => now()->subDays(5),
            'audio_expires_at' => now()->addDays(25),
            'audio_deleted_at' => null,
            'merged_audio_path' => "orgs/{$this->org->id}/meetings/20/merged.m4a",
        ]);

        $this->activeSegPath = "orgs/{$this->org->id}/meetings/20/seg-1.webm";
        $this->activeMergedPath = "orgs/{$this->org->id}/meetings/20/merged.m4a";
        Storage::disk('local')->put($this->activeSegPath, 'ACTIVE_SEGMENT_DATA');
        Storage::disk('local')->put($this->activeMergedPath, 'ACTIVE_MERGED_DATA');

        AudioSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->activeMeeting->id,
            'seq' => 1,
            'path' => $this->activeSegPath,
            'mime' => 'audio/webm',
            'size_bytes' => 19,
            'duration_sec' => 60.0,
            'checksum' => hash('sha256', 'ACTIVE_SEGMENT_DATA'),
            'status' => 'ready',
        ]);
    }

    public function test_audio_purge_deletes_expired_files_and_preserves_raw_transcripts(): void
    {
        $this->artisan('audio:purge')
            ->expectsOutputToContain("Audio rapat #{$this->expiredMeeting->id}")
            ->assertSuccessful();

        // 1. Files for expired meeting must be deleted from storage
        $this->assertFalse(Storage::disk('local')->exists($this->expiredSegPath));
        $this->assertFalse(Storage::disk('local')->exists($this->expiredMergedPath));

        // 2. Expired meeting model updated
        $this->expiredMeeting->refresh();
        $this->assertNotNull($this->expiredMeeting->audio_deleted_at);
        $this->assertNull($this->expiredMeeting->merged_audio_path);

        // 3. Audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->expiredMeeting->id,
            'action' => 'audio_purge',
        ]);

        // 4. Raw transcript segment MUST still exist untouched!
        $this->assertDatabaseHas('transcript_segments', [
            'id' => $this->transcriptSegment->id,
            'meeting_id' => $this->expiredMeeting->id,
            'text' => 'Ini adalah teks transkrip mentah yang tidak boleh dihapus.',
        ]);

        // 5. Active meeting files MUST remain untouched
        $this->assertTrue(Storage::disk('local')->exists($this->activeSegPath));
        $this->assertTrue(Storage::disk('local')->exists($this->activeMergedPath));
        $this->assertNull($this->activeMeeting->fresh()->audio_deleted_at);
    }

    public function test_audio_purge_is_idempotent(): void
    {
        // First run
        $this->artisan('audio:purge')->assertSuccessful();

        $initialLogsCount = AuditLog::where('action', 'audio_purge')->count();
        $this->assertEquals(1, $initialLogsCount);

        // Second run
        $this->artisan('audio:purge')
            ->expectsOutputToContain('Tidak ada berkas audio yang perlu dibersihkan')
            ->assertSuccessful();

        // No duplicate audit logs
        $this->assertEquals(1, AuditLog::where('action', 'audio_purge')->count());
    }

    public function test_audio_purge_with_specific_meeting_option(): void
    {
        $this->artisan("audio:purge --meeting={$this->expiredMeeting->id}")
            ->assertSuccessful();

        $this->assertFalse(Storage::disk('local')->exists($this->expiredSegPath));
        $this->assertNotNull($this->expiredMeeting->fresh()->audio_deleted_at);
    }
}
