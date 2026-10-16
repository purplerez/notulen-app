<?php

namespace App\Policies;

use App\Models\User;

class MinutePolicy
{
    /**
     * Determine whether the user can view any minutes.
     */
    public function viewAny(User $user): bool
    {
        // TODO: Implement when minute features are developed.
        return false;
    }

    /**
     * Determine whether the user can update minutes.
     */
    public function update(User $user): bool
    {
        // TODO: Implement when minute features are developed.
        return false;
    }
}
