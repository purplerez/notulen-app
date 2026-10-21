<?php

namespace Tests\Feature\PublicShare;

use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShareLinkManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $creatorNotulis;

    private User $otherNotulis;

    private User $admin;

    private User $pimpinan;

    private Meeting $meeting;

    private Minute $draftMinute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->creatorNotulis = User::factory()->notulis()->create(['organization_id' => $this->org->id]);
        $this->otherNotulis = User::factory()->notulis()->create(['organization_id' => $this->org->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->pimpinan = User::factory()->pimpinan()->create(['organization_id' => $this->org->id]);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->creatorNotulis->id,
            'title' => 'Rapat Koordinasi Anggaran',
            'status' => 'uploaded',
        ]);

        $this->draftMinute = Minute::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'version' => 1,
            'status' => 'draft',
            'content_json' => [
                'ringkasan' => 'Draf notulen koordinasi.',
                'agenda' => ['Pembahasan Anggaran'],
                'pembahasan' => 'Diskusi pagu indikatif.',
                'keputusan' => ['Pagu disetujui'],
                'tindak_lanjut' => [],
            ],
            'created_by' => $this->creatorNotulis->id,
        ]);
    }

    public function test_finalization_automatically_generates_share_link(): void
    {
        $expiresAt = Carbon::now()->addDays(7)->toDateTimeString();

        $response = $this->actingAs($this->creatorNotulis)->post("/meetings/{$this->meeting->id}/minutes/finalize", [
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'share_expires_at' => $expiresAt,
        ]);

        $response->assertRedirect(route('meetings.minutes.show', ['meeting' => $this->meeting, 'v' => 1]));
        $response->assertSessionHas('plain_share_token');

        $this->assertDatabaseHas('share_links', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'created_by' => $this->creatorNotulis->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'link_create',
            'actor_user_id' => $this->creatorNotulis->id,
        ]);
    }

    public function test_notulis_creator_can_manually_generate_new_link_revoking_previous(): void
    {
        // First link
        $firstLink = ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', 'old-token'),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(2),
            'created_by' => $this->creatorNotulis->id,
        ]);

        $newExpiresAt = Carbon::now()->addDays(5)->toDateTimeString();

        $response = $this->actingAs($this->creatorNotulis)->post("/meetings/{$this->meeting->id}/share-links", [
            'access_mode' => 'code',
            'recipients_scope' => 'semua_undangan',
            'expires_at' => $newExpiresAt,
        ]);

        $response->assertRedirect("/meetings/{$this->meeting->id}/minutes");
        $response->assertSessionHas('plain_share_token');
        $response->assertSessionHas('plain_share_pin');

        // Old link is revoked
        $firstLink->refresh();
        $this->assertNotNull($firstLink->revoked_at);

        // New link exists and has code_hash
        $this->assertDatabaseHas('share_links', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'access_mode' => 'code',
            'recipients_scope' => 'semua_undangan',
            'revoked_at' => null,
        ]);
    }

    public function test_notulis_creator_can_revoke_active_link(): void
    {
        $link = ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', 'token-to-revoke'),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->creatorNotulis->id,
        ]);

        $response = $this->actingAs($this->creatorNotulis)->post("/meetings/{$this->meeting->id}/share-links/{$link->id}/revoke");

        $response->assertRedirect("/meetings/{$this->meeting->id}/minutes");
        $link->refresh();
        $this->assertNotNull($link->revoked_at);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'link_revoke',
            'actor_user_id' => $this->creatorNotulis->id,
        ]);
    }

    public function test_other_roles_cannot_create_or_revoke_share_links(): void
    {
        $link = ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', 'secure-token'),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->creatorNotulis->id,
        ]);

        $payload = [
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(2)->toDateTimeString(),
        ];

        // Other notulis
        $this->actingAs($this->otherNotulis)->post("/meetings/{$this->meeting->id}/share-links", $payload)
            ->assertForbidden();
        $this->actingAs($this->otherNotulis)->post("/meetings/{$this->meeting->id}/share-links/{$link->id}/revoke")
            ->assertForbidden();

        // Admin
        $this->actingAs($this->admin)->post("/meetings/{$this->meeting->id}/share-links", $payload)
            ->assertForbidden();
        $this->actingAs($this->admin)->post("/meetings/{$this->meeting->id}/share-links/{$link->id}/revoke")
            ->assertForbidden();

        // Pimpinan
        $this->actingAs($this->pimpinan)->post("/meetings/{$this->meeting->id}/share-links", $payload)
            ->assertForbidden();
        $this->actingAs($this->pimpinan)->post("/meetings/{$this->meeting->id}/share-links/{$link->id}/revoke")
            ->assertForbidden();
    }

    public function test_recipient_opened_status_is_derived_from_audit_logs(): void
    {
        $attendeeBudi = Attendee::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Budi Santoso',
            'phone_e164' => '081234567890',
            'status' => 'hadir',
        ]);

        $attendeeDewi = Attendee::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Dewi Lestari',
            'phone_e164' => '089876543210',
            'status' => 'hadir',
        ]);

        // Finalize minute to make show page render distribution card
        $this->draftMinute->update([
            'status' => 'final',
            'finalized_at' => Carbon::now(),
        ]);

        ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', 'plain-token-here'),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->creatorNotulis->id,
        ]);

        // Simulate Budi opening the link
        AuditLog::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'link_open',
            'actor_attendee_id' => $attendeeBudi->id,
            'created_at' => Carbon::now()->subMinutes(5),
        ]);

        $response = $this->actingAs($this->creatorNotulis)->get("/meetings/{$this->meeting->id}/minutes");

        $response->assertOk();
        $response->assertSee('Budi Santoso');
        $response->assertSee('Dewi Lestari');
        $response->assertSee('Dibuka');
        $response->assertSee('Belum Dibuka');
    }
}
