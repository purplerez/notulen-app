<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

/**
 * Policy for Raw Materials (Raw Audio & Raw Transcript).
 *
 * Core Rule 4: Raw audio and raw transcripts are NEVER accessible to admin
 * or super admin. Only notulis who created the meeting and pimpinan of the same
 * organization are permitted under specific retention constraints.
 */
class RawMaterialPolicy
{
    /**
     * Determine whether the user can access and play raw audio.
     */
    public function viewAudio(mixed $user, Meeting $meeting): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        // Multitenant isolation: user must belong to the meeting's organization
        if ((int) $user->organization_id !== (int) $meeting->organization_id) {
            return false;
        }

        // Admin is strictly forbidden (AGENTS.md Rule 4 & docs/keputusan.md)
        if ($user->isAdmin()) {
            return false;
        }

        // Only the notulis who created the meeting or pimpinan of the organization
        $isCreatorNotulis = $user->isNotulis() && (int) $meeting->created_by === (int) $user->id;
        $isPimpinan = $user->isPimpinan();

        if (! $isCreatorNotulis && ! $isPimpinan) {
            return false;
        }

        // Audio must not have been purged/deleted
        if ($meeting->audio_deleted_at !== null) {
            return false;
        }

        // Audio must not have expired
        if ($meeting->audio_expires_at === null || $meeting->audio_expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can access the raw transcript.
     * Raw transcripts are kept indefinitely without expiration.
     */
    public function viewTranscript(mixed $user, Meeting $meeting): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        // Multitenant isolation: user must belong to the meeting's organization
        if ((int) $user->organization_id !== (int) $meeting->organization_id) {
            return false;
        }

        // Admin is strictly forbidden (AGENTS.md Rule 4 & docs/keputusan.md)
        if ($user->isAdmin()) {
            return false;
        }

        // Only the notulis who created the meeting or pimpinan of the organization
        $isCreatorNotulis = $user->isNotulis() && (int) $meeting->created_by === (int) $user->id;
        $isPimpinan = $user->isPimpinan();

        return $isCreatorNotulis || $isPimpinan;
    }

    /**
     * Determine whether the user can edit the transcript of the meeting.
     * Strictly restricted to the Notulis creator. Pimpinan is read-only.
     * Admin and super admin are forbidden.
     */
    public function updateTranscript(mixed $user, Meeting $meeting): bool
    {
        if (! ($user instanceof User)) {
            return false;
        }

        // Multitenant isolation
        if ((int) $user->organization_id !== (int) $meeting->organization_id) {
            return false;
        }

        // Admin is strictly forbidden
        if ($user->isAdmin()) {
            return false;
        }

        // Only the notulis who created the meeting is allowed to edit
        return $user->isNotulis() && (int) $meeting->created_by === (int) $user->id;
    }
}
