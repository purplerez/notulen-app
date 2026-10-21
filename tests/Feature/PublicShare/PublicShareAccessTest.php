<?php

namespace Tests\Feature\PublicShare;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicShareAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $notulis;

    private Meeting $meeting;

    private Minute $finalMinute;

    private Attendee $attendeeHadir;

    private Attendee $attendeeUndangan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->notulis = User::factory()->notulis()->create(['organization_id' => $this->org->id]);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->notulis->id,
            'title' => 'Rapat Strategis Rahasia 2026',
            'status' => 'uploaded',
        ]);

        $this->finalMinute = Minute::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'version' => 1,
            'status' => 'final',
            'content_json' => [
                'ringkasan' => 'Ringkasan keputusan final rapat.',
                'agenda' => ['Pembahasan Roadmap'],
                'pembahasan' => 'Diskusi komprehensif roadmap.',
                'keputusan' => ['Menyetujui roadmap Q4'],
                'tindak_lanjut' => [],
            ],
            'created_by' => $this->notulis->id,
            'finalized_at' => Carbon::now(),
        ]);

        $this->attendeeHadir = Attendee::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Budi Santoso',
            'phone_e164' => '081234567890',
            'status' => 'hadir',
            'checked_in_at' => Carbon::now(),
        ]);

        $this->attendeeUndangan = Attendee::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'name' => 'Dewi Lestari',
            'phone_e164' => '089876543210',
            'status' => 'tanpa_keterangan',
        ]);
    }

    public function test_invalid_token_returns_404(): void
    {
        $response = $this->get('/n/'.Str::random(64));

        $response->assertStatus(404);
    }

    public function test_revoked_link_shows_revoked_status_page(): void
    {
        $token = Str::random(64);
        ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(3),
            'revoked_at' => Carbon::now()->subHour(),
            'created_by' => $this->notulis->id,
        ]);

        $response = $this->get('/n/'.$token);

        $response->assertOk();
        $response->assertViewIs('public_share.status');
        $response->assertSee('Tautan Telah Dicabut');
    }

    public function test_expired_link_shows_expired_status_page(): void
    {
        $token = Str::random(64);
        ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->subMinutes(10),
            'created_by' => $this->notulis->id,
        ]);

        $response = $this->get('/n/'.$token);

        $response->assertOk();
        $response->assertViewIs('public_share.status');
        $response->assertSee('Masa Berlaku Berakhir');
    }

    public function test_locked_link_shows_locked_status_page(): void
    {
        $token = Str::random(64);
        ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(2),
            'failed_attempts' => 5,
            'locked_until' => Carbon::now()->addMinutes(15),
            'created_by' => $this->notulis->id,
        ]);

        $response = $this->get('/n/'.$token);

        $response->assertOk();
        $response->assertViewIs('public_share.status');
        $response->assertSee('Tautan Terkunci Sementara');
    }

    public function test_gate_view_does_not_leak_meeting_title_in_open_graph(): void
    {
        $token = Str::random(64);
        ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->notulis->id,
        ]);

        $response = $this->get('/n/'.$token);

        $response->assertOk();
        $response->assertViewIs('public_share.gate');
        $response->assertSee('<meta property="og:title" content="Notulen Rapat"', false);
        // The real meeting title must NOT be leaked on gate view
        $response->assertDontSee($this->meeting->title);
    }

    public function test_phone_gate_rejects_unregistered_phone_with_uniform_message(): void
    {
        $token = Str::random(64);
        $shareLink = ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->notulis->id,
        ]);

        $response = $this->from('/n/'.$token)->post('/n/'.$token.'/verify', [
            'phone' => '081999999999',
        ]);

        $response->assertRedirect('/n/'.$token);
        $response->assertSessionHasErrors('gate');
        $this->assertEquals(
            config('sharing.uniform_error_message'),
            session('errors')->first('gate')
        );

        $shareLink->refresh();
        $this->assertEquals(1, $shareLink->failed_attempts);
    }

    public function test_phone_gate_rejects_absent_attendee_when_scope_is_hadir(): void
    {
        $token = Str::random(64);
        ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->notulis->id,
        ]);

        $response = $this->from('/n/'.$token)->post('/n/'.$token.'/verify', [
            'phone' => $this->attendeeUndangan->phone_e164,
        ]);

        $response->assertRedirect('/n/'.$token);
        $response->assertSessionHasErrors('gate');
        $this->assertEquals(
            config('sharing.uniform_error_message'),
            session('errors')->first('gate')
        );
    }

    public function test_phone_gate_accepts_present_attendee_and_logs_audit(): void
    {
        $token = Str::random(64);
        $shareLink = ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->notulis->id,
        ]);

        $response = $this->post('/n/'.$token.'/verify', [
            'phone' => '0812-3456-7890', // formatted phone should normalize correctly
        ]);

        $response->assertRedirect('/n/'.$token);
        $response->assertSessionMissing('errors');

        // Access view with session pass
        $viewResponse = $this->get('/n/'.$token);
        $viewResponse->assertOk();
        $viewResponse->assertViewIs('public_share.show');
        $viewResponse->assertSee($this->meeting->title);
        $viewResponse->assertSee('Ringkasan keputusan final rapat.');

        // Verify zero audio and raw transcripts in the HTML
        $viewResponse->assertDontSee('<audio', false);
        $viewResponse->assertDontSee('audio_play');
        $viewResponse->assertDontSee('Transkrip otomatis, belum diperiksa');

        // Audit log link_open should be recorded
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'link_open',
            'actor_attendee_id' => $this->attendeeHadir->id,
        ]);
    }

    public function test_phone_gate_accepts_all_invited_attendees_when_scope_is_semua_undangan(): void
    {
        $token = Str::random(64);
        ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'phone',
            'recipients_scope' => 'semua_undangan',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->notulis->id,
        ]);

        $response = $this->post('/n/'.$token.'/verify', [
            'phone' => $this->attendeeUndangan->phone_e164,
        ]);

        $response->assertRedirect('/n/'.$token);
        $response->assertSessionMissing('errors');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'link_open',
            'actor_attendee_id' => $this->attendeeUndangan->id,
        ]);
    }

    public function test_code_gate_rejects_invalid_pin_and_accepts_valid_pin(): void
    {
        $token = Str::random(64);
        $pin = '654321';
        $shareLink = ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'code',
            'code_hash' => Hash::make($pin),
            'recipients_scope' => 'semua_undangan',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->notulis->id,
        ]);

        // Wrong PIN
        $wrongResponse = $this->from('/n/'.$token)->post('/n/'.$token.'/verify', [
            'code' => '111111',
        ]);
        $wrongResponse->assertRedirect('/n/'.$token);
        $wrongResponse->assertSessionHasErrors('gate');

        // Correct PIN
        $correctResponse = $this->post('/n/'.$token.'/verify', [
            'code' => '654321',
        ]);
        $correctResponse->assertRedirect('/n/'.$token);
        $correctResponse->assertSessionMissing('errors');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'link_open',
            'actor_attendee_id' => null,
        ]);
    }

    public function test_brute_force_lockout_after_max_failed_attempts(): void
    {
        $token = Str::random(64);
        $shareLink = ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'code',
            'code_hash' => Hash::make('123456'),
            'recipients_scope' => 'semua_undangan',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->notulis->id,
        ]);

        // 5 consecutive wrong attempts
        for ($i = 1; $i <= 5; $i++) {
            $this->from('/n/'.$token)->post('/n/'.$token.'/verify', ['code' => '000000']);
        }

        $shareLink->refresh();
        $this->assertEquals(5, $shareLink->failed_attempts);
        $this->assertNotNull($shareLink->locked_until);
        $this->assertTrue($shareLink->locked_until->isFuture());

        // Now visiting /n/{token} immediately shows locked status page
        $response = $this->get('/n/'.$token);
        $response->assertOk();
        $response->assertViewIs('public_share.status');
        $response->assertSee('Tautan Terkunci Sementara');
    }

    public function test_pdf_export_is_blocked_without_verified_session(): void
    {
        $token = Str::random(64);
        ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->notulis->id,
        ]);

        // Try downloading PDF without gate pass
        $response = $this->get('/n/'.$token.'/pdf');
        $response->assertForbidden();
    }

    public function test_pdf_export_succeeds_with_verified_session(): void
    {
        $token = Str::random(64);
        ShareLink::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'token_hash' => hash('sha256', $token),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
            'expires_at' => Carbon::now()->addDays(3),
            'created_by' => $this->notulis->id,
        ]);

        // Pass gate
        $this->post('/n/'.$token.'/verify', [
            'phone' => $this->attendeeHadir->phone_e164,
        ]);

        $response = $this->get('/n/'.$token.'/pdf');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'link_pdf_download',
        ]);
    }
}
