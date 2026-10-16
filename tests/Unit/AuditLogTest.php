<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_can_be_created(): void
    {
        $org = Organization::factory()->create();

        $log = AuditLog::create([
            'organization_id' => $org->id,
            'meeting_id' => 123,
            'actor_user_id' => null,
            'action' => 'audio_play',
            'meta' => ['sample' => 'data'],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'audio_play',
            'meeting_id' => 123,
        ]);
    }

    public function test_audit_log_cannot_be_updated(): void
    {
        $org = Organization::factory()->create();
        $log = AuditLog::create([
            'organization_id' => $org->id,
            'action' => 'initial_action',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('AuditLog is append-only. Updates are not allowed.');

        $log->update(['action' => 'tampered_action']);
    }

    public function test_audit_log_cannot_be_saved_once_exists(): void
    {
        $org = Organization::factory()->create();
        $log = AuditLog::create([
            'organization_id' => $org->id,
            'action' => 'initial_action',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('AuditLog is append-only. Updates are not allowed.');

        $log->action = 'tampered';
        $log->save();
    }

    public function test_audit_log_cannot_be_deleted(): void
    {
        $org = Organization::factory()->create();
        $log = AuditLog::create([
            'organization_id' => $org->id,
            'action' => 'permanent_action',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('AuditLog is append-only. Deletions are not allowed.');

        $log->delete();
    }

    public function test_audit_helper_records_log_accurately(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user);

        $log = Audit::record('transcript_view', 456, ['ip' => '127.0.0.1']);

        $this->assertEquals($org->id, $log->organization_id);
        $this->assertEquals(456, $log->meeting_id);
        $this->assertEquals($user->id, $log->actor_user_id);
        $this->assertEquals('transcript_view', $log->action);
        $this->assertEquals(['ip' => '127.0.0.1'], $log->meta);
    }
}
