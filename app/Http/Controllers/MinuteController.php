<?php

namespace App\Http\Controllers;

use App\Models\ActionItem;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\MinuteAcknowledgement;
use App\Models\ShareLink;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class MinuteController extends Controller
{
    /**
     * Display the finalized minute or active version of the meeting minutes.
     */
    public function show(Request $request, Meeting $meeting): View
    {
        $versionParam = $request->query('v');
        $user = auth()->user();

        /** @var Minute|null $minute */
        $minute = null;

        if ($versionParam) {
            $minute = $meeting->minutes()
                ->where('version', (int) $versionParam)
                ->first();
        } else {
            // Default selection:
            // 1. If pimpinan, strictly latest finalized minute
            if ($user && $user->isPimpinan()) {
                $minute = $meeting->latestFinalMinute;
            } else {
                // If notulis/admin, show latest final or latest draft if no final exists
                $minute = $meeting->latestFinalMinute ?: $meeting->currentMinute;
            }
        }

        if (! $minute) {
            abort(404, 'Notulen belum tersedia untuk rapat ini.');
        }

        $this->authorize('view', $minute);

        $minute->load(['creator', 'actionItems', 'acknowledgements.user']);

        // Check if there are other versions (pimpinan only sees finalized versions)
        $versionsQuery = $meeting->minutes()->orderBy('version');
        if ($user && $user->isPimpinan()) {
            $versionsQuery->where('status', 'final');
        }
        $allVersions = $versionsQuery->get(['id', 'version', 'status', 'finalized_at', 'created_by', 'revision_reason']);

        $latestFinal = $meeting->latestFinalMinute;
        $hasNewerFinalRevision = $latestFinal && $latestFinal->version > $minute->version;

        $isAcknowledgedByMe = $user ? $minute->isAcknowledgedBy($user) : false;

        // Check if there is an active draft revision being worked on
        $draftRevision = $meeting->minutes()
            ->where('status', 'draft')
            ->where('version', '>', 1)
            ->first();

        $canRevise = Gate::allows('revise', $meeting);
        $canEditCurrent = Gate::allows('update', $minute);
        $canAcknowledge = Gate::allows('acknowledge', $minute);

        $activeShareLink = $meeting->activeShareLink;
        $recipients = collect();
        $openedLogMap = collect();

        if ($activeShareLink) {
            $recipientsQuery = $meeting->attendees()
                ->where(function ($q) {
                    $q->whereNull('guest_status')->orWhere('guest_status', '!=', 'rejected');
                });

            if ($activeShareLink->recipients_scope === 'hadir') {
                $recipientsQuery->where(function ($q) {
                    $q->whereNotNull('checked_in_at')->orWhereIn('status', ['hadir', 'terlambat']);
                });
            }

            $recipients = $recipientsQuery->orderBy('name')->get();

            $openedLogs = AuditLog::where('meeting_id', $meeting->id)
                ->where('action', 'link_open')
                ->whereNotNull('meta')
                ->get();

            foreach ($openedLogs as $log) {
                if (is_array($log->meta) && isset($log->meta['attendee_id'])) {
                    $attId = $log->meta['attendee_id'];
                    if (! isset($openedLogMap[$attId]) || $log->created_at > $openedLogMap[$attId]) {
                        $openedLogMap[$attId] = $log->created_at;
                    }
                }
            }
        }

        $cachedPlainToken = session("share_token_{$meeting->id}");

        return view('minutes.show', [
            'meeting' => $meeting,
            'minute' => $minute,
            'allVersions' => $allVersions,
            'latestFinal' => $latestFinal,
            'hasNewerFinalRevision' => $hasNewerFinalRevision,
            'isAcknowledgedByMe' => $isAcknowledgedByMe,
            'draftRevision' => $draftRevision,
            'canRevise' => $canRevise,
            'canEditCurrent' => $canEditCurrent,
            'canAcknowledge' => $canAcknowledge,
            'activeShareLink' => $activeShareLink,
            'recipients' => $recipients,
            'openedLogMap' => $openedLogMap,
            'cachedPlainToken' => $cachedPlainToken,
        ]);
    }

    /**
     * Show the editor form for a draft minute.
     */
    public function edit(Request $request, Meeting $meeting): View
    {
        $versionParam = $request->query('v');

        /** @var Minute|null $minute */
        $minute = $versionParam
            ? $meeting->minutes()->where('version', (int) $versionParam)->where('status', 'draft')->first()
            : $meeting->minutes()->where('status', 'draft')->reorder('version', 'desc')->first();

        if (! $minute) {
            abort(404, 'Tidak ada draf notulen yang dapat disunting untuk rapat ini.');
        }

        $this->authorize('update', $minute);

        $actionItems = $minute->actionItems()->get();
        $markers = $meeting->markers()->orderBy('at_sec')->get();

        return view('minutes.edit', [
            'meeting' => $meeting,
            'minute' => $minute,
            'actionItems' => $actionItems,
            'markers' => $markers,
        ]);
    }

    /**
     * Update the draft minute content.
     */
    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $minuteId = $request->input('minute_id');
        $minute = $minuteId
            ? $meeting->minutes()->where('status', 'draft')->findOrFail($minuteId)
            : $meeting->minutes()->where('status', 'draft')->reorder('version', 'desc')->firstOrFail();

        $this->authorize('update', $minute);

        $validated = $request->validate([
            'ringkasan' => ['nullable', 'string'],
            'agenda' => ['nullable', 'array'],
            'agenda.*' => ['nullable', 'string'],
            'pembahasan' => ['nullable', 'string'],
            'keputusan' => ['nullable', 'array'],
            'keputusan.*' => ['nullable', 'string'],
            'action_items' => ['nullable', 'array'],
            'action_items.*.description' => ['nullable', 'string'],
            'action_items.*.assignee_name' => ['nullable', 'string'],
            'action_items.*.due_date' => ['nullable', 'date'],
            'action_items.*.status' => ['nullable', 'string', 'in:pending,in_progress,completed,cancelled'],
        ]);

        $agenda = array_values(array_filter(array_map('trim', $validated['agenda'] ?? [])));
        $keputusan = array_values(array_filter(array_map('trim', $validated['keputusan'] ?? [])));

        $tindakLanjut = [];
        if ($meeting->use_followup) {
            $minute->actionItems()->delete();

            foreach ($validated['action_items'] ?? [] as $item) {
                $desc = trim($item['description'] ?? '');
                if (! empty($desc)) {
                    $assignee = trim($item['assignee_name'] ?? '') ?: null;
                    $dueDate = ! empty($item['due_date']) ? $item['due_date'] : null;
                    $status = $item['status'] ?? 'pending';

                    ActionItem::create([
                        'organization_id' => $meeting->organization_id,
                        'meeting_id' => $meeting->id,
                        'minute_id' => $minute->id,
                        'description' => $desc,
                        'assignee_name' => $assignee,
                        'due_date' => $dueDate,
                        'status' => $status,
                    ]);

                    $tindakLanjut[] = [
                        'description' => $desc,
                        'assignee_name' => $assignee,
                        'due_date' => $dueDate,
                    ];
                }
            }
        }

        $contentJson = [
            'ringkasan' => trim($validated['ringkasan'] ?? ''),
            'agenda' => $agenda,
            'pembahasan' => trim($validated['pembahasan'] ?? ''),
            'keputusan' => $keputusan,
            'tindak_lanjut' => $tindakLanjut,
        ];

        $minute->update([
            'content_json' => $contentJson,
        ]);

        return redirect()->route('meetings.minutes.show', ['meeting' => $meeting, 'v' => $minute->version])
            ->with('success', 'Perubahan draf notulen berhasil disimpan.');
    }

    /**
     * Finalize the draft minute.
     * Enforces mandatory share_expires_at per product requirements.
     */
    public function finalize(Request $request, Meeting $meeting): RedirectResponse
    {
        $minuteId = $request->input('minute_id');
        $minute = $minuteId
            ? $meeting->minutes()->where('status', 'draft')->findOrFail($minuteId)
            : $meeting->minutes()->where('status', 'draft')->reorder('version', 'desc')->firstOrFail();

        $this->authorize('finalize', $minute);

        $validated = $request->validate([
            'share_expires_at' => ['required', 'date', 'after:now'],
            'access_mode' => ['nullable', 'in:phone,code'],
            'recipients_scope' => ['nullable', 'in:hadir,semua_undangan'],
        ], [
            'share_expires_at.required' => 'Batas waktu baca peserta wajib ditentukan saat finalisasi notulen.',
            'share_expires_at.after' => 'Batas waktu baca peserta harus di masa depan.',
        ]);

        $accessMode = $validated['access_mode'] ?? 'phone';
        $recipientsScope = $validated['recipients_scope'] ?? 'semua_undangan';

        $meeting->update([
            'share_expires_at' => $validated['share_expires_at'],
        ]);

        $minute->update([
            'status' => 'final',
            'finalized_at' => now(),
        ]);

        // Create share link upon finalization
        $plainToken = Str::random(64);
        $tokenHash = hash('sha256', $plainToken);
        $pin = null;
        $codeHash = null;

        if ($accessMode === 'code') {
            $pin = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $codeHash = Hash::make($pin);
        }

        // Revoke any previous active link
        $meeting->shareLinks()->whereNull('revoked_at')->update([
            'revoked_at' => now(),
        ]);

        $shareLink = ShareLink::create([
            'organization_id' => $meeting->organization_id,
            'meeting_id' => $meeting->id,
            'token_hash' => $tokenHash,
            'access_mode' => $accessMode,
            'code_hash' => $codeHash,
            'recipients_scope' => $recipientsScope,
            'expires_at' => $validated['share_expires_at'],
            'created_by' => auth()->id(),
        ]);

        Audit::record('minute_finalize', $meeting->id, [
            'minute_id' => $minute->id,
            'version' => $minute->version,
            'finalized_by' => auth()->id(),
            'share_expires_at' => $meeting->share_expires_at?->toIso8601String(),
        ]);

        Audit::record('link_create', $meeting->id, [
            'share_link_id' => $shareLink->id,
            'access_mode' => $shareLink->access_mode,
            'recipients_scope' => $shareLink->recipients_scope,
            'expires_at' => $shareLink->expires_at->toIso8601String(),
            'created_by' => auth()->id(),
        ]);

        session()->put("share_token_{$meeting->id}", $plainToken);
        session()->flash('plain_share_token', $plainToken);
        session()->flash('plain_share_url', route('public_share.show', ['token' => $plainToken]));
        if ($pin !== null) {
            session()->flash('plain_share_pin', $pin);
        }

        return redirect()->route('meetings.minutes.show', ['meeting' => $meeting, 'v' => $minute->version])
            ->with('success', "Notulen versi {$minute->version} resmi difinalkan.");
    }

    /**
     * Start a new revision of the finalized minutes.
     */
    public function revise(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('revise', $meeting);

        $validated = $request->validate([
            'revision_reason' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'revision_reason.required' => 'Alasan revisi wajib diisi untuk mendokumentasikan perubahan.',
            'revision_reason.min' => 'Alasan revisi minimal 5 karakter.',
        ]);

        // If an unfinalized draft already exists, redirect to it
        $existingDraft = $meeting->minutes()->where('status', 'draft')->first();
        if ($existingDraft) {
            return redirect()->route('meetings.minutes.edit', ['meeting' => $meeting, 'v' => $existingDraft->version])
                ->with('info', "Draf revisi versi {$existingDraft->version} sudah ada dan dapat dilanjutkan penyuntingannya.");
        }

        $latestFinal = $meeting->latestFinalMinute;
        if (! $latestFinal) {
            abort(422, 'Tidak ada versi final sebelumnya untuk direvisi.');
        }

        $newVersion = $latestFinal->version + 1;

        $newMinute = Minute::create([
            'organization_id' => $meeting->organization_id,
            'meeting_id' => $meeting->id,
            'version' => $newVersion,
            'status' => 'draft',
            'content_json' => $latestFinal->content_json,
            'created_by' => auth()->id(),
            'finalized_at' => null,
            'revision_reason' => $validated['revision_reason'],
        ]);

        // Duplicate action items from previous final version if use_followup is active
        if ($meeting->use_followup) {
            foreach ($latestFinal->actionItems as $item) {
                ActionItem::create([
                    'organization_id' => $meeting->organization_id,
                    'meeting_id' => $meeting->id,
                    'minute_id' => $newMinute->id,
                    'description' => $item->description,
                    'assignee_name' => $item->assignee_name,
                    'due_date' => $item->due_date,
                    'status' => $item->status,
                ]);
            }
        }

        Audit::record('minute_revise', $meeting->id, [
            'new_minute_id' => $newMinute->id,
            'from_version' => $latestFinal->version,
            'new_version' => $newVersion,
            'reason' => $validated['revision_reason'],
            'revised_by' => auth()->id(),
        ]);

        return redirect()->route('meetings.minutes.edit', ['meeting' => $meeting, 'v' => $newVersion])
            ->with('success', "Draf revisi versi {$newVersion} berhasil dibuat. Silakan periksa dan sunting sebelum difinalkan.");
    }

    /**
     * Mark a finalized minute version as acknowledged by Pimpinan.
     */
    public function acknowledge(Request $request, Meeting $meeting, Minute $minute): RedirectResponse
    {
        abort_unless((int) $minute->meeting_id === (int) $meeting->id, 404);

        $this->authorize('acknowledge', $minute);

        $already = MinuteAcknowledgement::where('minute_id', $minute->id)
            ->where('user_id', auth()->id())
            ->exists();

        if (! $already) {
            MinuteAcknowledgement::create([
                'organization_id' => $meeting->organization_id,
                'minute_id' => $minute->id,
                'user_id' => auth()->id(),
                'acknowledged_at' => now(),
            ]);

            Audit::record('minute_acknowledge', $meeting->id, [
                'minute_id' => $minute->id,
                'version' => $minute->version,
                'acknowledged_by' => auth()->id(),
            ]);
        }

        return redirect()->route('meetings.minutes.show', ['meeting' => $meeting, 'v' => $minute->version])
            ->with('success', "Notulen versi {$minute->version} berhasil ditandai 'Diketahui'.");
    }
}
