<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\OrgContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingAttendeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_can_add_attendee_from_existing_contact(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        $contact = OrgContact::factory()->create([
            'organization_id' => $org->id,
            'name' => 'Budi Santoso',
            'phone_e164' => '6281234567890',
        ]);

        $response = $this->actingAs($notulis)->post("/meetings/{$meeting->id}/attendees", [
            'contact_id' => $contact->id,
        ]);

        $response->assertRedirect("/meetings/{$meeting->id}");

        $this->assertDatabaseHas('attendees', [
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'contact_id' => $contact->id,
            'name' => 'Budi Santoso',
            'phone_e164' => '6281234567890',
        ]);
    }

    public function test_adding_new_participant_automatically_creates_org_contact_and_normalizes_phone(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        $response = $this->actingAs($notulis)->post("/meetings/{$meeting->id}/attendees", [
            'name' => 'Dra. Sri Wahyuni',
            'position' => 'Guru Kimia',
            'phone' => '0812-3456-7899', // Needs normalization to 6281234567899
        ]);

        $response->assertRedirect("/meetings/{$meeting->id}");

        // Verify OrgContact was created automatically
        $this->assertDatabaseHas('org_contacts', [
            'organization_id' => $org->id,
            'name' => 'Dra. Sri Wahyuni',
            'position' => 'Guru Kimia',
            'phone_e164' => '6281234567899',
        ]);

        // Verify Attendee was created linked to that contact
        $this->assertDatabaseHas('attendees', [
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'name' => 'Dra. Sri Wahyuni',
            'phone_e164' => '6281234567899',
        ]);
    }

    public function test_rejects_duplicate_phone_number_in_same_meeting(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'phone_e164' => '6281234567890',
        ]);

        $response = $this->actingAs($notulis)->post("/meetings/{$meeting->id}/attendees", [
            'name' => 'Duplicate Person',
            'phone' => '0812 3456 7890', // Normalizes to 6281234567890
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_rejects_invalid_phone_number_when_adding_attendee(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        $response = $this->actingAs($notulis)->post("/meetings/{$meeting->id}/attendees", [
            'name' => 'Invalid Phone Person',
            'phone' => '021123456', // Landline, not valid mobile
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_creator_and_admin_can_remove_attendee(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        $attendee = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'name' => 'To Be Removed',
        ]);

        $response = $this->actingAs($notulis)->delete("/meetings/{$meeting->id}/attendees/{$attendee->id}");
        $response->assertRedirect("/meetings/{$meeting->id}");

        $this->assertDatabaseMissing('attendees', [
            'id' => $attendee->id,
        ]);
    }

    public function test_pimpinan_cannot_add_or_remove_attendees(): void
    {
        $org = Organization::factory()->create();
        $pimpinan = User::factory()->pimpinan()->create(['organization_id' => $org->id]);
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);
        $meeting = Meeting::factory()->create(['organization_id' => $org->id, 'created_by' => $notulis->id]);

        $attendee = Attendee::factory()->create([
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
        ]);

        $this->actingAs($pimpinan)
            ->post("/meetings/{$meeting->id}/attendees", [
                'name' => 'Peserta Pimpinan',
                'phone' => '081234567899',
            ])
            ->assertStatus(403);

        $this->actingAs($pimpinan)
            ->delete("/meetings/{$meeting->id}/attendees/{$attendee->id}")
            ->assertStatus(403);
    }
}
