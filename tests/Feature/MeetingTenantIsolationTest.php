<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\OrgContact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_meetings_are_isolated_between_organizations(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $adminA = User::factory()->admin()->create(['organization_id' => $orgA->id]);
        $adminB = User::factory()->admin()->create(['organization_id' => $orgB->id]);

        $meetingA = Meeting::factory()->create([
            'organization_id' => $orgA->id,
            'created_by' => $adminA->id,
            'title' => 'Rapat Rahasia Org A',
        ]);

        $meetingB = Meeting::factory()->create([
            'organization_id' => $orgB->id,
            'created_by' => $adminB->id,
            'title' => 'Rapat Terpisah Org B',
        ]);

        // User A only sees meeting A
        $this->actingAs($adminA);
        $visibleMeetingsA = Meeting::all();
        $this->assertCount(1, $visibleMeetingsA);
        $this->assertEquals('Rapat Rahasia Org A', $visibleMeetingsA->first()->title);

        $this->get('/meetings')
            ->assertSee('Rapat Rahasia Org A')
            ->assertDontSee('Rapat Terpisah Org B');

        // User B cannot access meeting A
        $this->actingAs($adminB)
            ->get("/meetings/{$meetingA->id}")
            ->assertStatus(404); // Scoped model binding fails
    }

    public function test_org_contacts_and_attendees_are_isolated_between_organizations(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $adminA = User::factory()->admin()->create(['organization_id' => $orgA->id]);
        $adminB = User::factory()->admin()->create(['organization_id' => $orgB->id]);

        $contactA = OrgContact::factory()->create([
            'organization_id' => $orgA->id,
            'name' => 'Kontak Eksklusif Org A',
        ]);

        $contactB = OrgContact::factory()->create([
            'organization_id' => $orgB->id,
            'name' => 'Kontak Eksklusif Org B',
        ]);

        $this->actingAs($adminA);
        $visibleContacts = OrgContact::all();
        $this->assertCount(1, $visibleContacts);
        $this->assertEquals('Kontak Eksklusif Org A', $visibleContacts->first()->name);

        $meetingA = Meeting::factory()->create(['organization_id' => $orgA->id, 'created_by' => $adminA->id]);
        $attendeeA = Attendee::factory()->create([
            'organization_id' => $orgA->id,
            'meeting_id' => $meetingA->id,
            'contact_id' => $contactA->id,
            'name' => 'Peserta Org A',
        ]);

        $this->actingAs($adminB);
        $this->assertCount(0, Attendee::all());
    }

    public function test_automatic_organization_id_assignment_on_meeting_and_attendee(): void
    {
        $org = Organization::factory()->create();
        $notulis = User::factory()->notulis()->create(['organization_id' => $org->id]);

        $this->actingAs($notulis);

        $meeting = Meeting::create([
            'created_by' => $notulis->id,
            'title' => 'Rapat Auto Scope',
            'meeting_date' => '2026-10-30',
            'style' => 'ringkas',
        ]);

        $this->assertEquals($org->id, $meeting->organization_id);

        $attendee = $meeting->attendees()->create([
            'name' => 'Peserta Auto Scope',
            'phone_e164' => '6281234567890',
        ]);

        $this->assertEquals($org->id, $attendee->organization_id);
    }
}
