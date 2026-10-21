<?php

namespace App\Policies;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\User;

class AttendeePolicy
{
    /**
     * Determine whether the user can add an attendee to the meeting.
     */
    public function create(mixed $user, Meeting $meeting): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

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
     * Determine whether the user can remove an attendee from the meeting.
     */
    public function delete(mixed $user, Attendee $attendee): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        if ($user->organization_id !== $attendee->organization_id) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isNotulis()) {
            $meeting = $attendee->meeting;

            return $meeting instanceof Meeting && $meeting->created_by === $user->id;
        }

        return false;
    }
}
