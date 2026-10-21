<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\ShareLink;
use App\Support\Audit;
use App\Support\PhoneNumber;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicShareController extends Controller
{
    /**
     * Display the public gate or the read-only minutes view.
     */
    public function show(Request $request, string $token): Response|View
    {
        $tokenHash = hash('sha256', $token);

        /** @var ShareLink|null $shareLink */
        $shareLink = ShareLink::withoutGlobalScopes()->where('token_hash', $tokenHash)->first();

        if (! $shareLink) {
            abort(404, 'Tautan tidak ditemukan.');
        }

        if ($shareLink->isRevoked()) {
            return response()
                ->view('public_share.status', ['type' => 'revoked', 'shareLink' => $shareLink])
                ->withHeaders($this->securityHeaders());
        }

        if ($shareLink->isExpired()) {
            return response()
                ->view('public_share.status', ['type' => 'expired', 'shareLink' => $shareLink])
                ->withHeaders($this->securityHeaders());
        }

        if ($shareLink->isLocked()) {
            return response()
                ->view('public_share.status', ['type' => 'locked', 'shareLink' => $shareLink])
                ->withHeaders($this->securityHeaders());
        }

        $sessionKey = "public_share_pass_{$shareLink->id}";
        $pass = session()->get($sessionKey);

        // If participant has successfully passed gate in this session
        if (is_array($pass) && isset($pass['expires_at']) && $pass['expires_at'] > now()->timestamp) {
            /** @var Meeting $meeting */
            $meeting = Meeting::withoutGlobalScopes()
                ->with(['organization', 'creator'])
                ->findOrFail($shareLink->meeting_id);

            /** @var Minute|null $minute */
            $minute = $meeting->minutes()
                ->withoutGlobalScopes()
                ->where('status', 'final')
                ->reorder('version', 'desc')
                ->first();

            if (! $minute) {
                abort(404, 'Notulen final belum tersedia untuk rapat ini.');
            }

            $minute->load(['actionItems']);
            $isRevised = $minute->version > 1;

            return response()
                ->view('public_share.show', [
                    'meeting' => $meeting,
                    'minute' => $minute,
                    'isRevised' => $isRevised,
                    'token' => $token,
                    'shareLink' => $shareLink,
                ])
                ->withHeaders($this->securityHeaders());
        }

        // Otherwise, render the security verification gate
        return response()
            ->view('public_share.gate', [
                'shareLink' => $shareLink,
                'token' => $token,
            ])
            ->withHeaders($this->securityHeaders());
    }

    /**
     * Verify credentials at the gate (phone number or 6-digit PIN).
     */
    public function verify(Request $request, string $token): RedirectResponse
    {
        $ipKey = 'share_gate_ip_'.$request->ip();
        $rateLimit = (int) config('sharing.rate_limit_per_minute', 10);

        if (RateLimiter::tooManyAttempts($ipKey, $rateLimit)) {
            return back()->withErrors([
                'gate' => 'Terlalu banyak percobaan dari alamat IP Anda. Silakan tunggu beberapa saat.',
            ])->withHeaders($this->securityHeaders());
        }

        RateLimiter::hit($ipKey, 60);

        $tokenHash = hash('sha256', $token);

        /** @var ShareLink|null $shareLink */
        $shareLink = ShareLink::withoutGlobalScopes()->where('token_hash', $tokenHash)->firstOrFail();

        if ($shareLink->isRevoked() || $shareLink->isExpired()) {
            return redirect()->route('public_share.show', ['token' => $token]);
        }

        if ($shareLink->isLocked()) {
            return back()->withErrors([
                'gate' => 'Tautan terkunci sementara karena terlalu banyak percobaan gagal. Silakan coba lagi nanti.',
            ])->withHeaders($this->securityHeaders());
        }

        $isMatch = false;
        /** @var Attendee|null $matchedAttendee */
        $matchedAttendee = null;

        if ($shareLink->access_mode === 'phone') {
            $phoneInput = $request->input('phone');
            $normalized = PhoneNumber::normalize($phoneInput);

            if ($normalized !== null) {
                $attendeesQuery = Attendee::withoutGlobalScopes()
                    ->where('meeting_id', $shareLink->meeting_id)
                    ->where('phone_e164', $normalized);

                if ($shareLink->recipients_scope === 'hadir') {
                    $attendeesQuery->where(function ($q) {
                        $q->whereNotNull('checked_in_at')
                            ->orWhereIn('status', ['hadir', 'terlambat']);
                    });
                }

                // Exclude rejected guests
                $attendeesQuery->where(function ($q) {
                    $q->whereNull('guest_status')->orWhere('guest_status', '!=', 'rejected');
                });

                $matchedAttendee = $attendeesQuery->first();
                if ($matchedAttendee) {
                    $isMatch = true;
                }
            }
        } elseif ($shareLink->access_mode === 'code') {
            $codeInput = trim((string) $request->input('code', ''));
            if ($codeInput !== '' && $shareLink->code_hash && Hash::check($codeInput, $shareLink->code_hash)) {
                $isMatch = true;
            }
        }

        if (! $isMatch) {
            $shareLink->increment('failed_attempts');

            $maxFailed = (int) config('sharing.max_failed_attempts', 5);
            if ($shareLink->failed_attempts >= $maxFailed) {
                $lockoutMinutes = (int) config('sharing.lockout_duration_minutes', 15);
                $shareLink->update([
                    'locked_until' => now()->addMinutes($lockoutMinutes),
                ]);
            }

            return back()->withErrors([
                'gate' => config('sharing.uniform_error_message'),
            ])->withHeaders($this->securityHeaders());
        }

        // On successful match
        $shareLink->update([
            'failed_attempts' => 0,
            'locked_until' => null,
        ]);

        Audit::record('link_open', $shareLink->meeting_id, [
            'share_link_id' => $shareLink->id,
            'access_mode' => $shareLink->access_mode,
            'attendee_id' => $matchedAttendee?->id,
            'attendee_name' => $matchedAttendee?->name,
            'ip' => $request->ip(),
        ], $matchedAttendee?->id);

        $sessionKey = "public_share_pass_{$shareLink->id}";
        $lifetime = (int) config('sharing.session_lifetime_minutes', 60);

        session()->put($sessionKey, [
            'share_link_id' => $shareLink->id,
            'passed_at' => now()->timestamp,
            'expires_at' => now()->addMinutes($lifetime)->timestamp,
            'attendee_id' => $matchedAttendee?->id,
        ]);

        return redirect()->route('public_share.show', ['token' => $token]);
    }

    /**
     * Download the latest finalized minutes PDF via verified session.
     */
    public function pdf(Request $request, string $token): Response
    {
        $tokenHash = hash('sha256', $token);

        /** @var ShareLink|null $shareLink */
        $shareLink = ShareLink::withoutGlobalScopes()->where('token_hash', $tokenHash)->firstOrFail();

        if (! $shareLink->isValid()) {
            abort(403, 'Tautan tidak valid atau telah kedaluwarsa.');
        }

        $sessionKey = "public_share_pass_{$shareLink->id}";
        $pass = session()->get($sessionKey);

        if (! is_array($pass) || ! isset($pass['expires_at']) || $pass['expires_at'] < now()->timestamp) {
            abort(403, 'Sesi verifikasi Anda telah berakhir. Silakan verifikasi ulang.');
        }

        /** @var Meeting $meeting */
        $meeting = Meeting::withoutGlobalScopes()
            ->with(['organization', 'creator', 'attendees'])
            ->findOrFail($shareLink->meeting_id);

        /** @var Minute|null $minute */
        $minute = $meeting->minutes()
            ->withoutGlobalScopes()
            ->where('status', 'final')
            ->reorder('version', 'desc')
            ->firstOrFail();

        $minute->load(['actionItems']);

        $signatories = $meeting->signatories()
            ->withoutGlobalScopes()
            ->orderBy('meeting_signatories.sort')
            ->orderBy('organization_signatories.sort')
            ->get();

        $kopBase64 = null;
        if ($meeting->use_kop && $meeting->organization && $meeting->organization->kop_path) {
            $disk = Storage::disk('local');
            if ($disk->exists($meeting->organization->kop_path)) {
                $mime = $disk->mimeType($meeting->organization->kop_path) ?: 'image/png';
                $kopBase64 = 'data:'.$mime.';base64,'.base64_encode($disk->get($meeting->organization->kop_path));
            }
        }

        Audit::record('link_pdf_download', $meeting->id, [
            'share_link_id' => $shareLink->id,
            'minute_id' => $minute->id,
            'version' => $minute->version,
            'attendee_id' => $pass['attendee_id'] ?? null,
        ], $pass['attendee_id'] ?? null);

        $pdf = Pdf::loadView('pdf.minute', [
            'meeting' => $meeting,
            'minute' => $minute,
            'organization' => $meeting->organization,
            'signatories' => $signatories,
            'kopBase64' => $kopBase64,
        ])->setPaper('a4', 'portrait');

        $filename = "notulen-{$meeting->id}-v{$minute->version}.pdf";

        return $pdf->download($filename)->withHeaders($this->securityHeaders());
    }

    /**
     * Standard protective HTTP response headers.
     *
     * @return array<string, string>
     */
    protected function securityHeaders(): array
    {
        return [
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ];
    }
}
