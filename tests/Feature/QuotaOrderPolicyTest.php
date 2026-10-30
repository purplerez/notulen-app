<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\QuotaOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class QuotaOrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;

    protected Organization $orgB;

    protected User $adminA;

    protected User $pimpinanA;

    protected User $notulisA;

    protected User $adminB;

    protected QuotaOrder $orderA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::create([
            'name' => 'Organisasi A',
            'slug' => 'org-a',
            'quota_minutes' => 300,
        ]);

        $this->orgB = Organization::create([
            'name' => 'Organisasi B',
            'slug' => 'org-b',
            'quota_minutes' => 300,
        ]);

        $this->adminA = User::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Admin A',
            'email' => 'adminA@test.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->pimpinanA = User::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Pimpinan A',
            'email' => 'pimpinanA@test.com',
            'password' => 'password',
            'role' => 'pimpinan',
        ]);

        $this->notulisA = User::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'name' => 'Notulis A',
            'email' => 'notulisA@test.com',
            'password' => 'password',
            'role' => 'notulis',
        ]);

        $this->adminB = User::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'name' => 'Admin B',
            'email' => 'adminB@test.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->orderA = QuotaOrder::withoutGlobalScopes()->create([
            'organization_id' => $this->orgA->id,
            'order_number' => 'QO-TEST-001',
            'hours' => 2,
            'minutes' => 120,
            'price_per_hour' => 50000,
            'total_amount' => 100000,
            'status' => 'pending',
            'created_by' => $this->adminA->id,
        ]);
    }

    public function test_admin_and_pimpinan_can_view_any_quota_orders(): void
    {
        $this->assertTrue(Gate::forUser($this->adminA)->allows('viewAny', QuotaOrder::class));
        $this->assertTrue(Gate::forUser($this->pimpinanA)->allows('viewAny', QuotaOrder::class));
        $this->assertFalse(Gate::forUser($this->notulisA)->allows('viewAny', QuotaOrder::class));
    }

    public function test_only_admin_can_create_quota_order(): void
    {
        $this->assertTrue(Gate::forUser($this->adminA)->allows('create', QuotaOrder::class));
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('create', QuotaOrder::class));
        $this->assertFalse(Gate::forUser($this->notulisA)->allows('create', QuotaOrder::class));
    }

    public function test_admin_and_pimpinan_can_view_their_own_org_order(): void
    {
        $this->assertTrue(Gate::forUser($this->adminA)->allows('view', $this->orderA));
        $this->assertTrue(Gate::forUser($this->pimpinanA)->allows('view', $this->orderA));
        $this->assertFalse(Gate::forUser($this->notulisA)->allows('view', $this->orderA));
    }

    public function test_cross_tenant_access_is_forbidden(): void
    {
        // Admin B cannot view Order of Org A
        $this->assertFalse(Gate::forUser($this->adminB)->allows('view', $this->orderA));
        $this->assertFalse(Gate::forUser($this->adminB)->allows('uploadProof', $this->orderA));
        $this->assertFalse(Gate::forUser($this->adminB)->allows('cancel', $this->orderA));
        $this->assertFalse(Gate::forUser($this->adminB)->allows('simulate', $this->orderA));
    }

    public function test_only_admin_can_upload_proof_and_cancel(): void
    {
        $this->assertTrue(Gate::forUser($this->adminA)->allows('uploadProof', $this->orderA));
        $this->assertTrue(Gate::forUser($this->adminA)->allows('cancel', $this->orderA));
        $this->assertTrue(Gate::forUser($this->adminA)->allows('simulate', $this->orderA));

        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('uploadProof', $this->orderA));
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('cancel', $this->orderA));
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('simulate', $this->orderA));
    }
}
