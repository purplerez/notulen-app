<?php

namespace App\Policies;

use App\Models\User;

/**
 * Policy for Raw Materials (Raw Audio & Raw Transcript).
 *
 * Core Rule 4: Raw audio and raw transcripts are NEVER accessible to admin
 * or super admin. Only notulis and pimpinan under specific constraints.
 */
class RawMaterialPolicy
{
    /**
     * Determine whether the user can access raw audio.
     */
    public function viewAudio(User $user): bool
    {
        // Admins are strictly forbidden.
        if ($user->isAdmin()) {
            return false;
        }

        // TODO: Implement meeting-level ownership checks when meetings are developed.
        return false;
    }

    /**
     * Determine whether the user can access raw transcript.
     */
    public function viewTranscript(User $user): bool
    {
        // Admins are strictly forbidden.
        if ($user->isAdmin()) {
            return false;
        }

        // TODO: Implement meeting-level ownership checks when meetings are developed.
        return false;
    }
}
