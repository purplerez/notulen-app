<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\TranscriptSegment;
use App\Support\Audit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class TranscriptController extends Controller
{
    /**
     * Display the raw transcript and comparison/editing screen for the meeting.
     */
    public function show(Meeting $meeting): View
    {
        // Enforce authorization (notulis creator & pimpinan only; admin forbidden)
        $this->authorize('viewTranscript', $meeting);

        // Record audit log on every transcript opening
        Audit::record('transcript_view', $meeting->id);

        $canViewAudio = Gate::allows('viewAudio', $meeting);
        $audioAvailable = $canViewAudio
            && ! empty($meeting->merged_audio_path)
            && $meeting->audio_deleted_at === null
            && ($meeting->audio_expires_at === null || $meeting->audio_expires_at->isFuture());

        $audioStreamUrl = $audioAvailable
            ? URL::temporarySignedRoute('meetings.audio.stream', now()->addMinutes(30), ['meeting' => $meeting->id])
            : null;

        $segments = $meeting->transcriptSegments()
            ->with('editor')
            ->orderBy('start_sec')
            ->get();

        $stats = $meeting->getTranscriptStats();
        $canEdit = Gate::allows('updateTranscript', $meeting);

        return view('meetings.transcript', [
            'meeting' => $meeting,
            'segments' => $segments,
            'stats' => $stats,
            'canEdit' => $canEdit,
            'audioAvailable' => $audioAvailable,
            'audioStreamUrl' => $audioStreamUrl,
        ]);
    }

    /**
     * Update an individual transcript segment with manual corrections.
     * The original text is NEVER overwritten.
     */
    public function update(Request $request, Meeting $meeting, TranscriptSegment $segment): RedirectResponse|JsonResponse
    {
        $this->authorize('updateTranscript', $meeting);

        abort_unless((int) $segment->meeting_id === (int) $meeting->id, 404);

        $validated = $request->validate([
            'edited_text' => ['required', 'string'],
        ]);

        // Preserve original text; write only to edited_text and edited_by
        $segment->update([
            'edited_text' => $validated['edited_text'],
            'edited_by' => auth()->id(),
        ]);

        Audit::record('transcript_edit', $meeting->id, [
            'segment_id' => $segment->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Transkrip segmen berhasil disimpan.',
                'segment' => $segment->fresh()->load('editor'),
                'stats' => $meeting->fresh()->getTranscriptStats(),
            ]);
        }

        return redirect()->route('meetings.transcript.show', $meeting)->with('success', 'Transkrip segmen berhasil disimpan.');
    }

    /**
     * Reset an edited segment back to its original STT text.
     */
    public function reset(Request $request, Meeting $meeting, TranscriptSegment $segment): RedirectResponse|JsonResponse
    {
        $this->authorize('updateTranscript', $meeting);

        abort_unless((int) $segment->meeting_id === (int) $meeting->id, 404);

        $segment->update([
            'edited_text' => null,
            'edited_by' => null,
        ]);

        Audit::record('transcript_reset', $meeting->id, [
            'segment_id' => $segment->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Transkrip segmen dikembalikan ke teks asli.',
                'segment' => $segment->fresh()->load('editor'),
                'stats' => $meeting->fresh()->getTranscriptStats(),
            ]);
        }

        return redirect()->route('meetings.transcript.show', $meeting)->with('success', 'Transkrip segmen dikembalikan ke teks asli.');
    }
}
