<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PlatformGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_authenticate_via_platform_guard(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@platform.test',
            'password' => bcrypt('password123'),
        ]);

        $attempt = Auth::guard('platform')->attempt([
            'email' => 'super@platform.test',
            'password' => 'password123',
        ]);

        $this->assertTrue($attempt);
        $this->assertEquals($superAdmin->id, Auth::guard('platform')->id());
    }

    public function test_regular_user_cannot_authenticate_via_platform_guard(): void
    {
        $org = Organization::factory()->create();
        User::factory()->create([
            'organization_id' => $org->id,
            'email' => 'user@tenant.test',
            'password' => bcrypt('password123'),
        ]);

        $attempt = Auth::guard('platform')->attempt([
            'email' => 'user@tenant.test',
            'password' => 'password123',
        ]);

        $this->assertFalse($attempt);
        $this->assertNull(Auth::guard('platform')->user());
    }

    public function test_super_admin_cannot_authenticate_via_web_guard(): void
    {
        SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@platform.test',
            'password' => bcrypt('password123'),
        ]);

        $attempt = Auth::guard('web')->attempt([
            'email' => 'super@platform.test',
            'password' => 'password123',
        ]);

        $this->assertFalse($attempt);
        $this->assertNull(Auth::guard('web')->user());
    }

    public function test_platform_and_web_guards_are_isolated(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(['organization_id' => $org->id]);

        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@platform.test',
            'password' => bcrypt('password123'),
        ]);

        Auth::guard('platform')->login($superAdmin);

        $this->assertTrue(Auth::guard('platform')->check());
        $this->assertFalse(Auth::guard('web')->check());

        Auth::guard('web')->login($user);

        $this->assertTrue(Auth::guard('web')->check());
        $this->assertTrue(Auth::guard('platform')->check());
    }
}
