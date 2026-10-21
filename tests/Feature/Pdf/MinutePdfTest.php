<?php

namespace Tests\Feature\Pdf;

use App\Models\ActionItem;
use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\OrganizationSignatory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MinutePdfTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;

    protected Organization $orgB;

    protected User $creatorNotulis;

    protected User $admin;

    protected User $pimpinan;

    protected User $userOrgB;

    protected Meeting $meeting;

    protected Minute $finalMinute;

    protected Minute $draftMinute;

    protected OrganizationSignatory $signatory1;

    protected OrganizationSignatory $signatory2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->orgA = Organization::factory()->create([
            'name' => 'SD Negeri 01 Pagi',
            'address_text' => 'Jl. Merdeka Barat No. 5, Jakarta Pusat',
            'kop_path' => null,
        ]);

        $this->orgB = Organization::factory()->create([
            'name' => 'SMP Swasta Cendekia',
        ]);

        $this->creatorNotulis = User::factory()->notulis()->create(['organization_id' => $this->orgA->id]);
        $this->admin = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);
        $this->pimpinan = User::factory()->pimpinan()->create(['organization_id' => $this->orgA->id]);

        $this->userOrgB = User::factory()->notulis()->create(['organization_id' => $this->orgB->id]);

        // Meeting in Org A
        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->creatorNotulis->id,
            'title' => 'Rapat Pleno Kurikulum Merdeka',
            'meeting_date' => '2026-10-15',
            'start_time' => '09:00:00',
            'location' => 'Aula Utama Gedung B',
            'style' => 'lengkap',
            'use_kop' => true,
            'use_signature' => true,
            'show_attendance' => true,
            'show_recorded_notice' => true,
            'use_followup' => true,
            'status' => 'ready',
        ]);

        // Signatories
        $this->signatory1 = OrganizationSignatory::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Drs. H. Bambang Sugiarto, M.Pd.',
            'position' => 'Kepala Sekolah',
            'role_label' => 'Mengetahui,',
            'sort' => 1,
        ]);

        $this->signatory2 = OrganizationSignatory::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Siti Nurhaliza, S.Pd.',
            'position' => 'Ketua Panitia Rapat',
            'role_label' => 'Ketua Rapat,',
            'sort' => 2,
        ]);

        $this->meeting->signatories()->attach($this->signatory1->id, [
            'organization_id' => $this->orgA->id,
            'sort' => 1,
        ]);
        $this->meeting->signatories()->attach($this->signatory2->id, [
            'organization_id' => $this->orgA->id,
            'sort' => 2,
        ]);

        // Attendees
        Attendee::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Ahmad Fauzi',
            'position' => 'Guru Matematika',
            'phone_e164' => '+6281234567801',
            'status' => 'hadir',
            'checked_in_at' => '2026-10-15 08:50:00',
            'method' => 'qr',
        ]);

        Attendee::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Rina Kartika',
            'position' => 'Guru Bahasa Indonesia',
            'phone_e164' => '+6281234567802',
            'status' => 'terlambat',
            'checked_in_at' => '2026-10-15 09:20:00',
            'method' => 'manual',
        ]);

        // Final Minute (Version 1)
        $this->finalMinute = Minute::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'version' => 1,
            'status' => 'final',
            'content_json' => [
                'ringkasan' => 'Rapat membahas penyesuaian kurikulum dan kalender akademik semester ganjil.',
                'agenda' => [
                    'Evaluasi Kurikulum Semester Lalu',
                    'Penyusunan Modul Ajar Baru',
                    'Alokasi Anggaran Praktikum',
                ],
                'pembahasan' => "Pembahasan berlangsung dengan tertib.\nSeluruh perwakilan mata pelajaran menyampaikan masukan modul.",
                'keputusan' => [
                    'Kurikulum Merdeka diterapkan penuh di seluruh jenjang kelas.',
                    'Modul ajar wajib dikumpulkan selambatnya 30 Oktober 2026.',
                ],
            ],
            'created_by' => $this->creatorNotulis->id,
            'finalized_at' => now()->subDay(),
        ]);

        ActionItem::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'minute_id' => $this->finalMinute->id,
            'description' => 'Mengumpulkan draf modul ajar per rumpun mata pelajaran',
            'assignee_name' => 'Ahmad Fauzi',
            'due_date' => '2026-10-30',
            'status' => 'in_progress',
        ]);

        // Draft Minute (Version 2 - Revision in progress)
        $this->draftMinute = Minute::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meeting->id,
            'version' => 2,
            'status' => 'draft',
            'content_json' => [
                'ringkasan' => 'Draf revisi notulen kurikulum.',
            ],
            'created_by' => $this->creatorNotulis->id,
            'revision_reason' => 'Perbaikan butir keputusan 2',
        ]);
    }

    public function test_final_minute_pdf_renders_successfully_with_all_toggles_enabled(): void
    {
        $response = $this->actingAs($this->pimpinan)
            ->get(route('meetings.minutes.version.pdf', [
                'meeting' => $this->meeting,
                'minute' => $this->finalMinute,
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // PDF starts with %PDF- header magic bytes
        $content = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertGreaterThan(1000, strlen($content));
    }

    public function test_final_minute_pdf_renders_with_all_toggles_disabled(): void
    {
        $this->meeting->update([
            'use_kop' => false,
            'use_signature' => false,
            'show_attendance' => false,
            'show_recorded_notice' => false,
            'use_followup' => false,
            'style' => 'ringkas',
        ]);

        $response = $this->actingAs($this->creatorNotulis)
            ->get(route('meetings.minutes.version.pdf', [
                'meeting' => $this->meeting,
                'minute' => $this->finalMinute,
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_minute_pdf_embeds_kop_image_from_private_storage(): void
    {
        $kopPath = "orgs/{$this->orgA->id}/kop/kop_official.png";
        // Create 1x1 png image
        $pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        Storage::disk('local')->put($kopPath, base64_decode($pngBase64));

        $this->orgA->update(['kop_path' => $kopPath]);

        $response = $this->actingAs($this->admin)
            ->get(route('meetings.minutes.version.pdf', [
                'meeting' => $this->meeting,
                'minute' => $this->finalMinute,
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    public function test_pimpinan_admin_and_creator_notulis_can_download_final_minute_pdf(): void
    {
        // 1. Pimpinan can access
        $this->actingAs($this->pimpinan)
            ->get(route('meetings.minutes.version.pdf', ['meeting' => $this->meeting, 'minute' => $this->finalMinute]))
            ->assertOk();

        // 2. Admin can access
        $this->actingAs($this->admin)
            ->get(route('meetings.minutes.version.pdf', ['meeting' => $this->meeting, 'minute' => $this->finalMinute]))
            ->assertOk();

        // 3. Creator Notulis can access
        $this->actingAs($this->creatorNotulis)
            ->get(route('meetings.minutes.version.pdf', ['meeting' => $this->meeting, 'minute' => $this->finalMinute]))
            ->assertOk();
    }

    public function test_pimpinan_is_strictly_forbidden_from_downloading_draft_minute_pdf(): void
    {
        // Creator Notulis can view draft PDF
        $this->actingAs($this->creatorNotulis)
            ->get(route('meetings.minutes.version.pdf', ['meeting' => $this->meeting, 'minute' => $this->draftMinute]))
            ->assertOk();

        // Pimpinan MUST BE FORBIDDEN from viewing draft PDF
        $response = $this->actingAs($this->pimpinan)
            ->get(route('meetings.minutes.version.pdf', ['meeting' => $this->meeting, 'minute' => $this->draftMinute]));

        $response->assertForbidden();
    }

    public function test_cross_tenant_minute_pdf_is_strictly_rejected(): void
    {
        $response = $this->actingAs($this->userOrgB)
            ->get(route('meetings.minutes.version.pdf', ['meeting' => $this->meeting, 'minute' => $this->finalMinute]));

        // Global scope / policy blocks cross-tenant access
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_minute_pdf_export_is_logged_in_audit_logs(): void
    {
        $response = $this->actingAs($this->pimpinan)
            ->get(route('meetings.minutes.version.pdf', ['meeting' => $this->meeting, 'minute' => $this->finalMinute]));

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->orgA->id,
            'action' => 'minute_pdf_export',
            'meeting_id' => $this->meeting->id,
            'actor_user_id' => $this->pimpinan->id,
        ]);
    }

    public function test_download_parameter_sets_attachment_disposition(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('meetings.minutes.version.pdf', [
                'meeting' => $this->meeting,
                'minute' => $this->finalMinute,
                'download' => '1',
            ]));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('notulen-', $response->headers->get('Content-Disposition'));
    }
}
