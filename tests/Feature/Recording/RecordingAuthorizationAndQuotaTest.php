<?php

namespace Tests\Feature\Recording;

use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordingAuthorizationAndQuotaTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $creatorNotulis;

    private User $otherNotulis;

    private User $admin;

    private User $pimpinan;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('recording.disk', 'local'));

        $this->org = Organization::factory()->create(['quota_minutes' => 600]);
        $this->creatorNotulis = User::factory()->notulis()->create(['organization_id' => $this->org->id]);
        $this->otherNotulis = User::factory()->notulis()->create(['organization_id' => $this->org->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->pimpinan = User::factory()->pimpinan()->create(['organization_id' => $this->org->id]);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->creatorNotulis->id,
            'status' => 'draft',
        ]);
    }

    public function test_only_notulis_creator_can_access_recording_screen(): void
    {
        // Admin must be denied (Rule 4: raw audio never accessible by admin)
        $this->actingAs($this->admin)->get(route('meetings.record', $this->meeting))->assertForbidden();

        // Pimpinan cannot record
        $this->actingAs($this->pimpinan)->get(route('meetings.record', $this->meeting))->assertForbidden();

        // Another notulis cannot record someone else's meeting
        $this->actingAs($this->otherNotulis)->get(route('meetings.record', $this->meeting))->assertForbidden();

        // Notulis from another tenant cannot see meeting (404 via BelongsToOrganization global scope)
        $otherOrg = Organization::factory()->create();
        $foreignNotulis = User::factory()->notulis()->create(['organization_id' => $otherOrg->id]);
        $this->actingAs($foreignNotulis)->get(route('meetings.record', $this->meeting))->assertNotFound();

        // Creator notulis can view
        $this->actingAs($this->creatorNotulis)->get(route('meetings.record', $this->meeting))
            ->assertOk()
            ->assertSee('Perekaman Audio Rapat');
    }

    public function test_only_notulis_creator_can_start_recording_and_upload(): void
    {
        $file = UploadedFile::fake()->createWithContent('seg-1.webm', 'audio-bytes');
        $checksum = hash('sha256', 'audio-bytes');

        // Admin denied
        $this->actingAs($this->admin)->postJson(route('meetings.record.start', $this->meeting))->assertForbidden();
        $this->actingAs($this->admin)->postJson(route('meetings.record.segments', $this->meeting), [
            'file' => $file,
            'seq' => 1,
            'checksum' => $checksum,
        ])->assertForbidden();

        // Pimpinan denied
        $this->actingAs($this->pimpinan)->postJson(route('meetings.record.start', $this->meeting))->assertForbidden();

        // Creator notulis can start
        $this->actingAs($this->creatorNotulis)->postJson(route('meetings.record.start', $this->meeting))
            ->assertOk()
            ->assertJsonPath('status', 'recording');
    }

    public function test_start_recording_fails_when_organization_quota_is_exhausted(): void
    {
        $this->org->update(['quota_minutes' => 0]);

        $response = $this->actingAs($this->creatorNotulis)->postJson(route('meetings.record.start', $this->meeting));

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Kuota menit organisasi tidak mencukupi atau telah habis. Hubungi administrator.');

        $this->meeting->refresh();
        $this->assertEquals('draft', $this->meeting->status);
    }

    public function test_start_recording_succeeds_and_sets_status_recording(): void
    {
        $this->assertEquals('draft', $this->meeting->status);

        $response = $this->actingAs($this->creatorNotulis)->postJson(route('meetings.record.start', $this->meeting));

        $response->assertOk();
        $response->assertJsonPath('status', 'recording');

        $this->meeting->refresh();
        $this->assertEquals('recording', $this->meeting->status);
        $this->assertNotNull($this->meeting->started_at);

        // Audit log must be recorded
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'recording_start',
        ]);
    }

    public function test_cannot_start_recording_on_already_completed_meeting(): void
    {
        $this->meeting->update(['status' => 'uploaded']);

        $response = $this->actingAs($this->creatorNotulis)->postJson(route('meetings.record.start', $this->meeting));

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Rapat ini sudah selesai direkam dan tidak dapat dimulai ulang.');
    }
}
