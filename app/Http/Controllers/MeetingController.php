<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\OrganizationSignatory;
use App\Models\OrgContact;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class MeetingController extends Controller
{
    /**
     * Display a listing of meetings in the tenant organization.
     */
    public function index(): View
    {
        $this->authorize('viewAny', Meeting::class);

        $meetings = Meeting::query()
            ->with('creator')
            ->withCount('attendees')
            ->latest('meeting_date')
            ->latest('id')
            ->get();

        return view('meetings.index', [
            'meetings' => $meetings,
        ]);
    }

    /**
     * Show the form for creating a new meeting room.
     */
    public function create(): View
    {
        $this->authorize('create', Meeting::class);

        $signatories = OrganizationSignatory::query()->orderBy('sort')->get();
        $contacts = OrgContact::query()->orderBy('name')->get();

        return view('meetings.create', [
            'signatories' => $signatories,
            'contacts' => $contacts,
        ]);
    }

    /**
     * Store a newly created meeting room.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Meeting::class);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'style' => ['required', 'in:ringkas,lengkap'],
            'late_after_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'signatory_ids' => ['nullable', 'array'],
            'signatory_ids.*' => ['exists:organization_signatories,id'],
            'selected_contact_ids' => ['nullable', 'array'],
            'selected_contact_ids.*' => ['exists:org_contacts,id'],
        ]);

        $meeting = Meeting::create([
            'created_by' => auth()->id(),
            'title' => $validated['title'],
            'meeting_date' => $validated['meeting_date'],
            'start_time' => $validated['start_time'] ?? null,
            'location' => $validated['location'] ?? null,
            'style' => $validated['style'],
            'use_followup' => $request->boolean('use_followup', true),
            'use_kop' => $request->boolean('use_kop', true),
            'use_signature' => $request->boolean('use_signature', true),
            'show_attendance' => $request->boolean('show_attendance', true),
            'show_recorded_notice' => $request->boolean('show_recorded_notice', false),
            'late_after_minutes' => isset($validated['late_after_minutes']) ? (int) $validated['late_after_minutes'] : 15,
            'status' => 'draft',
        ]);

        // Attach signatories if provided
        if (! empty($validated['signatory_ids'])) {
            $syncData = [];
            foreach ($validated['signatory_ids'] as $idx => $sigId) {
                $syncData[$sigId] = [
                    'organization_id' => auth()->user()->organization_id,
                    'sort' => $idx + 1,
                ];
            }
            $meeting->signatories()->sync($syncData);
        }

        // Attach selected contacts as attendees if provided
        if (! empty($validated['selected_contact_ids'])) {
            $contacts = OrgContact::query()->whereIn('id', $validated['selected_contact_ids'])->get();
            foreach ($contacts as $contact) {
                $meeting->attendees()->create([
                    'contact_id' => $contact->id,
                    'name' => $contact->name,
                    'position' => $contact->position,
                    'phone_e164' => $contact->phone_e164,
                ]);
            }
        }

        Audit::record('meeting_create', $meeting->id, [
            'title' => $meeting->title,
        ]);

        return redirect()->route('meetings.show', $meeting)->with('success', 'Room rapat berhasil dibuat.');
    }

    /**
     * Display the specified meeting room and its attendees.
     */
    public function show(Meeting $meeting): View
    {
        $this->authorize('view', $meeting);

        $meeting->load(['creator', 'signatories', 'attendees.contact']);
        $contacts = OrgContact::query()->orderBy('name')->get();

        $user = auth()->user();
        $canViewAudio = $user && Gate::allows('viewAudio', $meeting);
        $audioStreamUrl = null;

        if ($canViewAudio && $meeting->merged_audio_path) {
            $audioStreamUrl = URL::temporarySignedRoute(
                'meetings.audio.stream',
                now()->addMinutes(30),
                ['meeting' => $meeting->id]
            );
        }

        return view('meetings.show', [
            'meeting' => $meeting,
            'contacts' => $contacts,
            'canViewAudio' => $canViewAudio,
            'audioStreamUrl' => $audioStreamUrl,
        ]);
    }

    /**
     * Show the form for editing the meeting room.
     */
    public function edit(Meeting $meeting): View
    {
        $this->authorize('update', $meeting);

        $meeting->load('signatories');
        $signatories = OrganizationSignatory::query()->orderBy('sort')->get();

        return view('meetings.edit', [
            'meeting' => $meeting,
            'signatories' => $signatories,
        ]);
    }

    /**
     * Update the specified meeting room.
     */
    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('update', $meeting);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
            'style' => ['required', 'in:ringkas,lengkap'],
            'late_after_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'signatory_ids' => ['nullable', 'array'],
            'signatory_ids.*' => ['exists:organization_signatories,id'],
        ]);

        $meeting->update([
            'title' => $validated['title'],
            'meeting_date' => $validated['meeting_date'],
            'start_time' => $validated['start_time'] ?? null,
            'location' => $validated['location'] ?? null,
            'style' => $validated['style'],
            'use_followup' => $request->boolean('use_followup'),
            'use_kop' => $request->boolean('use_kop'),
            'use_signature' => $request->boolean('use_signature'),
            'show_attendance' => $request->boolean('show_attendance'),
            'show_recorded_notice' => $request->boolean('show_recorded_notice'),
            'late_after_minutes' => isset($validated['late_after_minutes']) ? (int) $validated['late_after_minutes'] : 15,
        ]);

        if (isset($validated['signatory_ids'])) {
            $syncData = [];
            foreach ($validated['signatory_ids'] as $idx => $sigId) {
                $syncData[$sigId] = [
                    'organization_id' => auth()->user()->organization_id,
                    'sort' => $idx + 1,
                ];
            }
            $meeting->signatories()->sync($syncData);
        } else {
            $meeting->signatories()->detach();
        }

        return redirect()->route('meetings.show', $meeting)->with('success', 'Room rapat berhasil diperbarui.');
    }

    /**
     * Remove the specified meeting room.
     */
    public function destroy(Meeting $meeting): RedirectResponse
    {
        $this->authorize('delete', $meeting);

        Audit::record('meeting_delete', $meeting->id, [
            'title' => $meeting->title,
        ]);

        $meeting->delete();

        return redirect()->route('meetings.index')->with('success', 'Room rapat berhasil dihapus.');
    }
}
