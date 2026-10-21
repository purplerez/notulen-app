<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view the organization details and kop.
     */
    public function view(User $user, Organization $organization): bool
    {
        return (int) $user->organization_id === (int) $organization->id;
    }

    /**
     * Determine whether the user can update the organization settings or kop.
     * Only Admin of the same organization can update.
     */
    public function update(User $user, Organization $organization): bool
    {
        return (int) $user->organization_id === (int) $organization->id && $user->isAdmin();
    }
}
