<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\QuotaOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotaOrderTenantTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;

    protected User $admin;

    protected User $notulis;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'SMKN 1 Maju',
            'slug' => 'smkn-1-maju',
            'quota_minutes' => 600,
        ]);

        $this->admin = User::withoutGlobalScopes()->create([
            'organization_id' => $this->org->id,
            'name' => 'Admin SMKN',
            'email' => 'admin@smkn1maju.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->notulis = User::withoutGlobalScopes()->create([
            'organization_id' => $this->org->id,
            'name' => 'Notulis SMKN',
            'email' => 'notulis@smkn1maju.test',
            'password' => 'password',
            'role' => 'notulis',
        ]);

        config([
            'quota.rate_per_hour_idr' => 50000,
            'quota.tiers' => [
                ['min_hours' => 1, 'max_hours' => 2, 'discount_pct' => 0],
                ['min_hours' => 3, 'max_hours' => 5, 'discount_pct' => 10],
            ],
            'quota.grace_period_minutes' => 15,
        ]);
    }

    public function test_admin_can_view_quota_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get(route('quota.index'));

        $response->assertOk();
        $response->assertSee('Fasilitas Kuota Rekam Rapat');
        $response->assertSee('SMKN 1 Maju');
    }

    public function test_notulis_cannot_view_quota_dashboard(): void
    {
        $response = $this->actingAs($this->notulis)->get(route('quota.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_quota_order_with_calculated_discount(): void
    {
        // 3 hours should get 10% discount: 3 * 45,000 = 135,000
        $response = $this->actingAs($this->admin)->post(route('quota.orders.store'), [
            'hours' => 3,
        ]);

        $order = QuotaOrder::withoutGlobalScopes()->where('hours', 3)->first();
        $this->assertNotNull($order);
        $this->assertSame(180, $order->minutes);
        $this->assertEquals(45000.0, (float) $order->price_per_hour);
        $this->assertEquals(135000.0, (float) $order->total_amount);
        $this->assertSame('pending', $order->status);

        $response->assertRedirect(route('quota.orders.show', $order));

        // Verify audit log
        $audit = AuditLog::withoutGlobalScopes()
            ->where('action', 'quota_order_create')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame($order->order_number, $audit->meta['order_number']);
    }

    public function test_admin_can_upload_payment_proof(): void
    {
        Storage::fake('local');

        $order = QuotaOrder::withoutGlobalScopes()->create([
            'organization_id' => $this->org->id,
            'order_number' => 'QO-TEST-PROOF',
            'hours' => 2,
            'minutes' => 120,
            'price_per_hour' => 50000,
            'total_amount' => 100000,
            'status' => 'pending',
            'created_by' => $this->admin->id,
        ]);

        $file = UploadedFile::fake()->image('transfer_receipt.jpg');

        $response = $this->actingAs($this->admin)->post(route('quota.orders.proof', $order), [
            'proof' => $file,
        ]);

        $response->assertRedirect();
        $order->refresh();

        $this->assertSame('waiting_verification', $order->status);
        $this->assertNotNull($order->payment_proof_path);
        Storage::disk('local')->assertExists($order->payment_proof_path);

        $audit = AuditLog::withoutGlobalScopes()
            ->where('action', 'quota_order_proof_upload')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_admin_can_simulate_payment_in_sandbox(): void
    {
        $initialQuota = $this->org->quota_minutes; // 600

        $order = QuotaOrder::withoutGlobalScopes()->create([
            'organization_id' => $this->org->id,
            'order_number' => 'QO-TEST-SIM',
            'hours' => 2,
            'minutes' => 120,
            'price_per_hour' => 50000,
            'total_amount' => 100000,
            'status' => 'pending',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('quota.orders.simulate', $order));

        $response->assertRedirect();
        $order->refresh();
        $this->org->refresh();

        $this->assertSame('paid', $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('simulator', $order->payment_method);

        // Organization quota should have increased by 120 minutes (720 minutes)
        $this->assertSame($initialQuota + 120, $this->org->quota_minutes);

        $audit = AuditLog::withoutGlobalScopes()
            ->where('action', 'quota_order_paid')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame(120, $audit->meta['minutes_added']);
    }

    public function test_admin_can_cancel_pending_order(): void
    {
        $order = QuotaOrder::withoutGlobalScopes()->create([
            'organization_id' => $this->org->id,
            'order_number' => 'QO-TEST-CANCEL',
            'hours' => 1,
            'minutes' => 60,
            'price_per_hour' => 50000,
            'total_amount' => 50000,
            'status' => 'pending',
            'created_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->post(route('quota.orders.cancel', $order));

        $response->assertRedirect(route('quota.index'));
        $order->refresh();

        $this->assertSame('cancelled', $order->status);

        $audit = AuditLog::withoutGlobalScopes()
            ->where('action', 'quota_order_cancel')
            ->first();
        $this->assertNotNull($audit);
    }

    public function test_quick_status_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->admin)->get(route('quota.status'));

        $response->assertOk();
        $response->assertJsonStructure([
            'available_minutes',
            'available_hours',
            'used_minutes',
            'total_minutes',
            'grace_period_minutes',
        ]);
        $response->assertJson([
            'total_minutes' => 600,
            'grace_period_minutes' => 15,
        ]);
    }
}
