<?php

namespace Tests\Feature\Minutes;

use App\Models\Meeting;
use App\Models\Minute;
use App\Models\MinuteAcknowledgement;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinuteAcknowledgementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $creatorNotulis;

    private User $admin;

    private User $pimpinan;

    private Meeting $meeting;

    private Minute $finalMinuteV1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->creatorNotulis = User::factory()->notulis()->create(['organization_id' => $this->org->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->org->id]);
        $this->pimpinan = User::factory()->pimpinan()->create(['organization_id' => $this->org->id]);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->creatorNotulis->id,
            'title' => 'Rapat Evaluasi Mutu Sekolah',
            'style' => 'lengkap',
            'share_expires_at' => now()->addDays(7),
        ]);

        $this->finalMinuteV1 = Minute::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'version' => 1,
            'status' => 'final',
            'content_json' => [
                'ringkasan' => 'Evaluasi berjalan lancar dengan kesepakatan target baru.',
                'agenda' => ['Target Mutu'],
                'pembahasan' => 'Pembahasan mutu akademik.',
                'keputusan' => ['Standar nilai dinaikkan 5%.'],
            ],
            'created_by' => $this->creatorNotulis->id,
            'finalized_at' => now()->subDay(),
        ]);
    }

    public function test_pimpinan_can_acknowledge_final_minutes(): void
    {
        $response = $this->actingAs($this->pimpinan)
            ->post(route('meetings.minutes.acknowledge', ['meeting' => $this->meeting, 'minute' => $this->finalMinuteV1]));

        $response->assertRedirect(route('meetings.minutes.show', ['meeting' => $this->meeting, 'v' => 1]));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('minute_acknowledgements', [
            'organization_id' => $this->org->id,
            'minute_id' => $this->finalMinuteV1->id,
            'user_id' => $this->pimpinan->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'actor_user_id' => $this->pimpinan->id,
            'action' => 'minute_acknowledge',
        ]);

        $this->assertTrue($this->finalMinuteV1->isAcknowledgedBy($this->pimpinan));
    }

    public function test_acknowledgement_is_idempotent_and_does_not_duplicate(): void
    {
        MinuteAcknowledgement::create([
            'organization_id' => $this->org->id,
            'minute_id' => $this->finalMinuteV1->id,
            'user_id' => $this->pimpinan->id,
            'acknowledged_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->pimpinan)
            ->post(route('meetings.minutes.acknowledge', ['meeting' => $this->meeting, 'minute' => $this->finalMinuteV1]));

        $response->assertRedirect();
        $this->assertSame(1, MinuteAcknowledgement::where('minute_id', $this->finalMinuteV1->id)->where('user_id', $this->pimpinan->id)->count());
    }

    public function test_pimpinan_cannot_acknowledge_a_draft_minute(): void
    {
        $draftMinute = Minute::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'version' => 2,
            'status' => 'draft',
            'content_json' => ['ringkasan' => 'Draf revisi belum final'],
            'created_by' => $this->creatorNotulis->id,
            'revision_reason' => 'Perbaikan draf',
        ]);

        $response = $this->actingAs($this->pimpinan)
            ->post(route('meetings.minutes.acknowledge', ['meeting' => $this->meeting, 'minute' => $draftMinute]));

        $response->assertForbidden();
        $this->assertDatabaseMissing('minute_acknowledgements', [
            'minute_id' => $draftMinute->id,
        ]);
    }

    public function test_notulis_and_admin_cannot_acknowledge_minutes(): void
    {
        // Notulis
        $responseNotulis = $this->actingAs($this->creatorNotulis)
            ->post(route('meetings.minutes.acknowledge', ['meeting' => $this->meeting, 'minute' => $this->finalMinuteV1]));
        $responseNotulis->assertForbidden();

        // Admin
        $responseAdmin = $this->actingAs($this->admin)
            ->post(route('meetings.minutes.acknowledge', ['meeting' => $this->meeting, 'minute' => $this->finalMinuteV1]));
        $responseAdmin->assertForbidden();

        $this->assertDatabaseMissing('minute_acknowledgements', [
            'minute_id' => $this->finalMinuteV1->id,
        ]);
    }

    public function test_cross_tenant_pimpinan_cannot_acknowledge_minutes(): void
    {
        $otherOrg = Organization::factory()->create();
        $otherPimpinan = User::factory()->pimpinan()->create(['organization_id' => $otherOrg->id]);

        $response = $this->actingAs($otherPimpinan)
            ->post(route('meetings.minutes.acknowledge', ['meeting' => $this->meeting, 'minute' => $this->finalMinuteV1]));

        // Cross-tenant meeting will be 404 due to BelongsToOrganization global scope
        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_pimpinan_acknowledgement_shows_acknowledged_status_on_page(): void
    {
        MinuteAcknowledgement::create([
            'organization_id' => $this->org->id,
            'minute_id' => $this->finalMinuteV1->id,
            'user_id' => $this->pimpinan->id,
            'acknowledged_at' => now(),
        ]);

        $response = $this->actingAs($this->pimpinan)
            ->get(route('meetings.minutes.show', $this->meeting));

        $response->assertOk();
        $response->assertSee('Diketahui (Versi 1');
        $response->assertDontSee('Tandai Diketahui');
    }

    public function test_pimpinan_who_has_not_acknowledged_sees_acknowledge_button(): void
    {
        $response = $this->actingAs($this->pimpinan)
            ->get(route('meetings.minutes.show', $this->meeting));

        $response->assertOk();
        $response->assertSee('Tandai Diketahui (Versi 1)');
    }
}
