<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\Minute;
use App\Models\User;

class MinutePolicy
{
    /**
     * Determine whether the user can view the minute.
     * - Final minutes: creator notulis, admin, and pimpinan of the same organization.
     * - Draft minutes: creator notulis (for v1 or their own revision), or admin (for their own revision draft).
     *   Pimpinan can NEVER view draft minutes.
     */
    public function view(mixed $user, Minute $minute): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        if ((int) $user->organization_id !== (int) $minute->organization_id) {
            return false;
        }

        $meeting = $minute->meeting;
        if (! $meeting) {
            return false;
        }

        $isCreatorNotulis = $user->isNotulis() && (int) $meeting->created_by === (int) $user->id;

        if ($minute->isFinal()) {
            return $isCreatorNotulis || $user->isAdmin() || $user->isPimpinan();
        }

        // Draft minutes: only creator notulis, or the user who initiated the revision draft
        return $isCreatorNotulis || ((int) $minute->created_by === (int) $user->id && $user->isAdmin());
    }

    /**
     * Determine whether the user can update the draft minute.
     * - Version 1: creator notulis only.
     * - Version > 1 (revision): the user who initiated the revision draft.
     * - Finalized minutes cannot be updated.
     */
    public function update(mixed $user, Minute $minute): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        if ((int) $user->organization_id !== (int) $minute->organization_id) {
            return false;
        }

        if (! $minute->isDraft()) {
            return false;
        }

        $meeting = $minute->meeting;
        if (! $meeting) {
            return false;
        }

        if ($minute->version === 1) {
            return $user->isNotulis() && (int) $meeting->created_by === (int) $user->id;
        }

        // Revision draft: creator of the revision
        return (int) $minute->created_by === (int) $user->id;
    }

    /**
     * Determine whether the user can finalize the minute.
     * - Version 1: strictly the creator notulis. Admin is FORBIDDEN.
     * - Version > 1: the user who initiated the revision (creator notulis or admin).
     * - Pimpinan is always forbidden.
     */
    public function finalize(mixed $user, Minute $minute): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        if ((int) $user->organization_id !== (int) $minute->organization_id) {
            return false;
        }

        if (! $minute->isDraft()) {
            return false;
        }

        $meeting = $minute->meeting;
        if (! $meeting) {
            return false;
        }

        if ($minute->version === 1) {
            // Admin cannot finalize initial draft
            return $user->isNotulis() && (int) $meeting->created_by === (int) $user->id;
        }

        // Revisions: the author of the revision
        return (int) $minute->created_by === (int) $user->id;
    }

    /**
     * Determine whether the user can create a new revision of the finalized minutes.
     * - Meeting must have at least one finalized minute.
     * - Creator notulis or Admin of the same organization.
     * - Pimpinan is forbidden.
     */
    public function revise(mixed $user, Meeting $meeting): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        if ((int) $user->organization_id !== (int) $meeting->organization_id) {
            return false;
        }

        if (! $meeting->isMinutesFinal()) {
            return false;
        }

        $isCreatorNotulis = $user->isNotulis() && (int) $meeting->created_by === (int) $user->id;
        $isAdmin = $user->isAdmin();

        return $isCreatorNotulis || $isAdmin;
    }

    /**
     * Determine whether the user can mark the minute as 'diketahui'.
     * - Strictly Pimpinan of the same organization.
     * - Minute must be finalized.
     * - Admin and notulis are forbidden.
     */
    public function acknowledge(mixed $user, Minute $minute): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        if ((int) $user->organization_id !== (int) $minute->organization_id) {
            return false;
        }

        if (! $minute->isFinal()) {
            return false;
        }

        return $user->isPimpinan();
    }
}
