<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceRecapAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_screen_renders_with_qr_and_stats(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        $this->actingAs($notulis)
            ->get("/meetings/{$meeting->id}/attendance")
            ->assertStatus(200)
            ->assertSee('Layar QR Presensi');
    }

    public function test_qr_data_polling_endpoint_returns_json(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        $attendee = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'name' => 'Peserta Aktif',
            'status' => 'hadir',
            'checked_in_at' => now(),
        ]);

        $response = $this->actingAs($notulis)->getJson("/meetings/{$meeting->id}/attendance/qr-data");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'code',
                'url',
                'seconds_remaining',
                'is_closed',
                'total_invited',
                'total_present',
                'pending_guests_count',
                'attendees',
            ])
            ->assertJsonPath('total_present', 1);
    }

    public function test_recap_page_renders_with_attendees_and_status_summary(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $pimpinan = User::factory()->pimpinan()->create(['organization_id' => $org->id]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $admin->id,
            'title' => 'Rapat Rencana Strategis',
        ]);

        Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'name' => 'Guru Hadir',
            'status' => 'hadir',
        ]);

        // Admin can view recap
        $this->actingAs($admin)
            ->get("/meetings/{$meeting->id}/recap")
            ->assertStatus(200)
            ->assertSee('Rekapitulasi Presensi Berita Acara')
            ->assertSee('Guru Hadir');

        // Pimpinan can view recap
        $this->actingAs($pimpinan)
            ->get("/meetings/{$meeting->id}/recap")
            ->assertStatus(200)
            ->assertSee('Rapat Rencana Strategis');
    }

    public function test_tenant_isolation_prevents_cross_organization_attendance_access(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $notulisA = User::factory()->notulis()->create(['organization_id' => $orgA->id]);
        $notulisB = User::factory()->notulis()->create(['organization_id' => $orgB->id]);

        $meetingA = Meeting::factory()->create([
            'organization_id' => $orgA->id,
            'created_by' => $notulisA->id,
        ]);

        // Notulis B cannot access host screen of Meeting A
        $this->actingAs($notulisB)
            ->get("/meetings/{$meetingA->id}/attendance")
            ->assertStatus(404);

        // Notulis B cannot call QR data polling of Meeting A
        $this->actingAs($notulisB)
            ->getJson("/meetings/{$meetingA->id}/attendance/qr-data")
            ->assertStatus(404);

        // Notulis B cannot close attendance of Meeting A
        $this->actingAs($notulisB)
            ->post("/meetings/{$meetingA->id}/close-attendance")
            ->assertStatus(404);

        // Notulis B cannot view recap of Meeting A
        $this->actingAs($notulisB)
            ->get("/meetings/{$meetingA->id}/recap")
            ->assertStatus(404);
    }
}
