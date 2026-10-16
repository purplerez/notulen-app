<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-user
        {organization : Slug organisasi}
        {name : Nama pengguna}
        {email : Alamat email}
        {role : Peran (admin atau pimpinan)}
        {--password= : Kata sandi (opsional, akan diminta jika tidak disediakan)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create an admin or pimpinan user account for an organization (operator only)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $orgSlug = (string) $this->argument('organization');
        $org = Organization::where('slug', $orgSlug)->first();

        if (! $org) {
            $this->error("Organisasi dengan slug '{$orgSlug}' tidak ditemukan.");

            return self::FAILURE;
        }

        $role = (string) $this->argument('role');
        if (! in_array($role, ['admin', 'pimpinan'], true)) {
            $this->error("Peran tidak valid. Hanya peran 'admin' atau 'pimpinan' yang dapat dibuat lewat perintah ini.");

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        if (User::withoutGlobalScopes()->where('email', $email)->exists()) {
            $this->error("Email '{$email}' sudah digunakan.");

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Masukkan kata sandi');
        if (empty($password)) {
            $this->error('Kata sandi tidak boleh kosong.');

            return self::FAILURE;
        }

        $user = User::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => (string) $this->argument('name'),
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'is_active' => true,
        ]);

        $this->info("Akun {$role} '{$user->name}' ({$user->email}) berhasil dibuat untuk organisasi '{$org->name}'.");

        return self::SUCCESS;
    }
}
