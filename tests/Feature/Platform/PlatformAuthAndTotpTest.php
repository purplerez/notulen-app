<?php

namespace Tests\Feature\Platform;

use App\Models\SuperAdmin;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class PlatformAuthAndTotpTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_form_is_accessible(): void
    {
        $response = $this->get(route('platform.login'));

        $response->assertStatus(200);
        $response->assertSee('Platform Super Admin');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('correct-password'),
        ]);

        $response = $this->post(route('platform.login.submit'), [
            'email' => 'super@notulen.test',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertNull(session('platform_auth_id'));
        $this->assertFalse(Auth::guard('platform')->check());
    }

    public function test_valid_credentials_redirect_to_2fa_verification(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post(route('platform.login.submit'), [
            'email' => 'super@notulen.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('platform.verify_2fa'));
        $this->assertEquals($superAdmin->id, session('platform_auth_id'));
        $this->assertFalse(Auth::guard('platform')->check());

        // A secret should have been generated
        $superAdmin->refresh();
        $this->assertNotEmpty($superAdmin->totp_secret);
        $this->assertFalse($superAdmin->hasTotpEnabled());
    }

    public function test_2fa_form_requires_pending_or_active_session(): void
    {
        $response = $this->get(route('platform.verify_2fa'));

        $response->assertRedirect(route('platform.login'));
    }

    public function test_2fa_verification_fails_with_invalid_code(): void
    {
        $secret = Totp::generateSecret();
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_secret' => $secret,
            'totp_enabled' => true,
            'totp_confirmed_at' => now(),
        ]);

        $response = $this->withSession(['platform_auth_id' => $superAdmin->id])
            ->post(route('platform.verify_2fa.submit'), [
                'code' => '000000',
            ]);

        $response->assertSessionHasErrors('code');
        $this->assertFalse(Auth::guard('platform')->check());

        // Check failed audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform_login_2fa',
        ]);
    }

    public function test_2fa_verification_succeeds_with_valid_totp_code(): void
    {
        $secret = Totp::generateSecret();
        $code = Totp::getCode($secret);

        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_secret' => $secret,
            'totp_enabled' => false,
        ]);

        $response = $this->withSession(['platform_auth_id' => $superAdmin->id])
            ->post(route('platform.verify_2fa.submit'), [
                'code' => $code,
            ]);

        $response->assertRedirect(route('platform.dashboard'));
        $this->assertTrue(Auth::guard('platform')->check());
        $this->assertEquals($superAdmin->id, Auth::guard('platform')->id());
        $this->assertTrue(session('platform_2fa_verified'));

        $superAdmin->refresh();
        $this->assertTrue($superAdmin->hasTotpEnabled());
        $this->assertNotNull($superAdmin->totp_confirmed_at);

        // Check audit log for login
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform_login',
        ]);
    }

    public function test_super_admin_cannot_access_platform_without_2fa_verification(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
        ]);

        // Logged into platform guard but without session('platform_2fa_verified')
        $response = $this->actingAs($superAdmin, 'platform')
            ->get(route('platform.dashboard'));

        $response->assertRedirect(route('platform.verify_2fa'));
    }

    public function test_unauthenticated_user_accessing_platform_redirects_to_login(): void
    {
        $response = $this->get(route('platform.dashboard'));

        $response->assertRedirect(route('platform.login'));
    }

    public function test_super_admin_can_logout(): void
    {
        $superAdmin = SuperAdmin::create([
            'name' => 'Super Admin',
            'email' => 'super@notulen.test',
            'password' => bcrypt('password123'),
            'totp_enabled' => true,
        ]);

        $response = $this->actingAs($superAdmin, 'platform')
            ->withSession(['platform_2fa_verified' => true])
            ->post(route('platform.logout'));

        $response->assertRedirect(route('platform.login'));
        $this->assertFalse(Auth::guard('platform')->check());
        $this->assertNull(session('platform_2fa_verified'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform_logout',
        ]);
    }
}
