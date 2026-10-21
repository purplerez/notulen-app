<?php

namespace Tests\Feature\Platform;

use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\PriceRate;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorageCostCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_storage_cost_command_creates_daily_usage_logs(): void
    {
        PriceRate::create([
            'provider' => 'local_disk',
            'model' => 'audio_storage',
            'unit' => 'gb_bulan',
            'price_usd' => 0.030, // $0.03 per GB-month
            'effective_from' => '2026-01-01 00:00:00',
        ]);

        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $meetingActive = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'audio_deleted_at' => null,
        ]);

        $oneGb = 1024 * 1024 * 1024; // 1 GB

        AudioSegment::create([
            'organization_id' => $org->id,
            'meeting_id' => $meetingActive->id,
            'seq' => 1,
            'path' => 'audio/seg1.webm',
            'mime' => 'audio/webm',
            'size_bytes' => $oneGb,
            'duration_sec' => 3600,
            'checksum' => 'checksum1',
            'status' => 'ready',
        ]);

        // Purged meeting audio segment should NOT be counted
        $meetingPurged = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'audio_deleted_at' => now()->subDay(),
        ]);

        AudioSegment::create([
            'organization_id' => $org->id,
            'meeting_id' => $meetingPurged->id,
            'seq' => 1,
            'path' => 'audio/purged.webm',
            'mime' => 'audio/webm',
            'size_bytes' => 2 * $oneGb,
            'duration_sec' => 7200,
            'checksum' => 'checksum2',
            'status' => 'ready',
        ]);

        $this->artisan('storage:calculate-cost', ['--date' => '2026-10-21'])
            ->assertExitCode(0);

        // Verify UsageLog created
        $log = UsageLog::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('kind', 'penyimpanan')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals($oneGb, (int) $log->qty);
        $this->assertEquals('byte_hari', $log->unit);
        $this->assertEquals(0.030, (float) $log->rate_snapshot);

        // 1 GB * (0.03 / 30) = 0.001 USD
        $this->assertEquals(0.001, (float) $log->est_cost_usd);
        $this->assertEquals(round(0.001 * 16000.0, 2), (float) $log->est_cost_idr);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'storage_cost_calculated',
        ]);
    }

    public function test_calculate_storage_cost_is_idempotent_for_same_date(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $notulis->id,
            'audio_deleted_at' => null,
        ]);

        AudioSegment::create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'seq' => 1,
            'path' => 'audio/seg1.webm',
            'mime' => 'audio/webm',
            'size_bytes' => 50000000,
            'duration_sec' => 120,
            'checksum' => 'c1',
            'status' => 'ready',
        ]);

        // Run first time
        $this->artisan('storage:calculate-cost', ['--date' => '2026-10-21'])
            ->assertExitCode(0);

        $countAfterFirst = UsageLog::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('kind', 'penyimpanan')
            ->count();

        $this->assertEquals(1, $countAfterFirst);

        // Run second time on same date
        $this->artisan('storage:calculate-cost', ['--date' => '2026-10-21'])
            ->assertExitCode(0);

        $countAfterSecond = UsageLog::withoutGlobalScopes()
            ->where('organization_id', $org->id)
            ->where('kind', 'penyimpanan')
            ->count();

        $this->assertEquals(1, $countAfterSecond);
    }
}
