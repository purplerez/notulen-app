<?php

namespace Tests\Feature\Platform;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\SuperAdmin;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformPrivacyAndAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_cannot_access_tenant_content_routes(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_enabled' => true,
        ]);

        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'title' => 'Rapat Rahasia Super Terbatas',
        ]);

        Minute::create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'version' => 1,
            'status' => 'final',
            'content_json' => ['ringkasan' => 'Ringkasan Rahasia'],
            'created_by' => $notulis->id,
            'finalized_at' => now(),
        ]);

        $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true]);

        // Attempt accessing meeting detail (web auth required)
        $r1 = $this->get(route('meetings.show', $meeting));
        $this->assertTrue(in_array($r1->status(), [302, 403]));

        // Attempt accessing transcript
        $r2 = $this->get(route('meetings.transcript.show', $meeting));
        $this->assertTrue(in_array($r2->status(), [302, 403]));

        // Attempt accessing audio stream route
        $r3 = $this->get(route('meetings.audio.stream', $meeting));
        $this->assertTrue(in_array($r3->status(), [302, 403]));

        // Attempt accessing minutes pdf
        $r4 = $this->get(route('meetings.minutes.pdf', $meeting));
        $this->assertTrue(in_array($r4->status(), [302, 403]));

        // Attempt accessing organization kop
        $r5 = $this->get(route('organization.kop.show'));
        $this->assertTrue(in_array($r5->status(), [302, 403]));
    }

    public function test_tenant_users_cannot_access_platform_routes(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);
        $pimpinan = User::factory()->pimpinan()->create(['organization_id' => $org->id]);
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        foreach ([$admin, $pimpinan, $notulis] as $user) {
            $response = $this->actingAs($user, 'web')
                ->get(route('platform.dashboard'));

            $response->assertRedirect(route('platform.login'));
        }
    }

    public function test_platform_organization_view_strictly_omits_meeting_titles_and_attendees(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_enabled' => true,
        ]);

        $org = Organization::factory()->create(['name' => 'Dinas Pendidikan Jawa Barat']);
        $notulis = User::factory()->notulis()->create([
            'organization_id' => $org->id,
            'name' => 'Notulis Budi Santoso',
        ]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'title' => 'JUDUL_SANGAT_RAHASIA_DOKUMEN_A',
            'meeting_date' => now()->toDateString(),
        ]);

        Attendee::create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'name' => 'PESERTA_RAHASIA_KADIS',
            'phone_e164' => '+6281234567890',
        ]);

        UsageLog::create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'user_id' => $notulis->id,
            'kind' => 'stt',
            'provider' => 'groq',
            'model' => 'whisper-large-v3-turbo',
            'unit' => 'detik_audio',
            'qty' => 120.0,
            'audio_sec' => 120.0,
            'rate_snapshot' => 0.00005,
            'est_cost_usd' => 0.006,
            'est_cost_idr' => 96.0,
            'fx_rate_used' => 16000.0,
            'est_cost' => 0.006,
            'status' => 'sukses',
            'attempt' => 1,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->get(route('platform.organizations.show', $org));

        $response->assertStatus(200);

        // Super Admin sees organization name, notulis name, and meeting ID
        $response->assertSee('Dinas Pendidikan Jawa Barat');
        $response->assertSee('Notulis Budi Santoso');
        $response->assertSee('#'.$meeting->id);

        // STRICT PRIVACY: Meeting title must NEVER appear in response HTML
        $response->assertDontSee('JUDUL_SANGAT_RAHASIA_DOKUMEN_A');

        // STRICT PRIVACY: Attendee names must NEVER appear in response HTML
        $response->assertDontSee('PESERTA_RAHASIA_KADIS');
    }

    public function test_platform_export_csv_strictly_omits_meeting_titles_and_attendees(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_enabled' => true,
        ]);

        $org = Organization::factory()->create(['name' => 'Badan Kepegawaian', 'slug' => 'bkd']);
        $notulis = User::factory()->notulis()->create([
            'organization_id' => $org->id,
            'name' => 'Notulis Siti',
        ]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'title' => 'JUDUL_RAHASIA_LELANG_JABATAN',
            'meeting_date' => now()->toDateString(),
        ]);

        UsageLog::create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'user_id' => $notulis->id,
            'kind' => 'stt',
            'provider' => 'groq',
            'model' => 'whisper-large-v3-turbo',
            'unit' => 'detik_audio',
            'qty' => 180.0,
            'audio_sec' => 180.0,
            'rate_snapshot' => 0.00005,
            'est_cost_usd' => 0.009,
            'est_cost_idr' => 144.0,
            'fx_rate_used' => 16000.0,
            'est_cost' => 0.009,
            'status' => 'sukses',
            'attempt' => 1,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->get(route('platform.organizations.export_csv', $org));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // CSV Header should have ID Rapat, Tanggal, Nama Notulis, etc.
        $this->assertStringContainsString('ID Rapat', $content);
        $this->assertStringContainsString('Nama Notulis', $content);
        $this->assertStringContainsString((string) $meeting->id, $content);
        $this->assertStringContainsString('Notulis Siti', $content);

        // STRICT PRIVACY: Header and rows must NOT contain meeting title
        $this->assertStringNotContainsString('JUDUL_RAHASIA_LELANG_JABATAN', $content);
        $this->assertStringNotContainsString('Judul Rapat', $content);
    }
}
