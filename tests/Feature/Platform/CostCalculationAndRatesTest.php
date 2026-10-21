<?php

namespace Tests\Feature\Platform;

use App\Contracts\SpeechToTextProvider;
use App\DTOs\TranscriptionResult;
use App\Jobs\TranscribeSegment;
use App\Models\AudioSegment;
use App\Models\FxRate;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\PriceRate;
use App\Models\SuperAdmin;
use App\Models\UsageLog;
use App\Models\User;
use App\Services\Cost\CostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CostCalculationAndRatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_calculator_resolves_active_price_and_fx_rates(): void
    {
        // Setup historical rates
        PriceRate::create([
            'provider' => 'groq',
            'model' => 'whisper-large-v3',
            'unit' => 'detik_audio',
            'price_usd' => 0.000050,
            'effective_from' => Carbon::parse('2026-01-01 00:00:00'),
        ]);

        PriceRate::create([
            'provider' => 'groq',
            'model' => 'whisper-large-v3',
            'unit' => 'detik_audio',
            'price_usd' => 0.000030,
            'effective_from' => Carbon::parse('2026-06-01 00:00:00'),
        ]);

        FxRate::create([
            'date' => '2026-01-01',
            'usd_idr' => 15500.00,
        ]);

        FxRate::create([
            'date' => '2026-06-01',
            'usd_idr' => 16200.00,
        ]);

        // Calculation in March 2026 should use January rates
        $marchResult = CostCalculator::calculate(
            'groq',
            'whisper-large-v3',
            'detik_audio',
            100.0,
            Carbon::parse('2026-03-15 10:00:00')
        );

        $this->assertEquals(0.000050, $marchResult['rate_snapshot']);
        $this->assertEquals(15500.00, $marchResult['fx_rate_used']);
        $this->assertEquals(round(100.0 * 0.000050, 6), $marchResult['est_cost_usd']);
        $this->assertEquals(round(100.0 * 0.000050 * 15500.00, 2), $marchResult['est_cost_idr']);

        // Calculation in July 2026 should use June rates
        $julyResult = CostCalculator::calculate(
            'groq',
            'whisper-large-v3',
            'detik_audio',
            100.0,
            Carbon::parse('2026-07-01 10:00:00')
        );

        $this->assertEquals(0.000030, $julyResult['rate_snapshot']);
        $this->assertEquals(16200.00, $julyResult['fx_rate_used']);
        $this->assertEquals(round(100.0 * 0.000030, 6), $julyResult['est_cost_usd']);
        $this->assertEquals(round(100.0 * 0.000030 * 16200.00, 2), $julyResult['est_cost_idr']);
    }

    public function test_transcribe_segment_freezes_cost_snapshot_in_usage_log(): void
    {
        Storage::fake('local');
        $audioContent = 'fake-audio-bytes';
        $path = 'audio/org-1/meeting-1/segment-1.webm';
        Storage::disk('local')->put($path, $audioContent);

        PriceRate::create([
            'provider' => 'groq',
            'model' => 'whisper-large-v3-turbo',
            'unit' => 'detik_audio',
            'price_usd' => 0.000045,
            'effective_from' => now()->subDay(),
        ]);

        FxRate::create([
            'date' => now()->toDateString(),
            'usd_idr' => 16500.00,
        ]);

        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id, 'role' => 'notulis']);
        $meeting = Meeting::factory()->create([
            'organization_id' => $org->id,
            'created_by' => $user->id,
            'status' => 'recording',
        ]);

        $segment = AudioSegment::create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'seq' => 1,
            'path' => $path,
            'mime' => 'audio/webm',
            'size_bytes' => strlen($audioContent),
            'duration_sec' => 60,
            'checksum' => hash('sha256', $audioContent),
            'status' => 'uploaded',
        ]);

        $mockProvider = $this->createMock(SpeechToTextProvider::class);
        $mockProvider->expects($this->once())
            ->method('transcribe')
            ->willReturn(new TranscriptionResult(
                text: 'Halo selamat pagi',
                segments: [['start' => 0.0, 'end' => 60.0, 'text' => 'Halo selamat pagi']],
                duration: 60.0
            ));

        $this->app->instance(SpeechToTextProvider::class, $mockProvider);

        $job = new TranscribeSegment($segment);
        $job->handle($mockProvider);

        // Verify UsageLog recorded frozen cost
        $log = UsageLog::where('meeting_id', $meeting->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('stt', $log->kind);
        $this->assertEquals('detik_audio', $log->unit);
        $this->assertEquals(60.0, (float) $log->qty);
        $this->assertEquals(0.000045, (float) $log->rate_snapshot);
        $this->assertEquals(16500.00, (float) $log->fx_rate_used);
        $this->assertEquals(round(60.0 * 0.000045, 6), (float) $log->est_cost_usd);
        $this->assertEquals(round(60.0 * 0.000045 * 16500.00, 2), (float) $log->est_cost_idr);
        $this->assertEquals('sukses', $log->status);
        $this->assertEquals(1, $log->attempt);

        // Even if rates change tomorrow, past log values remain unchanged
        PriceRate::create([
            'provider' => 'groq',
            'model' => 'whisper-large-v3-turbo',
            'unit' => 'detik_audio',
            'price_usd' => 0.000099,
            'effective_from' => now()->addDay(),
        ]);

        $log->refresh();
        $this->assertEquals(0.000045, (float) $log->rate_snapshot);
    }

    public function test_super_admin_can_store_price_rate(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_enabled' => true,
        ]);

        $response = $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->post(route('platform.rates.price.store'), [
                'provider' => 'openai',
                'model' => 'whisper-1',
                'unit' => 'detik_audio',
                'price_usd' => '0.000100',
                'effective_from' => '2026-11-01 00:00:00',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('price_rates', [
            'provider' => 'openai',
            'model' => 'whisper-1',
            'unit' => 'detik_audio',
            'price_usd' => 0.0001,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform_rate_create',
        ]);
    }

    public function test_super_admin_can_store_fx_rate(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_enabled' => true,
        ]);

        $response = $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->post(route('platform.rates.fx.store'), [
                'date' => '2026-10-21',
                'usd_idr' => '16450.50',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $fx = FxRate::first();
        $this->assertNotNull($fx);
        $this->assertEquals('2026-10-21', $fx->date->toDateString());
        $this->assertEquals(16450.50, (float) $fx->usd_idr);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform_fx_update',
        ]);
    }
}
