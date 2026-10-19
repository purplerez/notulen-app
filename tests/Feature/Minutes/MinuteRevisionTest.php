<?php

namespace Tests\Feature\Minutes;

use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinuteRevisionTest extends TestCase
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
            'title' => 'Rapat Pleno Kurikulum',
            'style' => 'lengkap',
            'use_followup' => true,
            'share_expires_at' => now()->addDays(7),
        ]);

        $this->finalMinuteV1 = Minute::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'version' => 1,
            'status' => 'final',
            'content_json' => [
                'ringkasan' => 'Ringkasan asli versi 1.',
                'agenda' => ['Agenda v1'],
                'pembahasan' => 'Pembahasan v1',
                'keputusan' => ['Keputusan Awal v1'],
                'tindak_lanjut' => [
                    ['description' => 'Tindak lanjut v1', 'assignee_name' => 'Pak Joko', 'due_date' => '2026-11-01'],
                ],
            ],
            'created_by' => $this->creatorNotulis->id,
            'finalized_at' => now()->subDay(),
        ]);

        ActionItem::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'minute_id' => $this->finalMinuteV1->id,
            'description' => 'Tindak lanjut v1',
            'assignee_name' => 'Pak Joko',
            'due_date' => '2026-11-01',
            'status' => 'pending',
        ]);
    }

    public function test_revising_requires_mandatory_revision_reason(): void
    {
        // 1. Missing reason
        $response1 = $this->actingAs($this->creatorNotulis)
            ->post(route('meetings.minutes.revise', $this->meeting), []);

        $response1->assertSessionHasErrors('revision_reason');
        $this->assertSame(1, Minute::where('meeting_id', $this->meeting->id)->count());

        // 2. Reason too short (< 5 chars)
        $response2 = $this->actingAs($this->creatorNotulis)
            ->post(route('meetings.minutes.revise', $this->meeting), [
                'revision_reason' => 'edit',
            ]);

        $response2->assertSessionHasErrors('revision_reason');
        $this->assertSame(1, Minute::where('meeting_id', $this->meeting->id)->count());
    }

    public function test_creator_notulis_creates_new_revision_draft_and_preserves_version_1(): void
    {
        $response = $this->actingAs($this->creatorNotulis)
            ->post(route('meetings.minutes.revise', $this->meeting), [
                'revision_reason' => 'Koreksi penanggung jawab tugas dan butir keputusan.',
            ]);

        $response->assertRedirect(route('meetings.minutes.edit', ['meeting' => $this->meeting, 'v' => 2]));

        // Check database
        $this->assertSame(2, Minute::where('meeting_id', $this->meeting->id)->count());

        // Version 1 is still intact and final
        $v1 = Minute::where('meeting_id', $this->meeting->id)->where('version', 1)->first();
        $this->assertNotNull($v1);
        $this->assertSame('final', $v1->status);
        $this->assertSame('Ringkasan asli versi 1.', $v1->getSection('ringkasan'));

        // Version 2 is draft, with copied content and revision reason
        $v2 = Minute::where('meeting_id', $this->meeting->id)->where('version', 2)->first();
        $this->assertNotNull($v2);
        $this->assertSame('draft', $v2->status);
        $this->assertNull($v2->finalized_at);
        $this->assertSame('Koreksi penanggung jawab tugas dan butir keputusan.', $v2->revision_reason);
        $this->assertSame((int) $this->creatorNotulis->id, (int) $v2->created_by);

        // Action items copied to version 2
        $this->assertTrue(ActionItem::where('minute_id', $v2->id)->where('description', 'Tindak lanjut v1')->exists());

        // Audit log recorded
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'minute_revise',
        ]);
    }

    public function test_admin_can_revise_and_finalize_their_own_revision(): void
    {
        // 1. Admin creates revision draft (version 2)
        $reviseResponse = $this->actingAs($this->admin)
            ->post(route('meetings.minutes.revise', $this->meeting), [
                'revision_reason' => 'Revisi oleh administrator atas arahan pimpinan.',
            ]);

        $reviseResponse->assertRedirect(route('meetings.minutes.edit', ['meeting' => $this->meeting, 'v' => 2]));

        $v2 = Minute::where('meeting_id', $this->meeting->id)->where('version', 2)->first();
        $this->assertNotNull($v2);
        $this->assertSame((int) $this->admin->id, (int) $v2->created_by);
        $this->assertSame('draft', $v2->status);

        // 2. Admin edits version 2
        $updateResponse = $this->actingAs($this->admin)
            ->put(route('meetings.minutes.update', $this->meeting), [
                'minute_id' => $v2->id,
                'ringkasan' => 'Ringkasan yang diperbaiki admin.',
                'keputusan' => ['Keputusan Baru Versi 2'],
            ]);
        $updateResponse->assertRedirect();

        // 3. Admin finalizes version 2 (allowed for revision author!)
        $finalizeResponse = $this->actingAs($this->admin)
            ->post(route('meetings.minutes.finalize', $this->meeting), [
                'minute_id' => $v2->id,
                'share_expires_at' => now()->addDays(14)->format('Y-m-d H:i:s'),
            ]);

        $finalizeResponse->assertRedirect(route('meetings.minutes.show', ['meeting' => $this->meeting, 'v' => 2]));

        $v2->refresh();
        $this->assertSame('final', $v2->status);
        $this->assertNotNull($v2->finalized_at);
    }

    public function test_during_revision_draft_pimpinan_still_sees_latest_final_version_1(): void
    {
        // Notulis creates draft revision v2
        $this->actingAs($this->creatorNotulis)
            ->post(route('meetings.minutes.revise', $this->meeting), [
                'revision_reason' => 'Sedang menyiapkan versi 2.',
            ]);

        // Pimpinan opens minutes default page
        $pimpinanResponse = $this->actingAs($this->pimpinan)
            ->get(route('meetings.minutes.show', $this->meeting));

        $pimpinanResponse->assertOk();
        // Sees version 1
        $pimpinanResponse->assertSee('Ringkasan asli versi 1.');
        $pimpinanResponse->assertSee('Versi 1');
        // Does NOT see draft version 2
        $pimpinanResponse->assertDontSee('Sedang menyiapkan versi 2.');
    }

    public function test_pimpinan_cannot_start_a_revision(): void
    {
        $response = $this->actingAs($this->pimpinan)
            ->post(route('meetings.minutes.revise', $this->meeting), [
                'revision_reason' => 'Pimpinan ingin merevisi langsung.',
            ]);

        $response->assertForbidden();
    }
}
