<?php

namespace Tests\Feature\Minutes;

use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MinuteEditingAndFinalizationTest extends TestCase
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
            'title' => 'Rapat Evaluasi Triwulan',
            'style' => 'lengkap',
            'use_followup' => true,
        ]);

        $this->draftMinute = Minute::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'version' => 1,
            'status' => 'draft',
            'content_json' => [
                'ringkasan' => 'Draf awal otomatis.',
                'agenda' => ['Agenda 1'],
                'pembahasan' => 'Pembahasan materi awal.',
                'keputusan' => ['Keputusan 1'],
                'tindak_lanjut' => [
                    ['description' => 'Tugas awal', 'assignee_name' => 'Staf', 'due_date' => '2026-10-30'],
                ],
            ],
            'created_by' => $this->creatorNotulis->id,
        ]);
    }

    public function test_creator_notulis_can_view_and_edit_draft_minute(): void
    {
        $response = $this->actingAs($this->creatorNotulis)
            ->get(route('meetings.minutes.edit', $this->meeting));

        $response->assertOk();
        $response->assertSee('Penyuntingan Draf Notulen');
        $response->assertSee('Draf awal otomatis.');

        $updateResponse = $this->actingAs($this->creatorNotulis)
            ->put(route('meetings.minutes.update', $this->meeting), [
                'minute_id' => $this->draftMinute->id,
                'ringkasan' => 'Ringkasan yang sudah disunting notulis.',
                'agenda' => ['Agenda Revisi A', 'Agenda Revisi B'],
                'pembahasan' => 'Pembahasan yang diperluas.',
                'keputusan' => ['Keputusan Baru 1', 'Keputusan Baru 2'],
                'action_items' => [
                    [
                        'description' => 'Tugas Baru PIC',
                        'assignee_name' => 'Ahmad',
                        'due_date' => '2026-11-01',
                        'status' => 'pending',
                    ],
                ],
            ]);

        $updateResponse->assertRedirect(route('meetings.minutes.show', ['meeting' => $this->meeting, 'v' => 1]));

        $this->draftMinute->refresh();
        $this->assertSame('Ringkasan yang sudah disunting notulis.', $this->draftMinute->getSection('ringkasan'));
        $this->assertSame(['Agenda Revisi A', 'Agenda Revisi B'], $this->draftMinute->getSection('agenda'));
        $this->assertSame(['Keputusan Baru 1', 'Keputusan Baru 2'], $this->draftMinute->getSection('keputusan'));

        $this->assertDatabaseHas('action_items', [
            'minute_id' => $this->draftMinute->id,
            'description' => 'Tugas Baru PIC',
            'assignee_name' => 'Ahmad',
        ]);
    }

    public function test_finalization_strictly_requires_share_expires_at_in_the_future(): void
    {
        // 1. Missing share_expires_at
        $response1 = $this->actingAs($this->creatorNotulis)
            ->post(route('meetings.minutes.finalize', $this->meeting), [
                'minute_id' => $this->draftMinute->id,
            ]);

        $response1->assertSessionHasErrors('share_expires_at');
        $this->assertSame('draft', $this->draftMinute->fresh()->status);

        // 2. Past date for share_expires_at
        $response2 = $this->actingAs($this->creatorNotulis)
            ->post(route('meetings.minutes.finalize', $this->meeting), [
                'minute_id' => $this->draftMinute->id,
                'share_expires_at' => now()->subDay()->format('Y-m-d H:i:s'),
            ]);

        $response2->assertSessionHasErrors('share_expires_at');
        $this->assertSame('draft', $this->draftMinute->fresh()->status);
    }

    public function test_creator_notulis_successfully_finalizes_minute_and_records_audit(): void
    {
        $futureDate = now()->addDays(7)->format('Y-m-d H:i:s');

        $response = $this->actingAs($this->creatorNotulis)
            ->post(route('meetings.minutes.finalize', $this->meeting), [
                'minute_id' => $this->draftMinute->id,
                'share_expires_at' => $futureDate,
            ]);

        $response->assertRedirect(route('meetings.minutes.show', ['meeting' => $this->meeting, 'v' => 1]));

        $this->draftMinute->refresh();
        $this->assertSame('final', $this->draftMinute->status);
        $this->assertNotNull($this->draftMinute->finalized_at);

        $this->meeting->refresh();
        $this->assertNotNull($this->meeting->share_expires_at);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'minute_finalize',
        ]);
    }

    public function test_admin_cannot_finalize_initial_draft_version_1(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('meetings.minutes.finalize', $this->meeting), [
                'minute_id' => $this->draftMinute->id,
                'share_expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            ]);

        $response->assertForbidden();
        $this->assertSame('draft', $this->draftMinute->fresh()->status);
    }

    public function test_pimpinan_cannot_view_or_finalize_draft_version_1(): void
    {
        // Pimpinan cannot view draft
        $viewResponse = $this->actingAs($this->pimpinan)
            ->get(route('meetings.minutes.show', ['meeting' => $this->meeting, 'v' => 1]));
        $viewResponse->assertForbidden();

        // Pimpinan cannot edit draft
        $editResponse = $this->actingAs($this->pimpinan)
            ->get(route('meetings.minutes.edit', ['meeting' => $this->meeting, 'v' => 1]));
        $editResponse->assertForbidden();

        // Pimpinan cannot finalize draft
        $finalizeResponse = $this->actingAs($this->pimpinan)
            ->post(route('meetings.minutes.finalize', $this->meeting), [
                'minute_id' => $this->draftMinute->id,
                'share_expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            ]);
        $finalizeResponse->assertForbidden();
    }

    public function test_other_notulis_cannot_edit_or_finalize_draft(): void
    {
        $response = $this->actingAs($this->otherNotulis)
            ->post(route('meetings.minutes.finalize', $this->meeting), [
                'minute_id' => $this->draftMinute->id,
                'share_expires_at' => now()->addDays(7)->format('Y-m-d H:i:s'),
            ]);

        $response->assertForbidden();
    }
}
