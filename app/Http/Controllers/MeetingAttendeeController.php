<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\OrgContact;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MeetingAttendeeController extends Controller
{
    /**
     * Store a newly added attendee into the meeting room.
     * Supports either selecting an existing OrgContact or adding a new contact that is automatically saved.
     */
    public function store(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->authorize('manageAttendees', $meeting);

        $contactId = $request->input('contact_id');

        if ($contactId) {
            // Adding from existing contact
            $contact = OrgContact::query()->findOrFail($contactId);

            $alreadyRegistered = $meeting->attendees()
                ->where('phone_e164', $contact->phone_e164)
                ->exists();

            if ($alreadyRegistered) {
                throw ValidationException::withMessages([
                    'contact_id' => 'Kontak ini sudah terdaftar sebagai peserta dalam rapat ini.',
                ]);
            }

            $meeting->attendees()->create([
                'contact_id' => $contact->id,
                'name' => $contact->name,
                'position' => $contact->position,
                'phone_e164' => $contact->phone_e164,
                'is_guest' => false,
            ]);

            return redirect()->route('meetings.show', $meeting)->with('success', "Peserta '{$contact->name}' berhasil ditambahkan.");
        }

        // Adding a new participant (automatically saves to OrgContact)
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string'],
            'is_guest' => ['nullable', 'boolean'],
        ]);

        $normalizedPhone = PhoneNumber::normalize($validated['phone']);
        if (! $normalizedPhone) {
            throw ValidationException::withMessages([
                'phone' => 'Nomor WhatsApp / telepon seluler Indonesia tidak valid. Contoh format: 081234567890.',
            ]);
        }

        // Check if attendee is already in this meeting
        $alreadyRegistered = $meeting->attendees()
            ->where('phone_e164', $normalizedPhone)
            ->exists();

        if ($alreadyRegistered) {
            throw ValidationException::withMessages([
                'phone' => 'Peserta dengan nomor telepon ini sudah terdaftar dalam rapat ini.',
            ]);
        }

        // Automatically find or create OrgContact
        $contact = OrgContact::query()->where('phone_e164', $normalizedPhone)->first();
        if (! $contact) {
            $contact = OrgContact::create([
                'name' => $validated['name'],
                'position' => $validated['position'] ?? null,
                'phone_e164' => $normalizedPhone,
            ]);
        }

        $meeting->attendees()->create([
            'contact_id' => $contact->id,
            'name' => $validated['name'],
            'position' => $validated['position'] ?? null,
            'phone_e164' => $normalizedPhone,
            'is_guest' => $request->boolean('is_guest', false),
        ]);

        return redirect()->route('meetings.show', $meeting)->with('success', "Peserta '{$validated['name']}' berhasil ditambahkan dan tersimpan ke kontak organisasi.");
    }

    /**
     * Remove an attendee from the meeting room.
     */
    public function destroy(Meeting $meeting, Attendee $attendee): RedirectResponse
    {
        abort_unless($attendee->meeting_id === $meeting->id, 404);

        $this->authorize('delete', $attendee);

        $name = $attendee->name;
        $attendee->delete();

        return redirect()->route('meetings.show', $meeting)->with('success', "Peserta '{$name}' telah dihapus dari daftar hadir.");
    }
}
