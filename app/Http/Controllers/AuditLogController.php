<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Meeting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * Display a listing of audit logs according to role permissions and filters.
     * Pimpinan and Admin see all logs of their organization.
     * Notulis sees only logs associated with meetings they created.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $user = $request->user();

        $query = AuditLog::query()
            ->with(['actorUser', 'actorAttendee', 'meeting']);

        // Scope logs: Notulis can only see logs of meetings they created
        if ($user->isNotulis()) {
            $myMeetingIds = Meeting::where('created_by', $user->id)->pluck('id');
            $query->whereIn('meeting_id', $myMeetingIds);
        }

        // Filter: meeting_id
        if ($request->filled('meeting_id')) {
            $meetingId = (int) $request->input('meeting_id');
            $query->where('meeting_id', $meetingId);
        }

        // Filter: action
        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        // Filter: actor role
        if ($request->filled('role')) {
            $role = (string) $request->input('role');
            if ($role === 'attendee') {
                $query->whereNotNull('actor_attendee_id');
            } else {
                $query->whereHas('actorUser', function ($q) use ($role) {
                    $q->where('role', $role);
                });
            }
        }

        // Filter: date
        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        $logs = $query->latest('id')->paginate(20)->withQueryString();

        // Accessible meetings for filter dropdown
        $meetingsQuery = Meeting::query()->orderBy('meeting_date', 'desc');
        if ($user->isNotulis()) {
            $meetingsQuery->where('created_by', $user->id);
        }
        $availableMeetings = $meetingsQuery->get(['id', 'title', 'meeting_date']);

        $availableActions = [
            'audio_play' => 'Pemutaran Audio',
            'transcript_view' => 'Melihat Transkrip Mentah',
            'transcript_edit' => 'Penyuntingan Transkrip',
            'minute_finalize' => 'Finalisasi Notulen',
            'minute_revise' => 'Pengajuan Revisi Notulen',
            'minute_acknowledge' => 'Konfirmasi Diketahui Notulen',
            'attendee_checkin' => 'Presensi Mandiri QR',
            'attendee_manual_checkin' => 'Presensi Manual',
            'attendance_closed' => 'Penutupan Absensi',
            'attendance_corrected' => 'Koreksi Data Absensi',
            'meeting_create' => 'Pembuatan Room Rapat',
            'meeting_delete' => 'Penghapusan Rapat',
            'recording_start' => 'Mulai Rekaman Rapat',
            'recording_finish' => 'Selesai Rekaman Rapat',
            'audio_purge' => 'Pembersihan Audio (Retensi)',
            'login' => 'Masuk Aplikasi (Login)',
            'logout' => 'Keluar Aplikasi (Logout)',
        ];

        return view('audit-logs.index', [
            'logs' => $logs,
            'availableMeetings' => $availableMeetings,
            'availableActions' => $availableActions,
            'filters' => [
                'meeting_id' => $request->input('meeting_id'),
                'action' => $request->input('action'),
                'role' => $request->input('role'),
                'date' => $request->input('date'),
            ],
        ]);
    }
}
