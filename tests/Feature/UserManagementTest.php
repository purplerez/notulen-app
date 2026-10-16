<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_user_management_and_create_notulis(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)
            ->get('/users')
            ->assertStatus(200);

        $this->actingAs($admin)
            ->get('/users/create')
            ->assertStatus(200);

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Notulis Baru',
            'email' => 'notulisbaru@contoh.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'organization_id' => $org->id,
            'email' => 'notulisbaru@contoh.test',
            'role' => 'notulis',
        ]);
    }

    public function test_pimpinan_can_view_user_management_and_create_notulis(): void
    {
        $org = Organization::factory()->create();
        $pimpinan = User::factory()->pimpinan()->create(['organization_id' => $org->id]);

        $this->actingAs($pimpinan)
            ->get('/users')
            ->assertStatus(200);

        $response = $this->actingAs($pimpinan)->post('/users', [
            'name' => 'Notulis Kedua',
            'email' => 'notulis2@contoh.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'organization_id' => $org->id,
            'email' => 'notulis2@contoh.test',
            'role' => 'notulis',
        ]);
    }

    public function test_notulis_cannot_access_user_management_or_create_users(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $this->actingAs($notulis)
            ->get('/users')
            ->assertStatus(403);

        $this->actingAs($notulis)
            ->get('/users/create')
            ->assertStatus(403);

        $this->actingAs($notulis)
            ->post('/users', [
                'name' => 'Ilegal Notulis',
                'email' => 'ilegal@contoh.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])
            ->assertStatus(403);
    }

    public function test_creating_user_always_forces_notulis_role_even_if_tampered(): void
    {
        $org = Organization::factory()->create();
        $admin = User::factory()->admin()->create(['organization_id' => $org->id]);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Attempt Admin',
            'email' => 'attempt@contoh.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin', // Tampering attempt
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'attempt@contoh.test',
            'role' => 'notulis', // Must strictly remain notulis
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/users')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
