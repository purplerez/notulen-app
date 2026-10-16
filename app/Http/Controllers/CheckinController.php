<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\OrgContact;
use App\Support\Audit;
use App\Support\CheckinCode;
use App\Support\PhoneNumber;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CheckinController extends Controller
{
    /**
     * Show public check-in page for attendees.
     */
    public function show(Request $request, int $meetingId): View
    {
        // Meeting is retrieved without tenant auth global scope (public route)
        $meeting = Meeting::withoutGlobalScopes()->findOrFail($meetingId);

        $code = (string) $request->query('code', '');
        $isCodeValid = CheckinCode::isValid($meeting->id, $code);
        $isClosed = $meeting->absensi_closed_at !== null;

        // List of invited attendees not yet checked in
        $unattended = $meeting->attendees()
            ->withoutGlobalScopes()
            ->whereNull('checked_in_at')
            ->orderBy('name')
            ->get();

        return view('checkin.show', [
            'meeting' => $meeting,
            'code' => $code,
            'isCodeValid' => $isCodeValid,
            'isClosed' => $isClosed,
            'unattended' => $unattended,
        ]);
    }

    /**
     * Process participant check-in submission.
     */
    public function store(Request $request, int $meetingId): View|RedirectResponse
    {
        $meeting = Meeting::withoutGlobalScopes()->findOrFail($meetingId);

        // 1. Check if attendance is already closed
        if ($meeting->absensi_closed_at !== null) {
            throw ValidationException::withMessages([
                'code' => 'Absensi untuk rapat ini sudah ditutup oleh host.',
            ]);
        }

        // 2. Validate HMAC check-in code
        $code = (string) $request->input('code', '');
        if (! CheckinCode::isValid($meeting->id, $code)) {
            throw ValidationException::withMessages([
                'code' => 'Kode QR absensi sudah kedaluwarsa atau tidak valid. Silakan scan ulang kode QR terbaru di layar.',
            ]);
        }

        // 3. Rate limiting per IP
        $ipThrottleKey = 'checkin-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipThrottleKey, 15)) {
            $seconds = RateLimiter::availableIn($ipThrottleKey);
            throw ValidationException::withMessages([
                'phone' => "Terlalu banyak permintaan presensi dari perangkat ini. Silakan tunggu {$seconds} detik.",
            ]);
        }
        RateLimiter::hit($ipThrottleKey, 60);

        // 4. Validate input & normalize phone
        $rawPhone = (string) $request->input('phone', '');
        $normalizedPhone = PhoneNumber::normalize($rawPhone);

        if (! $normalizedPhone) {
            throw ValidationException::withMessages([
                'phone' => 'Nomor WhatsApp / telepon seluler tidak valid. Contoh format: 081234567890.',
            ]);
        }

        // 5. Rate limiting per phone
        $phoneThrottleKey = 'checkin-phone:'.$normalizedPhone;
        if (RateLimiter::tooManyAttempts($phoneThrottleKey, 5)) {
            $seconds = RateLimiter::availableIn($phoneThrottleKey);
            throw ValidationException::withMessages([
                'phone' => "Terlalu banyak percobaan untuk nomor ini. Silakan tunggu {$seconds} detik.",
            ]);
        }
        RateLimiter::hit($phoneThrottleKey, 60);

        // 6. Determine status: hadir vs terlambat (Instruction 3)
        $status = 'hadir';
        if ($meeting->start_time) {
            $meetingDateStr = Carbon::parse($meeting->meeting_date)->format('Y-m-d');
            $startTimeStr = substr((string) $meeting->start_time, 0, 5);
            $scheduledStart = Carbon::parse("{$meetingDateStr} {$startTimeStr}");
            $lateThreshold = $scheduledStart->copy()->addMinutes((int) ($meeting->late_after_minutes ?? 15));

            if (now()->greaterThan($lateThreshold)) {
                $status = 'terlambat';
            }
        }

        // 7. Match attendee
        /** @var Attendee|null $attendee */
        $attendee = null;
        if ($request->filled('attendee_id')) {
            $attendee = Attendee::withoutGlobalScopes()
                ->where('meeting_id', $meeting->id)
                ->where('id', $request->input('attendee_id'))
                ->first();
        }

        if (! $attendee) {
            $attendee = Attendee::withoutGlobalScopes()
                ->where('meeting_id', $meeting->id)
                ->where('phone_e164', $normalizedPhone)
                ->first();
        }

        // Case A: Attendee was invited in this meeting
        if ($attendee) {
            if ($attendee->checked_in_at !== null) {
                return view('checkin.success', [
                    'meeting' => $meeting,
                    'attendee' => $attendee,
                    'alreadyCheckedIn' => true,
                    'isGuest' => (bool) $attendee->is_guest,
                ]);
            }

            $attendee->update([
                'status' => $status,
                'checked_in_at' => now(),
                'method' => 'qr',
                'phone_e164' => $normalizedPhone,
            ]);

            Audit::record(
                'attendee_checkin',
                $meeting->id,
                [
                    'attendee_id' => $attendee->id,
                    'name' => $attendee->name,
                    'status' => $status,
                    'method' => 'qr',
                    'organization_id' => $meeting->organization_id,
                ],
                actorAttendeeId: $attendee->id
            );

            return view('checkin.success', [
                'meeting' => $meeting,
                'attendee' => $attendee,
                'alreadyCheckedIn' => false,
                'isGuest' => false,
            ]);
        }

        // Case B: Not in invitation list -> Guest
        // Recognize from org_contacts if exists
        $orgContact = OrgContact::withoutGlobalScopes()
            ->where('organization_id', $meeting->organization_id)
            ->where('phone_e164', $normalizedPhone)
            ->first();

        $guestName = $request->input('name') ?: ($orgContact->name ?? 'Tamu');
        $guestPosition = $request->input('position') ?? ($orgContact->position ?? null);

        /** @var Attendee $guestAttendee */
        $guestAttendee = Attendee::withoutGlobalScopes()->create([
            'organization_id' => $meeting->organization_id,
            'meeting_id' => $meeting->id,
            'contact_id' => $orgContact->id ?? null,
            'name' => $guestName,
            'position' => $guestPosition,
            'phone_e164' => $normalizedPhone,
            'status' => null, // Pending host approval
            'checked_in_at' => now(),
            'method' => 'qr',
            'is_guest' => true,
            'guest_status' => 'pending',
            'note' => $status === 'terlambat' ? 'Presensi melewati batas keterlambatan' : null,
        ]);

        Audit::record(
            'guest_checkin_pending',
            $meeting->id,
            [
                'attendee_id' => $guestAttendee->id,
                'name' => $guestName,
                'phone' => $normalizedPhone,
                'organization_id' => $meeting->organization_id,
            ],
            actorAttendeeId: $guestAttendee->id
        );

        return view('checkin.success', [
            'meeting' => $meeting,
            'attendee' => $guestAttendee,
            'alreadyCheckedIn' => false,
            'isGuest' => true,
            'pendingApproval' => true,
        ]);
    }
}
