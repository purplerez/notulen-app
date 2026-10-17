<?php

namespace Tests\Feature\Audio;

use App\Models\Meeting;
use App\Models\Organization;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AudioStreamTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $admin;

    private User $creatorNotulis;

    private User $otherNotulis;

    private User $pimpinan;

    private Meeting $meeting;

    private string $audioContent;

    private string $relativePath;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->org = Organization::factory()->create();

        $this->admin = User::factory()->create([
            'organization_id' => $this->org->id,
            'role' => 'admin',
        ]);

        $this->creatorNotulis = User::factory()->create([
            'organization_id' => $this->org->id,
            'role' => 'notulis',
        ]);

        $this->otherNotulis = User::factory()->create([
            'organization_id' => $this->org->id,
            'role' => 'notulis',
        ]);

        $this->pimpinan = User::factory()->create([
            'organization_id' => $this->org->id,
            'role' => 'pimpinan',
        ]);

        $this->relativePath = "orgs/{$this->org->id}/meetings/1/merged.m4a";
        $this->audioContent = 'FAKE_AUDIO_DATA_FOR_STREAMING_TESTS_1234567890_ABCDEFGHIJ';
        Storage::disk('local')->put($this->relativePath, $this->audioContent);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->creatorNotulis->id,
            'audio_saved_at' => now()->subDay(),
            'audio_expires_at' => now()->addDays(29),
            'audio_deleted_at' => null,
            'merged_audio_path' => $this->relativePath,
        ]);
    }

    public function test_creator_notulis_can_stream_audio_with_signed_url(): void
    {
        $signedUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $this->meeting->id]);

        $response = $this->actingAs($this->creatorNotulis)->get($signedUrl);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'audio/mp4');
        $response->assertHeader('Accept-Ranges', 'bytes');
        $response->assertHeader('Content-Disposition', 'inline');

        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'actor_user_id' => $this->creatorNotulis->id,
            'action' => 'audio_play',
        ]);
    }

    public function test_pimpinan_can_stream_audio_with_signed_url(): void
    {
        $signedUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $this->meeting->id]);

        $response = $this->actingAs($this->pimpinan)->get($signedUrl);

        $response->assertStatus(200);
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'actor_user_id' => $this->pimpinan->id,
            'action' => 'audio_play',
        ]);
    }

    public function test_admin_is_forbidden_from_streaming_audio(): void
    {
        $signedUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $this->meeting->id]);

        $response = $this->actingAs($this->admin)->get($signedUrl);

        $response->assertStatus(403);
    }

    public function test_super_admin_is_forbidden_from_streaming_audio(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@platform.test',
            'password' => bcrypt('secret123'),
        ]);
        $signedUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $this->meeting->id]);

        $response = $this->actingAs($superAdmin, 'platform')->get($signedUrl);

        // Super admin is rejected with 403 Forbidden
        $response->assertStatus(403);
    }

    public function test_other_notulis_is_forbidden_from_streaming_audio(): void
    {
        $signedUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $this->meeting->id]);

        $response = $this->actingAs($this->otherNotulis)->get($signedUrl);

        $response->assertStatus(403);
    }

    public function test_unsigned_or_expired_signature_is_forbidden(): void
    {
        // Unsigned request
        $unsignedUrl = route('meetings.audio.stream', $this->meeting);
        $this->actingAs($this->creatorNotulis)->get($unsignedUrl)->assertStatus(403);

        // Expired signature
        $expiredUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->subMinute(), ['meeting' => $this->meeting->id]);
        $this->actingAs($this->creatorNotulis)->get($expiredUrl)->assertStatus(403);
    }

    public function test_streaming_forbidden_after_audio_expires(): void
    {
        $this->meeting->update(['audio_expires_at' => now()->subDay()]);
        $signedUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $this->meeting->id]);

        $response = $this->actingAs($this->creatorNotulis)->get($signedUrl);

        $response->assertStatus(403);
    }

    public function test_streaming_forbidden_after_audio_is_deleted(): void
    {
        $this->meeting->update(['audio_deleted_at' => now()]);
        $signedUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $this->meeting->id]);

        $response = $this->actingAs($this->creatorNotulis)->get($signedUrl);

        $response->assertStatus(403);
    }

    public function test_http_range_request_returns_206_partial_content(): void
    {
        $signedUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $this->meeting->id]);

        $totalLength = strlen($this->audioContent);
        $response = $this->actingAs($this->creatorNotulis)->get($signedUrl, [
            'Range' => 'bytes=0-9',
        ]);

        $response->assertStatus(206);
        $response->assertHeader('Content-Range', "bytes 0-9/{$totalLength}");
        $response->assertHeader('Content-Length', '10');
        $this->assertEquals(substr($this->audioContent, 0, 10), $response->streamedContent());
    }

    public function test_audit_log_not_duplicated_on_subsequent_range_requests(): void
    {
        $signedUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $this->meeting->id]);

        // Range request starting > 0 (buffer chunk)
        $this->actingAs($this->creatorNotulis)->get($signedUrl, [
            'Range' => 'bytes=10-19',
        ])->assertStatus(206);

        // Should NOT record audit log for secondary seek/chunk requests
        $this->assertDatabaseMissing('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'audio_play',
        ]);
    }

    public function test_meeting_show_page_renders_audio_player_for_creator_notulis(): void
    {
        $response = $this->actingAs($this->creatorNotulis)->get(route('meetings.show', $this->meeting));

        $response->assertStatus(200);
        $response->assertSee('Pemutar Audio Rekaman Rapat');
        $response->assertSee('controlsList="nodownload"', false);
        $response->assertSee('Audio tersedia sampai');
    }

    public function test_meeting_show_page_does_not_render_audio_player_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('meetings.show', $this->meeting));

        $response->assertStatus(200);
        $response->assertDontSee('Pemutar Audio Rekaman Rapat');
        $response->assertDontSee('controlsList="nodownload"', false);
    }
}
