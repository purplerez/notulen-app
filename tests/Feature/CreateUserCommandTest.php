<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_admin_user_via_artisan_command(): void
    {
        $org = Organization::factory()->create(['slug' => 'smkn1-contoh']);

        $this->artisan('app:create-user', [
            'organization' => 'smkn1-contoh',
            'name' => 'Operator Admin',
            'email' => 'admin@operator.test',
            'role' => 'admin',
            '--password' => 'secret123',
        ])
            ->expectsOutputToContain("Akun admin 'Operator Admin' (admin@operator.test) berhasil dibuat")
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'organization_id' => $org->id,
            'email' => 'admin@operator.test',
            'role' => 'admin',
        ]);
    }

    public function test_can_create_pimpinan_user_via_artisan_command(): void
    {
        $org = Organization::factory()->create(['slug' => 'smkn1-contoh']);

        $this->artisan('app:create-user', [
            'organization' => 'smkn1-contoh',
            'name' => 'Bapak Kepala',
            'email' => 'pimpinan@operator.test',
            'role' => 'pimpinan',
            '--password' => 'secret123',
        ])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'organization_id' => $org->id,
            'email' => 'pimpinan@operator.test',
            'role' => 'pimpinan',
        ]);
    }

    public function test_fails_if_organization_does_not_exist(): void
    {
        $this->artisan('app:create-user', [
            'organization' => 'nonexistent-org',
            'name' => 'Test User',
            'email' => 'test@operator.test',
            'role' => 'admin',
            '--password' => 'secret123',
        ])
            ->expectsOutputToContain("Organisasi dengan slug 'nonexistent-org' tidak ditemukan.")
            ->assertFailed();
    }

    public function test_fails_if_role_is_invalid(): void
    {
        $org = Organization::factory()->create(['slug' => 'smkn1-contoh']);

        $this->artisan('app:create-user', [
            'organization' => 'smkn1-contoh',
            'name' => 'Test User',
            'email' => 'test@operator.test',
            'role' => 'notulis', // only admin or pimpinan allowed via command
            '--password' => 'secret123',
        ])
            ->expectsOutputToContain('Peran tidak valid.')
            ->assertFailed();
    }

    public function test_fails_if_email_is_already_taken(): void
    {
        $org = Organization::factory()->create(['slug' => 'smkn1-contoh']);
        User::factory()->create(['organization_id' => $org->id, 'email' => 'existing@operator.test']);

        $this->artisan('app:create-user', [
            'organization' => 'smkn1-contoh',
            'name' => 'Duplicate User',
            'email' => 'existing@operator.test',
            'role' => 'admin',
            '--password' => 'secret123',
        ])
            ->expectsOutputToContain("Email 'existing@operator.test' sudah digunakan.")
            ->assertFailed();
    }
}
