<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\ShareLink;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ShareLinkController extends Controller
{
    /**
     * Create a new public share link for the meeting minutes.
     */
    public function store(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('manageShareLinks', $meeting);

        $validated = $request->validate([
            'access_mode' => ['required', 'in:phone,code'],
            'recipients_scope' => ['required', 'in:hadir,semua_undangan'],
            'expires_at' => ['required', 'date', 'after:now'],
        ], [
            'access_mode.required' => 'Pilih mode akses tautan (Nomor WhatsApp atau PIN).',
            'recipients_scope.required' => 'Pilih cakupan penerima tautan.',
            'expires_at.required' => 'Tentukan batas masa berlaku tautan.',
            'expires_at.after' => 'Batas masa berlaku tautan harus berada di masa depan.',
        ]);

        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);

        $pin = null;
        $codeHash = null;

        if ($validated['access_mode'] === 'code') {
            $pin = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $codeHash = Hash::make($pin);
        }

        // Revoke any previous active links for this meeting
        $meeting->shareLinks()->whereNull('revoked_at')->update([
            'revoked_at' => now(),
        ]);

        $shareLink = ShareLink::create([
            'organization_id' => $meeting->organization_id,
            'meeting_id' => $meeting->id,
            'token_hash' => $tokenHash,
            'access_mode' => $validated['access_mode'],
            'code_hash' => $codeHash,
            'recipients_scope' => $validated['recipients_scope'],
            'expires_at' => $validated['expires_at'],
            'created_by' => auth()->id(),
        ]);

        // Keep meeting's share_expires_at in sync
        $meeting->update([
            'share_expires_at' => $validated['expires_at'],
        ]);

        Audit::record('link_create', $meeting->id, [
            'share_link_id' => $shareLink->id,
            'access_mode' => $shareLink->access_mode,
            'recipients_scope' => $shareLink->recipients_scope,
            'expires_at' => $shareLink->expires_at->toIso8601String(),
            'created_by' => auth()->id(),
        ]);

        // Put token into session for the notulis
        session()->put("share_token_{$meeting->id}", $plainToken);
        session()->flash('plain_share_token', $plainToken);
        session()->flash('plain_share_url', route('public_share.show', ['token' => $plainToken]));

        if ($pin !== null) {
            session()->flash('plain_share_pin', $pin);
        }

        return redirect()->route('meetings.minutes.show', $meeting)->with('success', 'Tautan baca publik peserta berhasil dibuat.');
    }

    /**
     * Revoke an active public share link.
     */
    public function revoke(Request $request, Meeting $meeting, ShareLink $shareLink): RedirectResponse
    {
        $this->authorize('manageShareLinks', $meeting);

        abort_unless((int) $shareLink->meeting_id === (int) $meeting->id, 404);

        $shareLink->update([
            'revoked_at' => now(),
        ]);

        Audit::record('link_revoke', $meeting->id, [
            'share_link_id' => $shareLink->id,
            'revoked_by' => auth()->id(),
        ]);

        session()->forget("share_token_{$meeting->id}");

        return redirect()->route('meetings.minutes.show', $meeting)->with('success', 'Tautan publik peserta berhasil dicabut.');
    }
}
