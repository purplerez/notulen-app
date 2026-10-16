<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Scopes\OrganizationScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_only_sees_users_from_their_own_organization(): void
    {
        $orgA = Organization::factory()->create(['name' => 'Organisasi A']);
        $orgB = Organization::factory()->create(['name' => 'Organisasi B']);

        $userA1 = User::factory()->create(['organization_id' => $orgA->id, 'name' => 'User A1']);
        $userA2 = User::factory()->create(['organization_id' => $orgA->id, 'name' => 'User A2']);
        $userB1 = User::factory()->create(['organization_id' => $orgB->id, 'name' => 'User B1']);

        $this->actingAs($userA1);

        $visibleUsers = User::all();

        $this->assertCount(2, $visibleUsers);
        $this->assertTrue($visibleUsers->contains($userA1));
        $this->assertTrue($visibleUsers->contains($userA2));
        $this->assertFalse($visibleUsers->contains($userB1));
    }

    public function test_model_creation_automatically_populates_organization_id(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);

        $this->actingAs($user);

        $log = AuditLog::create([
            'action' => 'test_action',
        ]);

        $this->assertEquals($org->id, $log->organization_id);
    }

    public function test_audit_logs_are_isolated_between_organizations(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->create(['organization_id' => $orgA->id]);
        $userB = User::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($userA);
        AuditLog::create(['action' => 'org_a_action']);

        $this->actingAs($userB);
        AuditLog::create(['action' => 'org_b_action']);

        $visibleLogs = AuditLog::all();
        $this->assertCount(1, $visibleLogs);
        $this->assertEquals('org_b_action', $visibleLogs->first()->action);
    }

    public function test_organization_scope_can_be_explicitly_bypassed(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $userA = User::factory()->create(['organization_id' => $orgA->id]);
        $userB = User::factory()->create(['organization_id' => $orgB->id]);

        $this->actingAs($userA);

        $scopedCount = User::count();
        $unscopedCount = User::withoutOrganizationScope()->count();
        $explicitBypassCount = User::withoutGlobalScope(OrganizationScope::class)->count();

        $this->assertEquals(1, $scopedCount);
        $this->assertEquals(2, $unscopedCount);
        $this->assertEquals(2, $explicitBypassCount);
    }
}
