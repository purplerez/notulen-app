<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;

class Audit
{
    /**
     * Record an append-only audit log entry.
     *
     * @param  string  $action  Action identifier (e.g. 'audio_play', 'transcript_view', 'login', etc.)
     * @param  int|null  $meetingId  Optional meeting ID (no foreign key constraint)
     * @param  array<string, mixed>  $meta  Contextual metadata
     * @param  int|null  $actorAttendeeId  Optional attendee ID (for public attendee actions)
     */
    public static function record(
        string $action,
        ?int $meetingId = null,
        array $meta = [],
        ?int $actorAttendeeId = null
    ): AuditLog {
        $user = auth()->user();
        $orgId = 0;

        if ($user instanceof User) {
            $orgId = $user->organization_id;
        } elseif (isset($meta['organization_id'])) {
            $orgId = (int) $meta['organization_id'];
        }

        return AuditLog::create([
            'organization_id' => $orgId,
            'meeting_id' => $meetingId,
            'actor_user_id' => $user instanceof User ? $user->id : null,
            'actor_attendee_id' => $actorAttendeeId,
            'action' => $action,
            'meta' => ! empty($meta) ? $meta : null,
        ]);
    }
}
