<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_notulis_and_admin_can_view_create_page_and_create_meeting(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        // Notulis can create
        $this->actingAs($notulis)
            ->get('/meetings/create')
            ->assertStatus(200);

        $responseNotulis = $this->actingAs($notulis)->post('/meetings', [
            'title' => 'Rapat Notulis',
            'meeting_date' => '2026-10-20',
            'start_time' => '10:00',
            'location' => 'Ruang Guru',
            'style' => 'ringkas',
            'late_after_minutes' => 15,
            'use_followup' => true,
        ]);
        $responseNotulis->assertRedirect();
        $this->assertDatabaseHas('meetings', [
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'title' => 'Rapat Notulis',
        ]);

        // Admin can create
        $this->actingAs($admin)
            ->get('/meetings/create')
            ->assertStatus(200);

        $responseAdmin = $this->actingAs($admin)->post('/meetings', [
            'title' => 'Rapat Admin',
            'meeting_date' => '2026-10-21',
            'start_time' => '13:00',
            'location' => 'Aula',
            'style' => 'lengkap',
        ]);
        $responseAdmin->assertRedirect();
        $this->assertDatabaseHas('meetings', [
            'organization_id' => $org->id,
            'created_by' => $admin->id,
            'title' => 'Rapat Admin',
        ]);
    }

    public function test_pimpinan_cannot_create_meeting(): void
    {
        $org = Organization::factory()->create();
        $pimpinan = User::factory()->pimpinan()->create(['organization_id' => $org->id]);

        $this->actingAs($pimpinan)
            ->get('/meetings/create')
            ->assertStatus(403);

        $this->actingAs($pimpinan)
            ->post('/meetings', [
                'title' => 'Rapat Ilegal Pimpinan',
                'meeting_date' => '2026-10-25',
                'style' => 'ringkas',
            ])
            ->assertStatus(403);
    }

    public function test_notulis_can_only_manage_their_own_meetings(): void
    {
        $org = Organization::factory()->create();
        $notulis1 = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $notulis2 = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $meeting1 = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis1->id,
            'title' => 'Rapat Notulis 1',
        ]);

        // Notulis 1 can view, edit, update, delete
        $this->actingAs($notulis1)
            ->get("/meetings/{$meeting1->id}")
            ->assertStatus(200);

        $this->actingAs($notulis1)
            ->get("/meetings/{$meeting1->id}/edit")
            ->assertStatus(200);

        $this->actingAs($notulis1)
            ->put("/meetings/{$meeting1->id}", [
                'title' => 'Rapat Notulis 1 Diubah',
                'meeting_date' => '2026-10-22',
                'style' => 'ringkas',
            ])
            ->assertRedirect();

        // Notulis 2 CANNOT view, edit, update, or delete meeting 1
        $this->actingAs($notulis2)
            ->get("/meetings/{$meeting1->id}")
            ->assertStatus(403);

        $this->actingAs($notulis2)
            ->get("/meetings/{$meeting1->id}/edit")
            ->assertStatus(403);

        $this->actingAs($notulis2)
            ->put("/meetings/{$meeting1->id}", [
                'title' => 'Percobaan Ilegal Notulis 2',
                'meeting_date' => '2026-10-22',
                'style' => 'ringkas',
            ])
            ->assertStatus(403);

        $this->actingAs($notulis2)
            ->delete("/meetings/{$meeting1->id}")
            ->assertStatus(403);
    }

    public function test_admin_can_manage_any_meeting_in_organization(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
        ]);

        $this->actingAs($admin)
            ->get("/meetings/{$meeting->id}")
            ->assertStatus(200);

        $this->actingAs($admin)
            ->get("/meetings/{$meeting->id}/edit")
            ->assertStatus(200);

        $this->actingAs($admin)
            ->put("/meetings/{$meeting->id}", [
                'title' => 'Diubah Oleh Admin',
                'meeting_date' => '2026-10-23',
                'style' => 'lengkap',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'id' => $meeting->id,
            'title' => 'Diubah Oleh Admin',
        ]);
    }

    public function test_pimpinan_can_view_meetings_list_and_details_but_cannot_modify(): void
    {
        $org = Organization::factory()->create();
        $pimpinan = User::factory()->pimpinan()->create(['organization_id' => $org->id]);
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'title' => 'Rapat Strategis',
        ]);

        // Pimpinan can see list
        $this->actingAs($pimpinan)
            ->get('/meetings')
            ->assertStatus(200)
            ->assertSee('Rapat Strategis');

        // Pimpinan can view details
        $this->actingAs($pimpinan)
            ->get("/meetings/{$meeting->id}")
            ->assertStatus(200);

        // Pimpinan cannot edit, update, or delete
        $this->actingAs($pimpinan)
            ->get("/meetings/{$meeting->id}/edit")
            ->assertStatus(403);

        $this->actingAs($pimpinan)
            ->put("/meetings/{$meeting->id}", [
                'title' => 'Coba Ubah',
                'meeting_date' => '2026-10-23',
                'style' => 'ringkas',
            ])
            ->assertStatus(403);

        $this->actingAs($pimpinan)
            ->delete("/meetings/{$meeting->id}")
            ->assertStatus(403);
    }
}
