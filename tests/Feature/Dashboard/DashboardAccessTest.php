<?php

namespace Tests\Feature\Dashboard;

use App\Models\Attendee;
use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\TranscriptSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $pimpinanA;

    private User $adminA;

    private User $notulisA;

    private User $notulisA2;

    private User $pimpinanB;

    private Meeting $meetingWithFinalMinutes;

    private Meeting $meetingWithDraftMinutes;

    private Meeting $meetingB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->pimpinanA = User::factory()->pimpinan()->create(['organization_id' => $this->orgA->id]);
        $this->adminA = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);
        $this->notulisA = User::factory()->notulis()->create(['organization_id' => $this->orgA->id]);
        $this->notulisA2 = User::factory()->notulis()->create(['organization_id' => $this->orgA->id]);

        $this->pimpinanB = User::factory()->pimpinan()->create(['organization_id' => $this->orgB->id]);

        // Meeting 1: Ready with Audio, Transcripts, and Final Minutes
        $this->meetingWithFinalMinutes = Meeting::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->notulisA->id,
            'title' => 'Rapat Pleno Akreditasi',
            'meeting_date' => '2026-10-15',
            'start_time' => '09:00',
            'status' => 'ready',
            'audio_saved_at' => now()->subDays(5),
            'audio_expires_at' => now()->addDays(25),
            'audio_deleted_at' => null,
            'merged_audio_path' => "orgs/{$this->orgA->id}/meetings/1/merged.m4a",
            'share_expires_at' => now()->addDays(7),
        ]);
        Storage::disk('local')->put($this->meetingWithFinalMinutes->merged_audio_path, 'AUDIO_DATA_FOR_TEST');

        // Attendees for Meeting 1: 1 present out of 2 invited
        Attendee::factory()->create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meetingWithFinalMinutes->id,
            'name' => 'Peserta Satu',
            'status' => 'hadir',
            'checked_in_at' => now(),
        ]);
        Attendee::factory()->create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meetingWithFinalMinutes->id,
            'name' => 'Peserta Dua',
            'status' => null,
            'checked_in_at' => null,
        ]);

        // Audio & Transcript segments for Meeting 1
        $audioSeg = AudioSegment::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meetingWithFinalMinutes->id,
            'seq' => 1,
            'path' => "orgs/{$this->orgA->id}/meetings/1/seg-1.webm",
            'mime' => 'audio/webm',
            'size_bytes' => 1024,
            'duration_sec' => 60.0,
            'checksum' => hash('sha256', 'dummy'),
            'status' => 'ready',
        ]);
        TranscriptSegment::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meetingWithFinalMinutes->id,
            'audio_segment_id' => $audioSeg->id,
            'start_sec' => 0.0,
            'end_sec' => 10.0,
            'text' => 'Pembahasan akreditasi resmi dibuka.',
        ]);

        // Final Minutes for Meeting 1
        Minute::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meetingWithFinalMinutes->id,
            'version' => 1,
            'status' => 'final',
            'content_json' => [
                'ringkasan' => 'Ringkasan resmi akreditasi sekolah.',
                'keputusan' => ['Target predikat A tercapai.'],
            ],
            'created_by' => $this->notulisA->id,
            'finalized_at' => now()->subDay(),
        ]);

        // Meeting 2: Finished recording, transcripts ready, but minutes still in DRAFT
        $this->meetingWithDraftMinutes = Meeting::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->notulisA2->id,
            'title' => 'Rapat Evaluasi Anggaran Tahunan',
            'meeting_date' => '2026-10-18',
            'start_time' => '13:30',
            'status' => 'ready',
            'audio_saved_at' => now()->subDay(),
            'audio_expires_at' => now()->addDays(29),
            'audio_deleted_at' => null,
            'merged_audio_path' => "orgs/{$this->orgA->id}/meetings/2/merged.m4a",
        ]);
        Storage::disk('local')->put($this->meetingWithDraftMinutes->merged_audio_path, 'AUDIO_DATA_FOR_TEST_2');

        Minute::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meetingWithDraftMinutes->id,
            'version' => 1,
            'status' => 'draft',
            'content_json' => [
                'ringkasan' => 'DRAF RAHASIA: Belum boleh dibaca oleh pimpinan maupun admin!',
            ],
            'created_by' => $this->notulisA2->id,
        ]);

        // Meeting in Organization B
        $this->meetingB = Meeting::factory()->create([
            'organization_id' => $this->orgB->id,
            'title' => 'Rapat Internal Yayasan B',
            'meeting_date' => '2026-10-19',
        ]);
    }

    public function test_pimpinan_sees_all_organization_meetings_with_required_columns_and_links(): void
    {
        $response = $this->actingAs($this->pimpinanA)->get(route('dashboard'));

        $response->assertOk();

        // 1. Both meetings of Org A are visible
        $response->assertSee('Rapat Pleno Akreditasi');
        $response->assertSee('Rapat Evaluasi Anggaran Tahunan');

        // 2. Notulis name visible
        $response->assertSee($this->notulisA->name);
        $response->assertSee($this->notulisA2->name);

        // 3. Attendance counter visible: 1 / 2 Hadir
        $response->assertSee('1 / 2 Hadir');

        // 4. Minutes status & Acknowledgement status
        $response->assertSee('Notulen Final (v1)');
        $response->assertSee('Belum Diketahui'); // Not yet acknowledged by this pimpinan

        // 5. Audio indicator
        $response->assertSee('Audio tersedia sampai');

        // 6. Material links for Pimpinan:
        // - Final minutes link
        $response->assertSee(route('meetings.minutes.show', $this->meetingWithFinalMinutes));
        // - Raw transcript link
        $response->assertSee(route('meetings.transcript.show', $this->meetingWithFinalMinutes));
        // - Audio player exists
        $response->assertSee('Pemutar Audio Streaming');
        $response->assertSee('controlsList="nodownload"', false);
    }

    public function test_meeting_without_final_minutes_shows_status_without_leaking_draft_contents_to_pimpinan(): void
    {
        $response = $this->actingAs($this->pimpinanA)->get(route('dashboard'));

        $response->assertOk();

        // Meeting is listed with draft badge
        $response->assertSee('Rapat Evaluasi Anggaran Tahunan');
        $response->assertSee('Draf Notulen');

        // Draft text is strictly NOT leaked!
        $response->assertDontSee('DRAF RAHASIA: Belum boleh dibaca');

        // Pimpinan CAN open raw transcript and audio since the meeting is ready/finished
        $response->assertSee(route('meetings.transcript.show', $this->meetingWithDraftMinutes));
    }

    public function test_admin_dashboard_shows_meetings_but_strictly_omits_audio_and_transcript_links(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('dashboard'));

        $response->assertOk();

        // Admin sees the meetings and final minutes link
        $response->assertSee('Rapat Pleno Akreditasi');
        $response->assertSee(route('meetings.minutes.show', $this->meetingWithFinalMinutes));

        // Admin MUST NOT see audio player or signed audio stream URL
        $response->assertDontSee('Pemutar Audio Streaming');
        $response->assertDontSee('meetings.audio.stream');
        $response->assertDontSee('controlsList="nodownload"', false);

        // Admin MUST NOT see raw transcript links
        $response->assertDontSee(route('meetings.transcript.show', $this->meetingWithFinalMinutes));
        $response->assertDontSee(route('meetings.transcript.show', $this->meetingWithDraftMinutes));

        // Admin MUST NOT see draft minute content
        $response->assertDontSee('DRAF RAHASIA: Belum boleh dibaca');

        // Direct HTTP requests to raw materials by Admin must return 403 Forbidden!
        $signedAudioUrl = URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $this->meetingWithFinalMinutes->id]);
        $this->actingAs($this->adminA)->get($signedAudioUrl)->assertForbidden();

        $this->actingAs($this->adminA)
            ->get(route('meetings.transcript.show', $this->meetingWithFinalMinutes))
            ->assertForbidden();
    }

    public function test_dashboard_search_by_title_filters_correctly(): void
    {
        $response = $this->actingAs($this->pimpinanA)->get(route('dashboard', ['q' => 'Akreditasi']));

        $response->assertOk();
        $response->assertSee('Rapat Pleno Akreditasi');
        $response->assertDontSee('Rapat Evaluasi Anggaran Tahunan');
    }

    public function test_dashboard_filter_by_month_and_status(): void
    {
        // Filter by month
        $responseMonth = $this->actingAs($this->pimpinanA)->get(route('dashboard', ['month' => '2026-10']));
        $responseMonth->assertOk();
        $responseMonth->assertSee('Rapat Pleno Akreditasi');

        // Filter by final_minutes
        $responseFinal = $this->actingAs($this->pimpinanA)->get(route('dashboard', ['status' => 'final_minutes']));
        $responseFinal->assertOk();
        $responseFinal->assertSee('Rapat Pleno Akreditasi');
        $responseFinal->assertDontSee('Rapat Evaluasi Anggaran Tahunan');

        // Filter by draft_minutes
        $responseDraft = $this->actingAs($this->pimpinanA)->get(route('dashboard', ['status' => 'draft_minutes']));
        $responseDraft->assertOk();
        $responseDraft->assertSee('Rapat Evaluasi Anggaran Tahunan');
        $responseDraft->assertDontSee('Rapat Pleno Akreditasi');
    }

    public function test_notulis_dashboard_only_lists_meetings_created_by_that_notulis(): void
    {
        // Notulis A created Meeting 1
        $responseNotulis1 = $this->actingAs($this->notulisA)->get(route('dashboard'));
        $responseNotulis1->assertOk();
        $responseNotulis1->assertSee('Rapat Pleno Akreditasi');
        $responseNotulis1->assertDontSee('Rapat Evaluasi Anggaran Tahunan');

        // Notulis A2 created Meeting 2
        $responseNotulis2 = $this->actingAs($this->notulisA2)->get(route('dashboard'));
        $responseNotulis2->assertOk();
        $responseNotulis2->assertSee('Rapat Evaluasi Anggaran Tahunan');
        $responseNotulis2->assertDontSee('Rapat Pleno Akreditasi');
    }

    public function test_dashboard_strictly_respects_tenant_isolation(): void
    {
        $responseA = $this->actingAs($this->pimpinanA)->get(route('dashboard'));
        $responseA->assertOk();
        $responseA->assertDontSee('Rapat Internal Yayasan B');

        $responseB = $this->actingAs($this->pimpinanB)->get(route('dashboard'));
        $responseB->assertOk();
        $responseB->assertSee('Rapat Internal Yayasan B');
        $responseB->assertDontSee('Rapat Pleno Akreditasi');
    }
}
