<?php

namespace Tests\Feature\Pdf;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\OrganizationSignatory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceRecapPdfTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;

    protected Organization $orgB;

    protected User $creatorNotulis;

    protected User $admin;

    protected User $pimpinan;

    protected User $userOrgB;

    protected Meeting $meeting;

    protected OrganizationSignatory $signatory;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->orgA = Organization::factory()->create([
            'name' => 'SMP Negeri 10 Surabaya',
            'address_text' => 'Jl. Pahlawan No. 12, Surabaya',
            'kop_path' => null,
        ]);

        $this->orgB = Organization::factory()->create([
            'name' => 'SMA Swasta Nusantara',
        ]);

        $this->creatorNotulis = User::factory()->notulis()->create(['organization_id' => $this->orgA->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);
        $this->pimpinan = User::factory()->pimpinan()->create(['organization_id' => $this->orgA->id]);

        $this->userOrgB = User::factory()->notulis()->create(['organization_id' => $this->orgB->id]);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->creatorNotulis->id,
            'title' => 'Rapat Evaluasi Kinerja Guru Triwulan III',
            'meeting_date' => '2026-10-18',
            'start_time' => '13:00:00',
            'location' => 'Ruang Guru Utama',
            'use_kop' => true,
            'use_signature' => true,
            'absensi_closed_at' => '2026-10-18 14:00:00',
        ]);

        $this->signatory = OrganizationSignatory::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Dra. Endang Purwanti, M.Si.',
            'position' => 'Kepala Sekolah',
            'role_label' => 'Mengetahui,',
            'sort' => 1,
        ]);

        $this->meeting->signatories()->attach($this->signatory->id, [
            'organization_id' => $this->orgA->id,
            'sort' => 1,
        ]);

        // Create attendees with various attendance statuses
        Attendee::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Budi Santoso',
            'position' => 'Guru IPA',
            'phone_e164' => '+6281111111101',
            'status' => 'hadir',
            'checked_in_at' => '2026-10-18 12:55:00',
            'method' => 'qr',
        ]);

        Attendee::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Dewi Lestari',
            'position' => 'Guru Bahasa Inggris',
            'phone_e164' => '+6281111111102',
            'status' => 'terlambat',
            'checked_in_at' => '2026-10-18 13:20:00',
            'method' => 'manual',
        ]);

        Attendee::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Hendra Setiawan',
            'position' => 'Guru Olahraga',
            'phone_e164' => '+6281111111103',
            'status' => 'izin',
            'note' => 'Menghadiri MGMP Kota',
        ]);

        Attendee::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Siti Rahmawati',
            'position' => 'Guru Seni Budaya',
            'phone_e164' => '+6281111111104',
            'status' => 'sakit',
            'note' => 'Surat dokter terlampir',
        ]);

        Attendee::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Agus Priyono',
            'position' => 'Guru BK',
            'phone_e164' => '+6281111111105',
            'status' => 'tanpa_keterangan',
        ]);
    }

    public function test_attendance_recap_pdf_renders_successfully(): void
    {
        $response = $this->actingAs($this->pimpinan)
            ->get(route('meetings.attendance.pdf', $this->meeting));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }

    public function test_attendance_recap_pdf_embeds_kop_image_from_private_storage(): void
    {
        $kopPath = "orgs/{$this->orgA->id}/kop/kop_smp10.png";
        $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        Storage::disk('local')->put($kopPath, base64_decode($pngBase64));

        $this->orgA->update(['kop_path' => $kopPath]);

        $response = $this->actingAs($this->admin)
            ->get(route('meetings.attendance.pdf', $this->meeting));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_attendance_recap_pdf_renders_with_toggles_disabled(): void
    {
        $this->meeting->update([
            'use_kop' => false,
            'use_signature' => false,
        ]);

        $response = $this->actingAs($this->creatorNotulis)
            ->get(route('meetings.attendance.pdf', $this->meeting));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_all_organization_roles_can_access_attendance_recap_pdf(): void
    {
        // 1. Pimpinan can access
        $this->actingAs($this->pimpinan)
            ->get(route('meetings.attendance.pdf', $this->meeting))
            ->assertOk();

        // 2. Admin can access
        $this->actingAs($this->admin)
            ->get(route('meetings.attendance.pdf', $this->meeting))
            ->assertOk();

        // 3. Creator Notulis can access
        $this->actingAs($this->creatorNotulis)
            ->get(route('meetings.attendance.pdf', $this->meeting))
            ->assertOk();
    }

    public function test_cross_tenant_attendance_recap_pdf_is_strictly_rejected(): void
    {
        $response = $this->actingAs($this->userOrgB)
            ->get(route('meetings.attendance.pdf', $this->meeting));

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_attendance_pdf_export_is_logged_in_audit_logs(): void
    {
        $response = $this->actingAs($this->pimpinan)
            ->get(route('meetings.attendance.pdf', $this->meeting));

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->orgA->id,
            'action' => 'attendance_pdf_export',
            'meeting_id' => $this->meeting->id,
            'actor_user_id' => $this->pimpinan->id,
        ]);
    }

    public function test_download_parameter_sets_attachment_disposition_on_attendance_recap(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('meetings.attendance.pdf', [
                'meeting' => $this->meeting,
                'download' => '1',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('rekap-absensi-', $response->headers->get('Content-Disposition'));
    }
}
