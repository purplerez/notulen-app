<?php

namespace App\Actions;

use App\Models\Organization;
use App\Models\OrganizationSignatory;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateOrganizationAction
{
    /**
     * Create a new organization along with its initial admin user, optional leader user,
     * default signatories, and audit log.
     *
     * @param  array{
     *     name: string,
     *     slug?: ?string,
     *     address_text?: ?string,
     *     leader_label?: ?string,
     *     plan?: ?string,
     *     quota_minutes?: ?int,
     *     admin_name: string,
     *     admin_email: string,
     *     admin_password?: ?string,
     *     leader_name?: ?string,
     *     leader_email?: ?string,
     *     leader_password?: ?string,
     * }  $data
     * @return array{
     *     organization: Organization,
     *     admin: User,
     *     leader: ?User,
     *     raw_admin_password: string,
     *     raw_leader_password: ?string,
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $name = trim($data['name']);
            $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($name);

            // Ensure unique slug
            $baseSlug = $slug;
            $counter = 1;
            while (Organization::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $leaderLabel = ! empty($data['leader_label']) ? trim($data['leader_label']) : 'Kepala Sekolah';
            $plan = in_array($data['plan'] ?? 'free', ['internal', 'free', 'paid'], true) ? $data['plan'] : 'free';
            $quotaMinutes = isset($data['quota_minutes']) ? max(0, (int) $data['quota_minutes']) : 60;

            // 1. Create Organization
            $organization = Organization::create([
                'name' => $name,
                'slug' => $slug,
                'address_text' => ! empty($data['address_text']) ? trim($data['address_text']) : null,
                'kop_path' => null,
                'plan' => $plan,
                'quota_minutes' => $quotaMinutes,
                'parent_id' => null,
                'settings' => [
                    'leader_label' => $leaderLabel,
                ],
            ]);

            // 2. Create Admin User
            $rawAdminPassword = ! empty($data['admin_password']) ? $data['admin_password'] : Str::random(10);
            $admin = User::withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'name' => trim($data['admin_name']),
                'email' => strtolower(trim($data['admin_email'])),
                'password' => $rawAdminPassword,
                'role' => 'admin',
                'is_active' => true,
            ]);

            // 3. Create Leader User (Optional)
            $leader = null;
            $rawLeaderPassword = null;
            if (! empty($data['leader_email']) && ! empty($data['leader_name'])) {
                $rawLeaderPassword = ! empty($data['leader_password']) ? $data['leader_password'] : Str::random(10);
                $leader = User::withoutGlobalScopes()->create([
                    'organization_id' => $organization->id,
                    'name' => trim($data['leader_name']),
                    'email' => strtolower(trim($data['leader_email'])),
                    'password' => $rawLeaderPassword,
                    'role' => 'pimpinan',
                    'is_active' => true,
                ]);

                // Create Leader default signatory
                OrganizationSignatory::withoutGlobalScopes()->create([
                    'organization_id' => $organization->id,
                    'name' => $leader->name,
                    'position' => $leaderLabel,
                    'role_label' => 'Mengetahui',
                    'sort' => 1,
                ]);
            }

            // 4. Create Admin default signatory
            OrganizationSignatory::withoutGlobalScopes()->create([
                'organization_id' => $organization->id,
                'name' => $admin->name,
                'position' => 'Admin Lembaga / Tata Usaha',
                'role_label' => 'Notulis',
                'sort' => $leader ? 2 : 1,
            ]);

            // 5. Record Audit
            Audit::record('organization_created', null, [
                'organization_id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'plan' => $organization->plan,
                'quota_minutes' => $organization->quota_minutes,
                'admin_email' => $admin->email,
            ]);

            return [
                'organization' => $organization,
                'admin' => $admin,
                'leader' => $leader,
                'raw_admin_password' => $rawAdminPassword,
                'raw_leader_password' => $rawLeaderPassword,
            ];
        });
    }
}
