<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualAttendanceAndClosingTest extends TestCase
{
    use RefreshDatabase;

    public function test_host_can_manually_mark_attendee(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        $attendee = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'status' => null,
        ]);

        $response = $this->actingAs($notulis)->post("/meetings/{$meeting->id}/attendees/{$attendee->id}/manual", [
            'status' => 'hadir',
            'note' => 'Peserta tidak membawa HP',
        ]);

        $response->assertRedirect();

        $attendee->refresh();
        $this->assertEquals('hadir', $attendee->status);
        $this->assertEquals('manual', $attendee->method);
        $this->assertEquals('Peserta tidak membawa HP', $attendee->note);
        $this->assertNotNull($attendee->checked_in_at);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'action' => 'attendee_manual_checkin',
        ]);
    }

    public function test_host_can_approve_and_reject_pending_guests(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        $guest1 = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'is_guest' => true,
            'guest_status' => 'pending',
            'status' => null,
        ]);

        $guest2 = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'is_guest' => true,
            'guest_status' => 'pending',
            'status' => null,
        ]);

        // Approve guest 1
        $this->actingAs($notulis)->post("/meetings/{$meeting->id}/attendees/{$guest1->id}/approve-guest", [
            'status' => 'hadir',
        ]);

        $guest1->refresh();
        $this->assertEquals('approved', $guest1->guest_status);
        $this->assertEquals('hadir', $guest1->status);

        // Reject guest 2
        $this->actingAs($notulis)->post("/meetings/{$meeting->id}/attendees/{$guest2->id}/reject-guest");

        $guest2->refresh();
        $this->assertEquals('rejected', $guest2->guest_status);
        $this->assertNull($guest2->status);
    }

    public function test_closing_attendance_locks_and_assigns_tanpa_keterangan_to_unattended(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $admin->id]);

        $presentAttendee = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'status' => 'hadir',
        ]);

        $unattended1 = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'status' => null,
        ]);

        $unattended2 = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'status' => null,
        ]);

        $response = $this->actingAs($admin)->post("/meetings/{$meeting->id}/close-attendance", [
            'unattended' => [
                $unattended1->id => ['status' => 'izin', 'note' => 'Ada surat izin dinas'],
            ],
        ]);

        $response->assertRedirect("/meetings/{$meeting->id}/recap");

        $meeting->refresh();
        $this->assertNotNull($meeting->absensi_closed_at);

        $presentAttendee->refresh();
        $unattended1->refresh();
        $unattended2->refresh();

        $this->assertEquals('hadir', $presentAttendee->status);
        $this->assertEquals('izin', $unattended1->status);
        $this->assertEquals('Ada surat izin dinas', $unattended1->note);
        $this->assertEquals('tanpa_keterangan', $unattended2->status); // Default fallback

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'action' => 'attendance_closed',
        ]);
    }

    public function test_updating_status_after_closing_requires_reason(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $admin->id,
            'absensi_closed_at' => now(), // Closed
        ]);

        $attendee = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'status' => 'tanpa_keterangan',
        ]);

        // Attempt update WITHOUT reason -> fails validation
        $response = $this->actingAs($admin)->put("/meetings/{$meeting->id}/attendees/{$attendee->id}/status", [
            'status' => 'izin',
            'reason' => '',
        ]);

        $response->assertSessionHasErrors('reason');

        // Update WITH reason -> succeeds and logs audit
        $responseSuccess = $this->actingAs($admin)->put("/meetings/{$meeting->id}/attendees/{$attendee->id}/status", [
            'status' => 'izin',
            'reason' => 'Surat dokter baru diserahkan keluarga',
        ]);

        $responseSuccess->assertRedirect();

        $attendee->refresh();
        $this->assertEquals('izin', $attendee->status);
        $this->assertEquals('Surat dokter baru diserahkan keluarga', $attendee->note);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'action' => 'attendance_corrected',
        ]);
    }
}
