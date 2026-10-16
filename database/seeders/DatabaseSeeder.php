<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationSignatory;
use App\Models\OrgContact;
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

        // 3. Penandatangan Organisasi
        OrganizationSignatory::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => 'Drs. H. Mulyadi, M.Pd.',
            'position' => 'Kepala Sekolah',
            'role_label' => 'Mengetahui',
            'sort' => 1,
        ]);

        OrganizationSignatory::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => 'Budi Santoso, S.Pd.',
            'position' => 'Waka Kurikulum',
            'role_label' => 'Ketua Rapat',
            'sort' => 2,
        ]);

        OrganizationSignatory::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => 'Notulis Satu',
            'position' => 'Staf Tata Usaha',
            'role_label' => 'Notulis',
            'sort' => 3,
        ]);

        // 4. Buku Kontak Organisasi
        OrgContact::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => 'Budi Santoso, S.Pd.',
            'position' => 'Waka Kurikulum',
            'phone_e164' => '6281234567801',
        ]);

        OrgContact::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => 'Siti Aminah, M.Pd.',
            'position' => 'Waka Kesiswaan',
            'phone_e164' => '6281234567802',
        ]);

        OrgContact::withoutGlobalScopes()->create([
            'organization_id' => $org->id,
            'name' => 'Ir. Hendro Kusumo',
            'position' => 'Ketua Komite Sekolah',
            'phone_e164' => '6281234567803',
        ]);

        // 5. Super Admin Platform (Guard Platform Terpisah)
        SuperAdmin::create([
            'name' => 'Super Admin Platform',
            'email' => 'admin@platform.test',
            'password' => 'password',
        ]);
    }
}
