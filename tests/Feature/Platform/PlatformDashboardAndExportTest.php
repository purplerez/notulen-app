<?php

namespace Tests\Feature\Platform;

use App\Models\Organization;
use App\Models\PriceRate;
use App\Models\SuperAdmin;
use App\Models\UsageLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PlatformDashboardAndExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_platform_dashboard(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_enabled' => true,
        ]);

        $orgA = Organization::factory()->create(['name' => 'Organisasi Alpha', 'quota_minutes' => 600]);
        $orgB = Organization::factory()->create(['name' => 'Organisasi Beta', 'quota_minutes' => 300]);

        // Create usage logs for orgA in current month
        UsageLog::create([
            'organization_id' => $orgA->id,
            'meeting_id' => null,
            'user_id' => null,
            'kind' => 'stt',
            'provider' => 'groq',
            'model' => 'whisper-large-v3-turbo',
            'unit' => 'detik_audio',
            'qty' => 3600.0,
            'audio_sec' => 3600.0,
            'rate_snapshot' => 0.00005,
            'est_cost_usd' => 0.18,
            'est_cost_idr' => 2880.0,
            'fx_rate_used' => 16000.0,
            'est_cost' => 0.18,
            'status' => 'sukses',
            'attempt' => 1,
            'created_at' => now(),
        ]);

        // Create storage usage log for orgA
        UsageLog::create([
            'organization_id' => $orgA->id,
            'meeting_id' => null,
            'user_id' => null,
            'kind' => 'penyimpanan',
            'provider' => 'local_disk',
            'model' => 'audio_storage',
            'unit' => 'byte_hari',
            'qty' => 1073741824, // 1 GB
            'audio_sec' => 0,
            'rate_snapshot' => 0.02,
            'est_cost_usd' => 0.000667,
            'est_cost_idr' => 10.67,
            'fx_rate_used' => 16000.0,
            'est_cost' => 0.000667,
            'status' => 'sukses',
            'attempt' => 1,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->get(route('platform.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Organisasi Alpha');
        $response->assertSee('Organisasi Beta');
        $response->assertSee('2.891'); // 2890.67 IDR rounded

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform_dashboard_view',
        ]);
    }

    public function test_super_admin_can_view_reconciliation_and_save_actual_invoice(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_enabled' => true,
        ]);

        $org = Organization::factory()->create();
        $currentMonth = Carbon::now()->format('Y-m');

        // Usage log for groq
        UsageLog::create([
            'organization_id' => $org->id,
            'meeting_id' => null,
            'user_id' => null,
            'kind' => 'stt',
            'provider' => 'groq',
            'model' => 'whisper-large-v3-turbo',
            'unit' => 'detik_audio',
            'qty' => 10000.0,
            'audio_sec' => 10000.0,
            'rate_snapshot' => 0.00005,
            'est_cost_usd' => 0.50,
            'est_cost_idr' => 8000.0,
            'fx_rate_used' => 16000.0,
            'est_cost' => 0.50,
            'status' => 'sukses',
            'attempt' => 1,
            'created_at' => now(),
        ]);

        // GET reconciliation page
        $response = $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->get(route('platform.reconciliation.index', ['month' => $currentMonth]));

        $response->assertStatus(200);
        $response->assertSee('groq');

        // POST store actual invoice
        $postResponse = $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->post(route('platform.reconciliation.store'), [
                'month' => $currentMonth,
                'provider' => 'groq',
                'actual_cost_usd' => '0.48',
                'actual_cost_idr' => '7680.00',
                'notes' => 'Tagihan resmi invoice Groq nomor #INV-1029',
            ]);

        $postResponse->assertRedirect();
        $postResponse->assertSessionHas('success');

        $this->assertDatabaseHas('reconciliation_records', [
            'month' => $currentMonth,
            'provider' => 'groq',
            'actual_cost_usd' => 0.48,
            'actual_cost_idr' => 7680.00,
            'notes' => 'Tagihan resmi invoice Groq nomor #INV-1029',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform_reconciliation_save',
        ]);

        // Verify reconciliation page now computes diff
        $responseAfter = $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->get(route('platform.reconciliation.index', ['month' => $currentMonth]));

        $responseAfter->assertStatus(200);
        $responseAfter->assertSee('INV-1029');
    }

    public function test_super_admin_can_view_rates_management(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_enabled' => true,
        ]);

        PriceRate::create([
            'provider' => 'groq',
            'model' => 'whisper-large-v3-turbo',
            'unit' => 'detik_audio',
            'price_usd' => 0.00005,
            'effective_from' => now()->subDays(5),
        ]);

        $response = $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->get(route('platform.rates.index'));

        $response->assertStatus(200);
        $response->assertSee('whisper-large-v3-turbo');
        $response->assertSee('Kurs Harian USD ke IDR');
    }
}
