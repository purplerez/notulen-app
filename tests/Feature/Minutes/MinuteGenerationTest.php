<?php

namespace Tests\Feature\Minutes;

use App\Jobs\AssembleTranscript;
use App\Jobs\GenerateMinutes;
use App\Models\ActionItem;
use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\TranscriptSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MinuteGenerationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $notulis;

    private Meeting $meeting;

    private AudioSegment $audioSegment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();
        $this->notulis = User::factory()->notulis()->create(['organization_id' => $this->org->id]);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->notulis->id,
            'title' => 'Rapat Koordinasi Awal Tahun',
            'style' => 'lengkap',
            'use_followup' => true,
        ]);

        $this->audioSegment = AudioSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'seq' => 1,
            'path' => "orgs/{$this->org->id}/meetings/{$this->meeting->id}/seg-1.webm",
            'mime' => 'audio/webm',
            'size_bytes' => 1024,
            'duration_sec' => 30.0,
            'checksum' => hash('sha256', 'dummy-data'),
            'status' => 'ready',
        ]);
    }

    public function test_generate_minutes_creates_version_1_draft_and_action_items(): void
    {
        // Add transcript segments
        TranscriptSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'audio_segment_id' => $this->audioSegment->id,
            'start_sec' => 0.0,
            'end_sec' => 5.0,
            'text' => 'Rapat dibuka dengan agenda pembahasan kurikulum.',
            'edited_text' => null,
            'edited_by' => null,
        ]);

        TranscriptSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'audio_segment_id' => $this->audioSegment->id,
            'start_sec' => 5.0,
            'end_sec' => 12.0,
            'text' => 'Kepala sekolah menugaskan tim kurikulum menyusun modul sebelum bulan depan.',
            'edited_text' => null,
            'edited_by' => null,
        ]);

        $job = new GenerateMinutes($this->meeting);
        app()->call([$job, 'handle']);

        $this->assertDatabaseHas('minutes', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'version' => 1,
            'status' => 'draft',
            'created_by' => $this->notulis->id,
            'finalized_at' => null,
        ]);

        $minute = Minute::where('meeting_id', $this->meeting->id)->where('version', 1)->first();
        $this->assertNotNull($minute);
        $this->assertNotEmpty($minute->getSection('ringkasan'));
        $this->assertNotEmpty($minute->getSection('agenda'));
        $this->assertNotEmpty($minute->getSection('pembahasan'));

        // Action items created
        $this->assertTrue(ActionItem::where('minute_id', $minute->id)->exists());

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'minute_draft_created',
        ]);
    }

    public function test_generate_minutes_uses_edited_text_over_original_text(): void
    {
        TranscriptSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'audio_segment_id' => $this->audioSegment->id,
            'start_sec' => 0.0,
            'end_sec' => 5.0,
            'text' => 'Teks salah dari STT otomatis.',
            'edited_text' => 'Teks hasil revisi manual oleh notulis terpercaya.',
            'edited_by' => $this->notulis->id,
        ]);

        $job = new GenerateMinutes($this->meeting);
        app()->call([$job, 'handle']);

        $minute = Minute::where('meeting_id', $this->meeting->id)->first();
        $this->assertNotNull($minute);
        $this->assertStringContainsString('Teks hasil revisi manual', $minute->getSection('ringkasan'));
        $this->assertStringNotContainsString('Teks salah dari STT', $minute->getSection('ringkasan'));
    }

    public function test_generate_minutes_does_not_create_action_items_when_use_followup_is_false(): void
    {
        $this->meeting->update(['use_followup' => false]);

        TranscriptSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'audio_segment_id' => $this->audioSegment->id,
            'start_sec' => 0.0,
            'end_sec' => 10.0,
            'text' => 'Tugas diberikan kepada bendahara untuk merekap kwitansi.',
        ]);

        $job = new GenerateMinutes($this->meeting);
        app()->call([$job, 'handle']);

        $minute = Minute::where('meeting_id', $this->meeting->id)->first();
        $this->assertNotNull($minute);
        $this->assertSame([], $minute->getSection('tindak_lanjut'));
        $this->assertSame(0, ActionItem::where('minute_id', $minute->id)->count());
    }

    public function test_generate_minutes_is_idempotent_and_does_not_duplicate_version_1(): void
    {
        $existing = Minute::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'version' => 1,
            'status' => 'draft',
            'content_json' => ['ringkasan' => 'Draf awal sebelumnya'],
            'created_by' => $this->notulis->id,
        ]);

        $job = new GenerateMinutes($this->meeting);
        app()->call([$job, 'handle']);

        $this->assertSame(1, Minute::where('meeting_id', $this->meeting->id)->count());
        $this->assertSame('Draf awal sebelumnya', $existing->fresh()->getSection('ringkasan'));
    }

    public function test_assemble_transcript_dispatches_generate_minutes(): void
    {
        Queue::fake([GenerateMinutes::class]);

        $this->meeting->update([
            'expected_segments' => 1,
            'status' => 'uploaded',
        ]);

        $job = new AssembleTranscript($this->meeting);
        $job->handle();

        Queue::assertPushed(GenerateMinutes::class, function ($job) {
            return $job->meeting->id === $this->meeting->id;
        });
    }
}
