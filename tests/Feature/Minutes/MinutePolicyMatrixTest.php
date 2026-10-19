<?php

namespace Tests\Feature\Minutes;

use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MinutePolicyMatrixTest extends TestCase
{
    use RefreshDatabase;

    private Organization $orgA;

    private Organization $orgB;

    private User $creatorNotulisA;

    private User $otherNotulisA;

    private User $adminA;

    private User $pimpinanA;

    private User $notulisB;

    private User $adminB;

    private User $pimpinanB;

    private Meeting $meetingA;

    private Meeting $meetingFinalA;

    private Minute $draftMinuteV1;

    private Minute $finalMinuteV1;

    private Minute $adminRevisionDraftV2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();

        $this->creatorNotulisA = User::factory()->notulis()->create(['organization_id' => $this->orgA->id]);
        $this->otherNotulisA = User::factory()->notulis()->create(['organization_id' => $this->orgA->id]);
        $this->adminA = User::factory()->admin()->create(['organization_id' => $this->orgA->id]);
        $this->pimpinanA = User::factory()->pimpinan()->create(['organization_id' => $this->orgA->id]);

        $this->notulisB = User::factory()->notulis()->create(['organization_id' => $this->orgB->id]);
        $this->adminB = User::factory()->admin()->create(['organization_id' => $this->orgB->id]);
        $this->pimpinanB = User::factory()->pimpinan()->create(['organization_id' => $this->orgB->id]);

        $this->meetingA = Meeting::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->creatorNotulisA->id,
            'title' => 'Rapat Strategis Matriks Kebijakan Draf',
        ]);

        $this->draftMinuteV1 = Minute::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meetingA->id,
            'version' => 1,
            'status' => 'draft',
            'content_json' => ['ringkasan' => 'Draf V1'],
            'created_by' => $this->creatorNotulisA->id,
        ]);

        $this->meetingFinalA = Meeting::factory()->create([
            'organization_id' => $this->orgA->id,
            'created_by' => $this->creatorNotulisA->id,
            'title' => 'Rapat Strategis Matriks Kebijakan Final',
            'share_expires_at' => now()->addDays(7),
        ]);

        $this->finalMinuteV1 = Minute::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meetingFinalA->id,
            'version' => 1,
            'status' => 'final',
            'content_json' => ['ringkasan' => 'Final V1'],
            'created_by' => $this->creatorNotulisA->id,
            'finalized_at' => now()->subDay(),
        ]);

        $this->adminRevisionDraftV2 = Minute::create([
            'organization_id' => $this->orgA->id,
            'meeting_id' => $this->meetingFinalA->id,
            'version' => 2,
            'status' => 'draft',
            'content_json' => ['ringkasan' => 'Draf Revisi V2 oleh Admin'],
            'created_by' => $this->adminA->id,
            'revision_reason' => 'Perbaikan oleh admin',
        ]);
    }

    public function test_view_policy_matrix_for_v1_draft(): void
    {
        // Only creator notulis can view v1 draft
        $this->assertTrue(Gate::forUser($this->creatorNotulisA)->allows('view', $this->draftMinuteV1));

        // Other notulis, admin, pimpinan cannot view v1 draft
        $this->assertFalse(Gate::forUser($this->otherNotulisA)->allows('view', $this->draftMinuteV1));
        $this->assertFalse(Gate::forUser($this->adminA)->allows('view', $this->draftMinuteV1));
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('view', $this->draftMinuteV1));

        // Cross-tenant cannot view
        $this->assertFalse(Gate::forUser($this->notulisB)->allows('view', $this->draftMinuteV1));
        $this->assertFalse(Gate::forUser($this->adminB)->allows('view', $this->draftMinuteV1));
        $this->assertFalse(Gate::forUser($this->pimpinanB)->allows('view', $this->draftMinuteV1));
    }

    public function test_view_policy_matrix_for_finalized_minute(): void
    {
        // Creator notulis, admin, and pimpinan of same org can view final minute
        $this->assertTrue(Gate::forUser($this->creatorNotulisA)->allows('view', $this->finalMinuteV1));
        $this->assertTrue(Gate::forUser($this->adminA)->allows('view', $this->finalMinuteV1));
        $this->assertTrue(Gate::forUser($this->pimpinanA)->allows('view', $this->finalMinuteV1));

        // Other notulis of same org cannot view (strictly creator notulis, admin, pimpinan)
        $this->assertFalse(Gate::forUser($this->otherNotulisA)->allows('view', $this->finalMinuteV1));

        // Cross-tenant cannot view
        $this->assertFalse(Gate::forUser($this->notulisB)->allows('view', $this->finalMinuteV1));
        $this->assertFalse(Gate::forUser($this->adminB)->allows('view', $this->finalMinuteV1));
        $this->assertFalse(Gate::forUser($this->pimpinanB)->allows('view', $this->finalMinuteV1));
    }

    public function test_update_policy_matrix(): void
    {
        // V1 draft: only creator notulis
        $this->assertTrue(Gate::forUser($this->creatorNotulisA)->allows('update', $this->draftMinuteV1));
        $this->assertFalse(Gate::forUser($this->otherNotulisA)->allows('update', $this->draftMinuteV1));
        $this->assertFalse(Gate::forUser($this->adminA)->allows('update', $this->draftMinuteV1));
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('update', $this->draftMinuteV1));

        // Finalized minute cannot be updated by anyone
        $this->assertFalse(Gate::forUser($this->creatorNotulisA)->allows('update', $this->finalMinuteV1));
        $this->assertFalse(Gate::forUser($this->adminA)->allows('update', $this->finalMinuteV1));
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('update', $this->finalMinuteV1));

        // Revision v2 draft created by Admin: only that admin
        $this->assertTrue(Gate::forUser($this->adminA)->allows('update', $this->adminRevisionDraftV2));
        $this->assertFalse(Gate::forUser($this->creatorNotulisA)->allows('update', $this->adminRevisionDraftV2));
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('update', $this->adminRevisionDraftV2));
    }

    public function test_finalize_policy_matrix(): void
    {
        // V1 draft: only creator notulis, admin is FORBIDDEN on v1
        $this->assertTrue(Gate::forUser($this->creatorNotulisA)->allows('finalize', $this->draftMinuteV1));
        $this->assertFalse(Gate::forUser($this->otherNotulisA)->allows('finalize', $this->draftMinuteV1));
        $this->assertFalse(Gate::forUser($this->adminA)->allows('finalize', $this->draftMinuteV1));
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('finalize', $this->draftMinuteV1));

        // Revision v2 draft created by Admin: allowed for the admin who created the revision
        $this->assertTrue(Gate::forUser($this->adminA)->allows('finalize', $this->adminRevisionDraftV2));
        $this->assertFalse(Gate::forUser($this->creatorNotulisA)->allows('finalize', $this->adminRevisionDraftV2));
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('finalize', $this->adminRevisionDraftV2));

        // Cross-tenant cannot finalize
        $this->assertFalse(Gate::forUser($this->adminB)->allows('finalize', $this->adminRevisionDraftV2));
    }

    public function test_revise_policy_matrix(): void
    {
        // Meeting with finalized minute can be revised by creator notulis and admin
        $this->assertTrue(Gate::forUser($this->creatorNotulisA)->allows('revise', $this->meetingFinalA));
        $this->assertTrue(Gate::forUser($this->adminA)->allows('revise', $this->meetingFinalA));

        // Pimpinan cannot revise
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('revise', $this->meetingFinalA));

        // Other notulis cannot revise
        $this->assertFalse(Gate::forUser($this->otherNotulisA)->allows('revise', $this->meetingFinalA));

        // Cross-tenant cannot revise
        $this->assertFalse(Gate::forUser($this->adminB)->allows('revise', $this->meetingFinalA));
        $this->assertFalse(Gate::forUser($this->creatorNotulisA)->allows('revise', Meeting::factory()->create(['organization_id' => $this->orgB->id])));
    }

    public function test_acknowledge_policy_matrix(): void
    {
        // Final minute: only Pimpinan of the same organization
        $this->assertTrue(Gate::forUser($this->pimpinanA)->allows('acknowledge', $this->finalMinuteV1));

        // Notulis and Admin cannot acknowledge
        $this->assertFalse(Gate::forUser($this->creatorNotulisA)->allows('acknowledge', $this->finalMinuteV1));
        $this->assertFalse(Gate::forUser($this->adminA)->allows('acknowledge', $this->finalMinuteV1));

        // Draft minute cannot be acknowledged by anyone
        $this->assertFalse(Gate::forUser($this->pimpinanA)->allows('acknowledge', $this->draftMinuteV1));

        // Cross-tenant pimpinan cannot acknowledge
        $this->assertFalse(Gate::forUser($this->pimpinanB)->allows('acknowledge', $this->finalMinuteV1));
    }
}
