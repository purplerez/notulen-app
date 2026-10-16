<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\OrgContact;
use App\Models\User;
use App\Support\CheckinCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCheckinTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_check_in_using_valid_qr_code(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'meeting_date' => now()->format('Y-m-d'),
            'start_time' => now()->addHour()->format('H:i'), // In the future, not late
        ]);

        $attendee = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'name' => 'Ahmad Dahlan',
            'phone_e164' => '6281234567890',
            'status' => null,
        ]);

        $code = CheckinCode::generate($meeting->id);

        $response = $this->get("/c/{$meeting->id}?code={$code}");
        $response->assertStatus(200);
        $response->assertSee('Ahmad Dahlan');

        $postResponse = $this->post("/c/{$meeting->id}", [
            'code' => $code,
            'attendee_id' => $attendee->id,
            'phone' => '0812-3456-7890',
        ]);

        $postResponse->assertStatus(200);
        $postResponse->assertSee('Presensi Tercatat');

        $attendee->refresh();
        $this->assertEquals('hadir', $attendee->status);
        $this->assertEquals('qr', $attendee->method);
        $this->assertNotNull($attendee->checked_in_at);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'action' => 'attendee_checkin',
        ]);
    }

    public function test_two_different_participants_can_scan_and_both_be_recorded_present(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'meeting_date' => now()->format('Y-m-d'),
            'start_time' => now()->addHour()->format('H:i'),
        ]);

        $attendee1 = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'name' => 'Peserta Satu',
            'phone_e164' => '6281234567801',
        ]);

        $attendee2 = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'name' => 'Peserta Dua',
            'phone_e164' => '6281234567802',
        ]);

        $code = CheckinCode::generate($meeting->id);

        // First participant scans and checks in
        $res1 = $this->post("/c/{$meeting->id}", [
            'code' => $code,
            'attendee_id' => $attendee1->id,
            'phone' => '0812-3456-7801',
        ]);
        $res1->assertStatus(200);

        // Second participant scans and checks in
        $res2 = $this->post("/c/{$meeting->id}", [
            'code' => $code,
            'attendee_id' => $attendee2->id,
            'phone' => '0812-3456-7802',
        ]);
        $res2->assertStatus(200);

        $attendee1->refresh();
        $attendee2->refresh();

        $this->assertEquals('hadir', $attendee1->status);
        $this->assertEquals('hadir', $attendee2->status);
    }

    public function test_participant_is_marked_terlambat_after_late_threshold(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        // Meeting started 30 minutes ago, late threshold is 15 minutes
        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'meeting_date' => now()->format('Y-m-d'),
            'start_time' => now()->subMinutes(30)->format('H:i'),
            'late_after_minutes' => 15,
        ]);

        $attendee = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'phone_e164' => '6281234567890',
        ]);

        $code = CheckinCode::generate($meeting->id);

        $this->post("/c/{$meeting->id}", [
            'code' => $code,
            'attendee_id' => $attendee->id,
            'phone' => '0812-3456-7890',
        ]);

        $attendee->refresh();
        $this->assertEquals('terlambat', $attendee->status);
        $this->assertEquals('qr', $attendee->method);
    }

    public function test_participant_not_in_invitation_list_is_recorded_as_pending_guest(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
        ]);

        $code = CheckinCode::generate($meeting->id);

        $response = $this->post("/c/{$meeting->id}", [
            'code' => $code,
            'name' => 'Tamu Tak Terduga',
            'position' => 'Wartawan',
            'phone' => '0857-1122-3344',
        ]);

        $response->assertStatus(200);
        $response->assertSee('tamu');

        $this->assertDatabaseHas('attendees', [
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'name' => 'Tamu Tak Terduga',
            'position' => 'Wartawan',
            'phone_e164' => '6285711223344',
            'is_guest' => true,
            'guest_status' => 'pending',
            'status' => null, // Pending approval
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'action' => 'guest_checkin_pending',
        ]);
    }

    public function test_guest_whose_phone_matches_org_contact_is_recognized(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $contact = OrgContact::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Dr. H. Subagyo',
            'position' => 'Pengawas Sekolah',
            'phone_e164' => '6281299887766',
        ]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
        ]);

        $code = CheckinCode::generate($meeting->id);

        // Guest only enters phone number without name
        $this->post("/c/{$meeting->id}", [
            'code' => $code,
            'phone' => '0812-9988-7766',
        ]);

        $this->assertDatabaseHas('attendees', [
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'contact_id' => $contact->id,
            'name' => 'Dr. H. Subagyo',
            'position' => 'Pengawas Sekolah',
            'phone_e164' => '6281299887766',
            'is_guest' => true,
            'guest_status' => 'pending',
        ]);
    }

    public function test_checkin_is_rejected_if_code_is_expired_or_invalid(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        // Code from 2 minutes ago (4 windows older)
        $expiredCode = CheckinCode::generate($meeting->id, time() - 120);

        $response = $this->post("/c/{$meeting->id}", [
            'code' => $expiredCode,
            'name' => 'Test User',
            'phone' => '081234567890',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_checkin_is_rejected_if_attendance_is_closed(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'absensi_closed_at' => now(),
        ]);

        $code = CheckinCode::generate($meeting->id);

        $response = $this->post("/c/{$meeting->id}", [
            'code' => $code,
            'name' => 'Terlambat Banget',
            'phone' => '081234567890',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_displays_recorded_notice_label_when_enabled(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'show_recorded_notice' => true,
        ]);

        $code = CheckinCode::generate($meeting->id);

        $response = $this->get("/c/{$meeting->id}?code={$code}");
        $response->assertSee('Rapat direkam untuk notulensi');
    }
}
