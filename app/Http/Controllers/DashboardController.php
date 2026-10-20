<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the application dashboard tailored to the user's role:
     * - Pimpinan: all organization meetings, attendance, minutes status, acknowledgement status, audio retention, links to final minutes, raw transcripts, and audio.
     * - Admin: all organization meetings, attendance, final minutes, but WITHOUT audio/transcript links and WITHOUT unfinalized draft contents.
     * - Notulis: meetings created by the notulis, draft editing, recording, and audio/transcripts for their own meetings.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $organization = $user->organization;

        $query = Meeting::query()
            ->with(['creator', 'latestFinalMinute.acknowledgements.user', 'currentMinute'])
            ->withCount([
                'attendees as total_attendees_count',
                'attendees as present_attendees_count' => function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNotNull('checked_in_at')
                            ->orWhereIn('status', ['hadir', 'terlambat']);
                    });
                },
            ]);

        // Role scoping: Notulis only sees meetings they created
        if ($user->isNotulis()) {
            $query->where('created_by', $user->id);
        }

        // Filter: title search
        if ($request->filled('q')) {
            $q = trim((string) $request->input('q'));
            $query->where('title', 'like', "%{$q}%");
        }

        // Filter: month (format YYYY-MM)
        if ($request->filled('month')) {
            $month = (string) $request->input('month');
            $query->where('meeting_date', 'like', "{$month}%");
        }

        // Filter: status
        if ($request->filled('status')) {
            $status = (string) $request->input('status');

            if ($status === 'final_minutes') {
                $query->whereHas('minutes', function ($q) {
                    $q->where('status', 'final');
                });
            } elseif ($status === 'draft_minutes') {
                $query->whereDoesntHave('minutes', function ($q) {
                    $q->where('status', 'final');
                });
            } elseif ($status === 'unacknowledged') {
                $query->whereHas('latestFinalMinute', function ($q) use ($user) {
                    $q->whereDoesntHave('acknowledgements', function ($aq) use ($user) {
                        $aq->where('user_id', $user->id);
                    });
                });
            } else {
                $query->where('status', $status);
            }
        }

        $meetings = $query->latest('meeting_date')->latest('id')->paginate(15)->withQueryString();

        // Calculate summary statistics
        $baseStatsQuery = Meeting::query();
        if ($user->isNotulis()) {
            $baseStatsQuery->where('created_by', $user->id);
        }

        $totalMeetings = (clone $baseStatsQuery)->count();

        $finalMinutesCount = (clone $baseStatsQuery)
            ->whereHas('minutes', fn ($q) => $q->where('status', 'final'))
            ->count();

        $unacknowledgedCount = 0;
        if ($user->isPimpinan()) {
            $unacknowledgedCount = (clone $baseStatsQuery)
                ->whereHas('latestFinalMinute', function ($q) use ($user) {
                    $q->whereDoesntHave('acknowledgements', function ($aq) use ($user) {
                        $aq->where('user_id', $user->id);
                    });
                })
                ->count();
        }

        // Generate audio signed URLs for meetings that have audio and where user is authorized (Pimpinan or creator Notulis)
        $audioUrls = [];
        foreach ($meetings as $meeting) {
            if (Gate::allows('viewAudio', $meeting) && $meeting->merged_audio_path) {
                $audioUrls[$meeting->id] = URL::temporarySignedRoute(
                    'meetings.audio.stream',
                    now()->addMinutes(60),
                    ['meeting' => $meeting->id]
                );
            }
        }

        return view('dashboard', [
            'user' => $user,
            'organization' => $organization,
            'meetings' => $meetings,
            'audioUrls' => $audioUrls,
            'stats' => [
                'total_meetings' => $totalMeetings,
                'final_minutes_count' => $finalMinutesCount,
                'unacknowledged_count' => $unacknowledgedCount,
            ],
            'filters' => [
                'q' => $request->input('q', ''),
                'month' => $request->input('month', ''),
                'status' => $request->input('status', ''),
            ],
        ]);
    }
}
