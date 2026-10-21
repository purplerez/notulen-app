<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view the organization details and kop.
     */
    public function view(mixed $user, Organization $organization): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        return (int) $user->organization_id === (int) $organization->id;
    }

    /**
     * Determine whether the user can update the organization settings or kop.
     * Only Admin of the same organization can update.
     */
    public function update(mixed $user, Organization $organization): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        return (int) $user->organization_id === (int) $organization->id && $user->isAdmin();
    }
}
