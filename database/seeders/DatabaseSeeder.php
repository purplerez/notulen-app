<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Organisasi Awal
        $org = Organization::create([
            'name' => 'SMK Negeri 1 Contoh',
            'slug' => 'smkn1-contoh',
            'address_text' => 'Jl. Pendidikan No. 1, Kota Contoh',
            'kop_path' => null,
            'plan' => 'internal',
            'quota_minutes' => 6000,
            'settings' => [
                'leader_label' => 'Kepala Sekolah',
            ],
        ]);

        // 2. Pengguna Organisasi (Admin, Pimpinan, Notulis)
        User::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => 'Admin SMKN1',
            'email' => 'admin@smkn1.test',
            'password' => 'password',
            'role' => 'admin',
            'is_active' => true,
        ]);

        User::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => 'Kepala Sekolah (Pimpinan)',
            'email' => 'pimpinan@smkn1.test',
            'password' => 'password',
            'role' => 'pimpinan',
            'is_active' => true,
        ]);

        User::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => 'Notulis Satu',
            'email' => 'notulis1@smkn1.test',
            'password' => 'password',
            'role' => 'notulis',
            'is_active' => true,
        ]);

        User::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => 'Notulis Dua',
            'email' => 'notulis2@smkn1.test',
            'password' => 'password',
            'role' => 'notulis',
            'is_active' => true,
        ]);

        // 3. Super Admin Platform (Guard Platform Terpisah)
        SuperAdmin::create([
            'name' => 'Super Admin Platform',
            'email' => 'admin@platform.test',
            'password' => 'password',
        ]);
    }
}
