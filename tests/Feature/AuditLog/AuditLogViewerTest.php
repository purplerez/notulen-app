<?php

namespace Tests\Feature\AuditLog;

use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $pimpinanA;

    private User $adminA;

    private User $notulisA1;

    private User $notulisA2;

    private User $pimpinanB;

    private Meeting $meeting1;

    private Meeting $meeting2;

    private Meeting $meetingB;

    private AuditLog $logMeeting1;

    private AuditLog $logMeeting2;

    private AuditLog $logOrgLevel;

    private AuditLog $logOrgB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->pimpinanA = User::factory()->pimpinan()->create(['organization_id' => $this->orgA->id]);
        $this->adminA = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);
        $this->notulisA1 = User::factory()->notulis()->create(['organization_id' => $this->orgA->id]);
        $this->notulisA2 = User::factory()->notulis()->create(['organization_id' => $this->orgA->id]);

        $this->pimpinanB = User::factory()->pimpinan()->create(['organization_id' => $this->orgB->id]);

        $this->meeting1 = Meeting::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->notulisA1->id,
            'title' => 'Rapat Koordinasi Notulis Satu',
            'meeting_date' => '2026-10-10',
        ]);

        $this->meeting2 = Meeting::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->notulisA2->id,
            'title' => 'Rapat Anggaran Notulis Dua',
            'meeting_date' => '2026-10-12',
        ]);

        $this->meetingB = Meeting::factory()->create([
            'organization_id' => $this->orgB->id,
            'title' => 'Rapat Rahasia Organisasi B',
            'meeting_date' => '2026-10-15',
        ]);

        // Create log 1: on meeting 1 (action audio_play by pimpinan)
        $this->actingAs($this->pimpinanA);
        $this->logMeeting1 = Audit::record('audio_play', $this->meeting1->id, ['ip' => '192.168.1.1']);

        // Create log 2: on meeting 2 (action minute_finalize by notulis 2)
        $this->actingAs($this->notulisA2);
        $this->logMeeting2 = Audit::record('minute_finalize', $this->meeting2->id, ['version' => 1]);

        // Create log 3: organization-level log (action user_create by admin)
        $this->actingAs($this->adminA);
        $this->logOrgLevel = Audit::record('user_create', null, ['created_user_role' => 'notulis']);

        // Create log 4: organization B log
        $this->actingAs($this->pimpinanB);
        $this->logOrgB = Audit::record('audio_play', $this->meetingB->id, ['ip' => '10.0.0.1']);
    }

    public function test_pimpinan_and_admin_can_view_all_organization_audit_logs(): void
    {
        // Pimpinan A sees all logs in Org A
        $responsePimpinan = $this->actingAs($this->pimpinanA)->get(route('audit_logs.index'));
        $responsePimpinan->assertOk();
        $responsePimpinan->assertSeeText('Log Akses & Audit Trail');
        $responsePimpinan->assertSee('Pemutaran Audio');
        $responsePimpinan->assertSee('Finalisasi Notulen');
        $responsePimpinan->assertSee('Pembuatan Akun Notulis');
        $responsePimpinan->assertSee('Rapat Koordinasi Notulis Satu');
        $responsePimpinan->assertSee('Rapat Anggaran Notulis Dua');

        // Admin A sees all logs in Org A
        $responseAdmin = $this->actingAs($this->adminA)->get(route('audit_logs.index'));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee('Pemutaran Audio');
        $responseAdmin->assertSee('Finalisasi Notulen');
        $responseAdmin->assertSee('Pembuatan Akun Notulis');
    }

    public function test_notulis_can_only_view_audit_logs_for_meetings_they_created(): void
    {
        // Notulis 1 created Meeting 1
        $response = $this->actingAs($this->notulisA1)->get(route('audit_logs.index'));
        $response->assertOk();

        // 1. Sees log of meeting 1
        $response->assertSee('Rapat Koordinasi Notulis Satu');
        $response->assertSee('Pemutaran Audio');

        // 2. MUST NOT see log of meeting 2 (created by notulis 2)
        $response->assertDontSee('Rapat Anggaran Notulis Dua');

        // 3. MUST NOT see organization-level logs (e.g. user_create by admin)
        $response->assertDontSee('Pembuatan Akun Notulis');
    }

    public function test_cross_tenant_audit_logs_are_strictly_isolated(): void
    {
        // Org A users must never see Org B logs
        $responseA = $this->actingAs($this->pimpinanA)->get(route('audit_logs.index'));
        $responseA->assertOk();
        $responseA->assertDontSee('Rapat Rahasia Organisasi B');
        $responseA->assertDontSee('10.0.0.1');

        // Org B user must never see Org A logs
        $responseB = $this->actingAs($this->pimpinanB)->get(route('audit_logs.index'));
        $responseB->assertOk();
        $responseB->assertSee('Rapat Rahasia Organisasi B');
        $responseB->assertDontSee('Rapat Koordinasi Notulis Satu');
        $responseB->assertDontSee('Rapat Anggaran Notulis Dua');
    }

    public function test_audit_logs_filters_by_meeting_action_role_and_date(): void
    {
        // 1. Filter by meeting_id (Meeting 1)
        $respMeeting = $this->actingAs($this->pimpinanA)
            ->get(route('audit_logs.index', ['meeting_id' => $this->meeting1->id]));
        $respMeeting->assertOk();
        $respMeeting->assertViewHas('logs', function ($logs) {
            return $logs->pluck('id')->contains($this->logMeeting1->id)
                && ! $logs->pluck('id')->contains($this->logMeeting2->id)
                && ! $logs->pluck('id')->contains($this->logOrgLevel->id);
        });
        $respMeeting->assertSee('192.168.1.1');
        $respMeeting->assertDontSee('created_user_role');

        // 2. Filter by action
        $respAction = $this->actingAs($this->pimpinanA)
            ->get(route('audit_logs.index', ['action' => 'audio_play']));
        $respAction->assertOk();
        $respAction->assertViewHas('logs', function ($logs) {
            return $logs->pluck('id')->contains($this->logMeeting1->id)
                && ! $logs->pluck('id')->contains($this->logMeeting2->id);
        });
        $respAction->assertSee('192.168.1.1');
        $respAction->assertDontSee('created_user_role');

        // 3. Filter by role (e.g. role=admin)
        $respRole = $this->actingAs($this->pimpinanA)
            ->get(route('audit_logs.index', ['role' => 'admin']));
        $respRole->assertOk();
        $respRole->assertViewHas('logs', function ($logs) {
            return $logs->pluck('id')->contains($this->logOrgLevel->id)
                && ! $logs->pluck('id')->contains($this->logMeeting1->id)
                && ! $logs->pluck('id')->contains($this->logMeeting2->id);
        });
        $respRole->assertSee('created_user_role');
        $respRole->assertDontSee('192.168.1.1');

        // 4. Filter by date (today)
        $respDate = $this->actingAs($this->pimpinanA)
            ->get(route('audit_logs.index', ['date' => now()->toDateString()]));
        $respDate->assertOk();
        $respDate->assertViewHas('logs', fn ($logs) => $logs->count() >= 3);
        $respDate->assertSee('192.168.1.1');

        // Filter by past date where no logs exist
        $respPast = $this->actingAs($this->pimpinanA)
            ->get(route('audit_logs.index', ['date' => '2020-01-01']));
        $respPast->assertOk();
        $respPast->assertViewHas('logs', fn ($logs) => $logs->isEmpty());
        $respPast->assertSee('Tidak ada data log akses yang sesuai dengan filter');
    }

    public function test_audit_logs_are_strictly_read_only_via_http_and_model(): void
    {
        // 1. Direct POST to /audit-logs is rejected with 405 Method Not Allowed
        $postResponse = $this->actingAs($this->pimpinanA)->post('/audit-logs', [
            'action' => 'fake_action',
        ]);
        $this->assertEquals(405, $postResponse->getStatusCode());

        // 2. Direct PUT / DELETE to /audit-logs is rejected
        $putResponse = $this->actingAs($this->pimpinanA)->put('/audit-logs/'.$this->logMeeting1->id, [
            'action' => 'tampered',
        ]);
        $this->assertContains($putResponse->getStatusCode(), [404, 405]);

        $deleteResponse = $this->actingAs($this->pimpinanA)->delete('/audit-logs/'.$this->logMeeting1->id);
        $this->assertContains($deleteResponse->getStatusCode(), [404, 405]);

        // 3. Model level update rejects mutations with LogicException
        $this->expectException(LogicException::class);
        $this->logMeeting1->update(['action' => 'tampered_action']);
    }
}
