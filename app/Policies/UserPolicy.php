<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users in their organization.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isPimpinan();
    }

    /**
     * Determine whether the user can view a specific user.
     */
    public function view(User $user, User $model): bool
    {
        return $user->organization_id === $model->organization_id
            && ($user->isAdmin() || $user->isPimpinan() || $user->id === $model->id);
    }

    /**
     * Determine whether the user can create user accounts (notulis).
     * Admin and pimpinan are allowed.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isPimpinan();
    }

    /**
     * Determine whether the user can update a user.
     */
    public function update(User $user, User $model): bool
    {
        return $user->organization_id === $model->organization_id
            && ($user->isAdmin() || $user->id === $model->id);
    }

    /**
     * Determine whether the user can delete a user.
     */
    public function delete(User $user, User $model): bool
    {
        return $user->organization_id === $model->organization_id
            && $user->isAdmin()
            && $user->id !== $model->id;
    }
}
