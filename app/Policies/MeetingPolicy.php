<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    /**
     * Determine whether the user can view the meetings list.
     * Admin, pimpinan, and notulis can all view the list of meetings for their tenant.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the meeting room / details.
     * Admin and pimpinan see all meetings in their organization.
     * Notulis can only view meetings they created.
     */
    public function view(User $user, Meeting $meeting): bool
    {
        if ($user->organization_id !== $meeting->organization_id) {
            return false;
        }

        if ($user->isAdmin() || $user->isPimpinan()) {
            return true;
        }

        return $meeting->created_by === $user->id;
    }

    /**
     * Determine whether the user can create meetings.
     * Admin and notulis are allowed. Pimpinan is not allowed.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isNotulis();
    }

    /**
     * Determine whether the user can update the meeting.
     * Admin can update any meeting in their organization.
     * Notulis can only update meetings they created.
     */
    public function update(User $user, Meeting $meeting): bool
    {
        if ($user->organization_id !== $meeting->organization_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isNotulis()) {
            return $meeting->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the meeting.
     * Admin can delete; notulis can delete their own meeting; pimpinan cannot.
     */
    public function delete(User $user, Meeting $meeting): bool
    {
        if ($user->organization_id !== $meeting->organization_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isNotulis()) {
            return $meeting->created_by === $user->id;
        }

        return false;
    }

    /**
     * Determine whether the user can manage attendees for the meeting.
     */
    public function manageAttendees(User $user, Meeting $meeting): bool
    {
        return $this->update($user, $meeting);
    }
}
