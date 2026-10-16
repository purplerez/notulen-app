<?php

namespace App\Policies;

use App\Models\User;

class MeetingPolicy
{
    /**
     * Determine whether the user can view any meetings.
     */
    public function viewAny(User $user): bool
    {
        // TODO: Implement when meeting features are developed.
        return false;
    }

    /**
     * Determine whether the user can create meetings.
     */
    public function create(User $user): bool
    {
        // TODO: Implement when meeting features are developed.
        return false;
    }
}
