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

    /**
     * Determine whether the user can record the meeting and upload audio segments.
     * Strictly restricted to the Notulis who created the meeting within the same tenant.
     * Admin, super admin, and pimpinan cannot access raw audio or record.
     */
    public function record(User $user, Meeting $meeting): bool
    {
        if ($user->organization_id !== $meeting->organization_id) {
            return false;
        }

        return $user->isNotulis() && $meeting->created_by === $user->id;
    }

    /**
     * Determine whether the user can play raw audio of the meeting.
     * Enforced by RawMaterialPolicy.
     */
    public function viewAudio(mixed $user, Meeting $meeting): bool
    {
        return app(RawMaterialPolicy::class)->viewAudio($user, $meeting);
    }

    /**
     * Determine whether the user can view the raw transcript of the meeting.
     * Enforced by RawMaterialPolicy.
     */
    public function viewTranscript(mixed $user, Meeting $meeting): bool
    {
        return app(RawMaterialPolicy::class)->viewTranscript($user, $meeting);
    }
}
