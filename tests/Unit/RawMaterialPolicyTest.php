<?php

namespace Tests\Unit;

use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use App\Policies\RawMaterialPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RawMaterialPolicyTest extends TestCase
{
    use RefreshDatabase;

    private RawMaterialPolicy $policy;

    private Organization $orgA;

    private Organization $orgB;

    private User $adminA;

    private User $notulisA;

    private User $otherNotulisA;

    private User $pimpinanA;

    private User $notulisB;

    private Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new RawMaterialPolicy;

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->adminA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => 'admin',
        ]);

        $this->notulisA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => 'notulis',
        ]);

        $this->otherNotulisA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => 'notulis',
        ]);

        $this->pimpinanA = User::factory()->create([
            'organization_id' => $this->orgA->id,
            'role' => 'pimpinan',
        ]);

        $this->notulisB = User::factory()->create([
            'organization_id' => $this->orgB->id,
            'role' => 'notulis',
        ]);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->notulisA->id,
            'audio_saved_at' => now()->subDay(),
            'audio_expires_at' => now()->addDays(29),
            'audio_deleted_at' => null,
            'merged_audio_path' => "orgs/{$this->orgA->id}/meetings/1/merged.m4a",
        ]);
    }

    public function test_admin_is_strictly_forbidden_from_viewing_audio(): void
    {
        $this->assertFalse($this->policy->viewAudio($this->adminA, $this->meeting));
    }

    public function test_other_notulis_cannot_view_audio_of_meeting_they_did_not_create(): void
    {
        $this->assertFalse($this->policy->viewAudio($this->otherNotulisA, $this->meeting));
    }

    public function test_cross_tenant_user_cannot_view_audio(): void
    {
        $this->assertFalse($this->policy->viewAudio($this->notulisB, $this->meeting));
    }

    public function test_creator_notulis_can_view_audio_before_expiration(): void
    {
        $this->assertTrue($this->policy->viewAudio($this->notulisA, $this->meeting));
    }

    public function test_pimpinan_of_same_org_can_view_audio_before_expiration(): void
    {
        $this->assertTrue($this->policy->viewAudio($this->pimpinanA, $this->meeting));
    }

    public function test_creator_notulis_and_pimpinan_denied_after_audio_expires(): void
    {
        $this->meeting->update([
            'audio_expires_at' => now()->subSecond(),
        ]);

        $this->assertFalse($this->policy->viewAudio($this->notulisA, $this->meeting));
        $this->assertFalse($this->policy->viewAudio($this->pimpinanA, $this->meeting));
    }

    public function test_creator_notulis_and_pimpinan_denied_when_audio_is_deleted(): void
    {
        $this->meeting->update([
            'audio_deleted_at' => now(),
        ]);

        $this->assertFalse($this->policy->viewAudio($this->notulisA, $this->meeting));
        $this->assertFalse($this->policy->viewAudio($this->pimpinanA, $this->meeting));
    }

    public function test_transcript_authorization_matrix(): void
    {
        // Admin forbidden
        $this->assertFalse($this->policy->viewTranscript($this->adminA, $this->meeting));

        // Other notulis forbidden
        $this->assertFalse($this->policy->viewTranscript($this->otherNotulisA, $this->meeting));

        // Cross-tenant user forbidden
        $this->assertFalse($this->policy->viewTranscript($this->notulisB, $this->meeting));

        // Creator notulis and pimpinan allowed
        $this->assertTrue($this->policy->viewTranscript($this->notulisA, $this->meeting));
        $this->assertTrue($this->policy->viewTranscript($this->pimpinanA, $this->meeting));
    }

    public function test_update_transcript_authorization_matrix(): void
    {
        // Admin forbidden
        $this->assertFalse($this->policy->updateTranscript($this->adminA, $this->meeting));

        // Pimpinan forbidden (read-only)
        $this->assertFalse($this->policy->updateTranscript($this->pimpinanA, $this->meeting));

        // Other notulis forbidden
        $this->assertFalse($this->policy->updateTranscript($this->otherNotulisA, $this->meeting));

        // Cross-tenant user forbidden
        $this->assertFalse($this->policy->updateTranscript($this->notulisB, $this->meeting));

        // Creator notulis allowed
        $this->assertTrue($this->policy->updateTranscript($this->notulisA, $this->meeting));
    }
}
