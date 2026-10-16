<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Support\Audit;
use App\Support\CheckinCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    /**
     * Display the host attendance screen with rotating QR code and live updates.
     */
    public function hostScreen(Meeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $code = CheckinCode::generate($meeting->id);
        $checkinUrl = route('checkin.show', ['meeting' => $meeting->id, 'code' => $code]);

        $attendees = $meeting->attendees()->orderBy('name')->get();

        return view('meetings.host_qr', [
            'meeting' => $meeting,
            'initialCode' => $code,
            'initialUrl' => $checkinUrl,
            'secondsRemaining' => CheckinCode::secondsRemaining(),
            'attendees' => $attendees,
        ]);
    }

    /**
     * Polling endpoint (called every 5s by host screen) returning fresh QR code and attendee stats.
     */
    public function qrData(Meeting $meeting): JsonResponse
    {
        $this->authorize('view', $meeting);

        $code = CheckinCode::generate($meeting->id);
        $url = route('checkin.show', ['meeting' => $meeting->id, 'code' => $code]);

        $attendees = $meeting->attendees()
            ->whereNotNull('checked_in_at')
            ->latest('checked_in_at')
            ->get(['id', 'name', 'position', 'status', 'method', 'checked_in_at', 'is_guest', 'guest_status']);

        $totalInvited = $meeting->attendees()->where('is_guest', false)->count();
        $totalPresent = $meeting->attendees()->whereIn('status', ['hadir', 'terlambat'])->count();
        $pendingGuests = $meeting->attendees()->where('is_guest', true)->where('guest_status', 'pending')->count();

        return response()->json([
            'code' => $code,
            'url' => $url,
            'seconds_remaining' => CheckinCode::secondsRemaining(),
            'is_closed' => $meeting->absensi_closed_at !== null,
            'total_invited' => $totalInvited,
            'total_present' => $totalPresent,
            'pending_guests_count' => $pendingGuests,
            'attendees' => $attendees,
        ]);
    }

    /**
     * Manual attendance marking by host for attendees without smartphone.
     */
    public function manualCheckin(Request $request, Meeting $meeting, Attendee $attendee): RedirectResponse
    {
        $this->authorize('manageAttendees', $meeting);
        abort_unless($attendee->meeting_id === $meeting->id, 404);

        $validated = $request->validate([
            'status' => ['required', 'in:hadir,terlambat,izin,sakit,dinas_luar,tanpa_keterangan'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $attendee->update([
            'status' => $validated['status'],
            'checked_in_at' => now(),
            'method' => 'manual',
            'note' => $validated['note'] ?? $attendee->note,
        ]);

        Audit::record('attendee_manual_checkin', $meeting->id, [
            'attendee_id' => $attendee->id,
            'name' => $attendee->name,
            'status' => $validated['status'],
            'method' => 'manual',
            'marked_by' => auth()->id(),
        ]);

        return back()->with('success', "Presensi manual '{$attendee->name}' tercatat sebagai {$validated['status']}.");
    }

    /**
     * Approve a pending guest attendee.
     */
    public function approveGuest(Request $request, Meeting $meeting, Attendee $attendee): RedirectResponse
    {
        $this->authorize('manageAttendees', $meeting);
        abort_unless($attendee->meeting_id === $meeting->id, 404);

        $status = $request->input('status', 'hadir');
        if (! in_array($status, ['hadir', 'terlambat'], true)) {
            $status = 'hadir';
        }

        $attendee->update([
            'guest_status' => 'approved',
            'status' => $status,
        ]);

        Audit::record('guest_approved', $meeting->id, [
            'attendee_id' => $attendee->id,
            'name' => $attendee->name,
            'approved_by' => auth()->id(),
            'status' => $status,
        ]);

        return back()->with('success', "Tamu '{$attendee->name}' telah disetujui hadir.");
    }

    /**
     * Reject a pending guest attendee.
     */
    public function rejectGuest(Meeting $meeting, Attendee $attendee): RedirectResponse
    {
        $this->authorize('manageAttendees', $meeting);
        abort_unless($attendee->meeting_id === $meeting->id, 404);

        $attendee->update([
            'guest_status' => 'rejected',
            'status' => null,
        ]);

        Audit::record('guest_rejected', $meeting->id, [
            'attendee_id' => $attendee->id,
            'name' => $attendee->name,
            'rejected_by' => auth()->id(),
        ]);

        return back()->with('success', "Pendaftaran tamu '{$attendee->name}' telah ditolak.");
    }

    /**
     * Close attendance, setting absensi_closed_at and assigning status for un-checked-in attendees.
     */
    public function closeAttendance(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $now = now();
        $meeting->update([
            'absensi_closed_at' => $now,
        ]);

        // Bulk assign status to remaining un-checked-in attendees
        $unattended = $meeting->attendees()->whereNull('status')->get();
        $customStatuses = $request->input('unattended', []);

        /** @var Attendee $att */
        foreach ($unattended as $att) {
            $newStatus = $customStatuses[$att->id]['status'] ?? 'tanpa_keterangan';
            $note = $customStatuses[$att->id]['note'] ?? null;

            if (! in_array($newStatus, ['izin', 'sakit', 'dinas_luar', 'tanpa_keterangan'], true)) {
                $newStatus = 'tanpa_keterangan';
            }

            $att->update([
                'status' => $newStatus,
                'note' => $note,
            ]);
        }

        Audit::record('attendance_closed', $meeting->id, [
            'closed_by' => auth()->id(),
            'closed_at' => $now->toIso8601String(),
            'unattended_count' => $unattended->count(),
        ]);

        return redirect()->route('meetings.attendance.recap', $meeting)->with('success', 'Absensi telah resmi ditutup. Rekapitulasi kehadiran siap ditinjau.');
    }

    /**
     * Update/correct attendance status after closing (reason is mandatory).
     */
    public function updateStatus(Request $request, Meeting $meeting, Attendee $attendee): RedirectResponse
    {
        $this->authorize('update', $meeting);
        abort_unless($attendee->meeting_id === $meeting->id, 404);

        // When attendance is closed, reason is strictly required!
        $rules = [
            'status' => ['required', 'in:hadir,terlambat,izin,sakit,dinas_luar,tanpa_keterangan'],
        ];

        if ($meeting->absensi_closed_at !== null) {
            $rules['reason'] = ['required', 'string', 'min:3', 'max:500'];
        } else {
            $rules['reason'] = ['nullable', 'string', 'max:500'];
        }

        $validated = $request->validate($rules);

        $oldStatus = $attendee->status;
        $newStatus = $validated['status'];
        $reason = $validated['reason'] ?? null;

        $attendee->update([
            'status' => $newStatus,
            'note' => $reason ?: $attendee->note,
        ]);

        Audit::record('attendance_corrected', $meeting->id, [
            'attendee_id' => $attendee->id,
            'name' => $attendee->name,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'corrected_by' => auth()->id(),
        ]);

        return back()->with('success', "Status kehadiran '{$attendee->name}' berhasil diperbarui ke {$newStatus}.");
    }

    /**
     * Display attendance recap view (suitable for official report / berita acara).
     */
    public function recap(Meeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $attendees = $meeting->attendees()->orderBy('name')->get();

        $summary = [
            'total' => $attendees->count(),
            'hadir' => $attendees->where('status', 'hadir')->count(),
            'terlambat' => $attendees->where('status', 'terlambat')->count(),
            'izin' => $attendees->where('status', 'izin')->count(),
            'sakit' => $attendees->where('status', 'sakit')->count(),
            'dinas_luar' => $attendees->where('status', 'dinas_luar')->count(),
            'tanpa_keterangan' => $attendees->where('status', 'tanpa_keterangan')->count(),
            'belum_absen' => $attendees->whereNull('status')->count(),
        ];

        $totalPresent = $summary['hadir'] + $summary['terlambat'];
        $attendanceRate = $summary['total'] > 0 ? round(($totalPresent / $summary['total']) * 100, 1) : 0;

        return view('meetings.recap', [
            'meeting' => $meeting,
            'attendees' => $attendees,
            'summary' => $summary,
            'attendanceRate' => $attendanceRate,
        ]);
    }
}
