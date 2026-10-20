<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    /**
     * Determine whether the user can view any audit logs in their organization.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isPimpinan() || $user->isNotulis();
    }

    /**
     * Determine whether the user can view a specific audit log.
     * Admin and pimpinan can view all logs in their organization.
     * Notulis can only view logs tied to meetings they created.
     */
    public function view(User $user, AuditLog $auditLog): bool
    {
        if ((int) $user->organization_id !== (int) $auditLog->organization_id) {
            return false;
        }

        if ($user->isAdmin() || $user->isPimpinan()) {
            return true;
        }

        if ($user->isNotulis()) {
            if (! $auditLog->meeting_id) {
                return false;
            }

            $meeting = $auditLog->meeting;

            return $meeting && (int) $meeting->created_by === (int) $user->id;
        }

        return false;
    }

    /**
     * Audit logs are append-only via system helper, not directly created via controller.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Audit logs cannot be updated.
     */
    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    /**
     * Audit logs cannot be deleted.
     */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
