<?php

namespace Tests\Feature\Transcript;

use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\SuperAdmin;
use App\Models\TranscriptSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranscriptAccessAndEditTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $creatorNotulisA;

    private User $otherNotulisA;

    private User $pimpinanA;

    private User $notulisB;

    private Meeting $meeting;

    private AudioSegment $audioSegment;

    private TranscriptSegment $transcriptSegment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->adminA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => 'admin',
        ]);

        $this->creatorNotulisA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => 'notulis',
        ]);

        $this->otherNotulisA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => 'notulis',
        ]);

        $this->pimpinanA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => 'pimpinan',
        ]);

        $this->notulisB = User::factory()->create([
            'organization_id' => $this->orgB->id,
            'role' => 'notulis',
        ]);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->creatorNotulisA->id,
            'status' => 'ready',
        ]);

        $this->audioSegment = AudioSegment::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'seq' => 1,
            'path' => "orgs/{$this->orgA->id}/meetings/{$this->meeting->id}/seg-1.webm",
            'mime' => 'audio/webm',
            'size_bytes' => 1024,
            'duration_sec' => 60.0,
            'checksum' => hash('sha256', 'dummy'),
            'status' => 'ready',
        ]);

        $this->transcriptSegment = TranscriptSegment::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'audio_segment_id' => $this->audioSegment->id,
            'start_sec' => 0.0,
            'end_sec' => 15.5,
            'text' => 'Selamat pagi bapak dan ibu sekalian rapat kita mulai.',
            'edited_text' => null,
            'edited_by' => null,
        ]);
    }

    public function test_admin_is_strictly_forbidden_from_viewing_transcript(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('meetings.transcript.show', $this->meeting));

        $response->assertStatus(403);
    }

    public function test_super_admin_is_forbidden_from_viewing_transcript(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@platform.test',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->actingAs($superAdmin, 'platform')->get(route('meetings.transcript.show', $this->meeting));

        $response->assertStatus(403);
    }

    public function test_other_notulis_in_same_org_cannot_view_transcript(): void
    {
        $response = $this->actingAs($this->otherNotulisA)->get(route('meetings.transcript.show', $this->meeting));

        $response->assertStatus(403);
    }

    public function test_user_from_different_org_cannot_view_transcript(): void
    {
        $response = $this->actingAs($this->notulisB)->get(route('meetings.transcript.show', $this->meeting));

        // BelongsToOrganization global scope isolates cross-tenant query, returning 404
        $response->assertStatus(404);
    }

    public function test_pimpinan_can_view_transcript_read_only_and_records_audit_log(): void
    {
        $response = $this->actingAs($this->pimpinanA)->get(route('meetings.transcript.show', $this->meeting));

        $response->assertStatus(200);
        $response->assertSee('Transkrip otomatis, belum diperiksa');
        $response->assertSee('Selamat pagi bapak dan ibu sekalian rapat kita mulai.');
        // Read only for pimpinan: edit form is not available
        $response->assertSee('Mode Baca Saja (Pimpinan)');
        $response->assertDontSee('Perbaiki Teks Transkrip');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'actor_user_id' => $this->pimpinanA->id,
            'action' => 'transcript_view',
        ]);
    }

    public function test_creator_notulis_can_view_transcript_and_sees_mandatory_label(): void
    {
        $response = $this->actingAs($this->creatorNotulisA)->get(route('meetings.transcript.show', $this->meeting));

        $response->assertStatus(200);
        $response->assertSee('Transkrip otomatis, belum diperiksa');
        $response->assertSee('Selamat pagi bapak dan ibu sekalian rapat kita mulai.');
        $response->assertSee('Sunting');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'actor_user_id' => $this->creatorNotulisA->id,
            'action' => 'transcript_view',
        ]);
    }

    public function test_pimpinan_cannot_edit_transcript(): void
    {
        $response = $this->actingAs($this->pimpinanA)->put(
            route('meetings.transcript.update', [$this->meeting, $this->transcriptSegment]),
            ['edited_text' => 'Percobaan edit oleh pimpinan']
        );

        $response->assertStatus(403);
        $this->assertNull($this->transcriptSegment->fresh()->edited_text);
    }

    public function test_creator_notulis_can_edit_transcript_and_original_text_is_never_overwritten(): void
    {
        $originalText = $this->transcriptSegment->text;
        $correction = 'Selamat pagi Bapak dan Ibu sekalian, rapat dinas kita mulai.';

        $response = $this->actingAs($this->creatorNotulisA)->put(
            route('meetings.transcript.update', [$this->meeting, $this->transcriptSegment]),
            ['edited_text' => $correction]
        );

        $response->assertRedirect(route('meetings.transcript.show', $this->meeting));

        $this->transcriptSegment->refresh();

        // 1. edited_text is saved
        $this->assertEquals($correction, $this->transcriptSegment->edited_text);

        // 2. edited_by is recorded
        $this->assertEquals($this->creatorNotulisA->id, $this->transcriptSegment->edited_by);

        // 3. Original text MUST NOT be overwritten!
        $this->assertEquals($originalText, $this->transcriptSegment->text);

        // 4. Audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'transcript_edit',
        ]);
    }

    public function test_creator_notulis_can_reset_edited_transcript(): void
    {
        $this->transcriptSegment->update([
            'edited_text' => 'Teks yang sudah diedit',
            'edited_by' => $this->creatorNotulisA->id,
        ]);

        $response = $this->actingAs($this->creatorNotulisA)->post(
            route('meetings.transcript.reset', [$this->meeting, $this->transcriptSegment])
        );

        $response->assertRedirect(route('meetings.transcript.show', $this->meeting));

        $this->transcriptSegment->refresh();
        $this->assertNull($this->transcriptSegment->edited_text);
        $this->assertNull($this->transcriptSegment->edited_by);
        $this->assertEquals('Selamat pagi bapak dan ibu sekalian rapat kita mulai.', $this->transcriptSegment->text);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'transcript_reset',
        ]);
    }

    public function test_transcript_diff_percentage_calculation(): void
    {
        // Add a second segment
        TranscriptSegment::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'audio_segment_id' => $this->audioSegment->id,
            'start_sec' => 15.5,
            'end_sec' => 30.0,
            'text' => 'Agenda pertama adalah pembahasan kurikulum merdeka.',
            'edited_text' => 'Agenda pertama adalah pembahasan kurikulum nasional.',
            'edited_by' => $this->creatorNotulisA->id,
        ]);

        $stats = $this->meeting->getTranscriptStats();

        $this->assertEquals(2, $stats['total_segments']);
        $this->assertEquals(1, $stats['edited_segments']);
        $this->assertEquals(50.0, $stats['edited_segments_percent']);
        $this->assertGreaterThan(0.0, $stats['difference_percent']);

        // Verify page renders stats
        $response = $this->actingAs($this->creatorNotulisA)->get(route('meetings.transcript.show', $this->meeting));
        $response->assertStatus(200);
        $response->assertSee("{$stats['difference_percent']}%");
        $response->assertSee('Perbandingan Asli vs Edit');
    }

    public function test_transcript_is_accessible_without_time_limit_even_after_audio_is_purged(): void
    {
        $this->meeting->update([
            'audio_saved_at' => now()->subMonths(3),
            'audio_expires_at' => now()->subMonths(2),
            'audio_deleted_at' => now()->subMonth(),
            'merged_audio_path' => null,
        ]);

        $response = $this->actingAs($this->creatorNotulisA)->get(route('meetings.transcript.show', $this->meeting));

        $response->assertStatus(200);
        $response->assertSee('Transkrip otomatis, belum diperiksa');
        $response->assertSee('Audio rekaman telah dihapus sesuai retensi');
        $response->assertSee($this->transcriptSegment->text);
    }
}
