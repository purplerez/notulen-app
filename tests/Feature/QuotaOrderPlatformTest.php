<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\QuotaOrder;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaOrderPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected SuperAdmin $superAdmin;

    protected Organization $orgA;

    protected Organization $orgB;

    protected User $adminA;

    protected QuotaOrder $orderA;

    protected QuotaOrder $orderB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = SuperAdmin::create([
            'name' => 'Super Admin Platform',
            'email' => 'superadmin@platform.test',
            'password' => bcrypt('password123'),
            'totp_enabled' => true,
        ]);

        $this->orgA = Organization::create([
            'name' => 'SMK Negeri 1',
            'slug' => 'smkn-1',
            'quota_minutes' => 300,
        ]);

        $this->orgB = Organization::create([
            'name' => 'SMA Negeri 2',
            'slug' => 'sman-2',
            'quota_minutes' => 600,
        ]);

        $this->adminA = User::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Admin SMKN 1',
            'email' => 'admin@smkn1.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->orderA = QuotaOrder::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'order_number' => 'QO-TEST-PLATFORM-A',
            'hours' => 3,
            'minutes' => 180,
            'price_per_hour' => 45000,
            'total_amount' => 135000,
            'status' => 'waiting_verification',
            'created_by' => $this->adminA->id,
        ]);

        $this->orderB = QuotaOrder::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'order_number' => 'QO-TEST-PLATFORM-B',
            'hours' => 5,
            'minutes' => 300,
            'price_per_hour' => 45000,
            'total_amount' => 225000,
            'status' => 'pending',
        ]);
    }

    public function test_super_admin_can_view_all_tenant_quota_orders(): void
    {
        $response = $this->actingAs($this->superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->get(route('platform.quota_orders.index'));

        $response->assertOk();
        $response->assertSee('Manajemen Pembelian Kuota Tenant');
        $response->assertSee('SMK Negeri 1');
        $response->assertSee('SMA Negeri 2');
        $response->assertSee('QO-TEST-PLATFORM-A');
        $response->assertSee('QO-TEST-PLATFORM-B');
    }

    public function test_super_admin_can_approve_quota_order_and_grant_quota(): void
    {
        $initialMinutes = $this->orgA->quota_minutes; // 300

        $response = $this->actingAs($this->superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->post(route('platform.quota_orders.approve', $this->orderA));

        $response->assertRedirect();
        $this->orderA->refresh();
        $this->orgA->refresh();

        $this->assertSame('paid', $this->orderA->status);
        $this->assertNotNull($this->orderA->paid_at);
        $this->assertSame($this->superAdmin->id, $this->orderA->approved_by);

        // Quota should have been added: 300 + 180 = 480 minutes
        $this->assertSame($initialMinutes + 180, $this->orgA->quota_minutes);

        // Audit log recorded
        $audit = AuditLog::withoutGlobalScopes()
            ->where('action', 'quota_order_approved')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->orderA->order_number, $audit->meta['order_number']);
    }

    public function test_super_admin_can_reject_order_with_notes(): void
    {
        $response = $this->actingAs($this->superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->post(route('platform.quota_orders.reject', $this->orderA), [
                'notes' => 'Bukti mutasi tidak ditemukan pada rekening platform.',
            ]);

        $response->assertRedirect();
        $this->orderA->refresh();

        $this->assertSame('rejected', $this->orderA->status);
        $this->assertSame('Bukti mutasi tidak ditemukan pada rekening platform.', $this->orderA->notes);
        $this->assertSame($this->superAdmin->id, $this->orderA->approved_by);

        // Quota should NOT have changed
        $this->orgA->refresh();
        $this->assertSame(300, $this->orgA->quota_minutes);

        $audit = AuditLog::withoutGlobalScopes()
            ->where('action', 'quota_order_rejected')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_regular_tenant_admin_cannot_access_platform_quota_orders(): void
    {
        $response = $this->actingAs($this->adminA)
            ->get(route('platform.quota_orders.index'));

        // Should redirect to platform login
        $response->assertRedirect(route('platform.login'));
    }
}
