<?php

namespace Tests\Feature\Audio;

use App\Jobs\MergeAudio;
use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MergeAudioTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $notulis;

    private Meeting $meeting;

    private AudioSegment $seg1;

    private AudioSegment $seg2;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->org = Organization::factory()->create();
        $this->notulis = User::factory()->create([
            'organization_id' => $this->org->id,
            'role' => 'notulis',
        ]);

        $this->meeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->notulis->id,
            'status' => 'uploaded',
        ]);

        $dir = "orgs/{$this->org->id}/meetings/{$this->meeting->id}";
        $path1 = "{$dir}/seg-1.webm";
        $path2 = "{$dir}/seg-2.webm";

        Storage::disk('local')->put($path1, 'SEGMENT_1_CONTENT');
        Storage::disk('local')->put($path2, 'SEGMENT_2_CONTENT');

        $this->seg1 = AudioSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'seq' => 1,
            'path' => $path1,
            'mime' => 'audio/webm',
            'size_bytes' => 17,
            'duration_sec' => 60.0,
            'checksum' => hash('sha256', 'SEGMENT_1_CONTENT'),
            'status' => 'ready',
        ]);

        $this->seg2 = AudioSegment::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'seq' => 2,
            'path' => $path2,
            'mime' => 'audio/webm',
            'size_bytes' => 17,
            'duration_sec' => 60.0,
            'checksum' => hash('sha256', 'SEGMENT_2_CONTENT'),
            'status' => 'ready',
        ]);
    }

    public function test_merge_audio_invokes_ffmpeg_with_separate_arguments_and_updates_model(): void
    {
        Process::fake([
            '*' => Process::result(
                output: 'FFmpeg version 6.0',
                errorOutput: '',
                exitCode: 0,
            ),
        ]);

        $job = new MergeAudio($this->meeting);
        $job->handle();

        // Verify Process::run was called with array arguments
        Process::assertRan(function ($process) {
            $cmd = $process->command;
            $this->assertIsArray($cmd);
            $this->assertContains('-f', $cmd);
            $this->assertContains('concat', $cmd);
            $this->assertContains('-c:a', $cmd);
            $this->assertContains('aac', $cmd);
            $this->assertContains('-movflags', $cmd);
            $this->assertContains('+faststart', $cmd);

            return true;
        });

        $this->meeting->refresh();
        $expectedPath = "orgs/{$this->org->id}/meetings/{$this->meeting->id}/merged.m4a";
        $this->assertEquals($expectedPath, $this->meeting->merged_audio_path);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $this->org->id,
            'meeting_id' => $this->meeting->id,
            'action' => 'audio_merge',
        ]);

        // Verify temporary manifest is cleaned up
        $manifestPath = "orgs/{$this->org->id}/meetings/{$this->meeting->id}/concat_manifest.txt";
        $this->assertFalse(Storage::disk('local')->exists($manifestPath));
    }

    public function test_merge_audio_throws_exception_when_process_fails(): void
    {
        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'Invalid data found when processing input',
                exitCode: 1,
            ),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gagal menggabungkan audio');

        $job = new MergeAudio($this->meeting);
        $job->handle();
    }

    public function test_merge_audio_skips_when_no_segments_exist(): void
    {
        Process::fake();

        $emptyMeeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->notulis->id,
        ]);

        $job = new MergeAudio($emptyMeeting);
        $job->handle();

        Process::assertNothingRan();
        $this->assertNull($emptyMeeting->fresh()->merged_audio_path);
    }

    public function test_merge_audio_optional_integration_with_real_ffmpeg(): void
    {
        $ffmpeg = trim((string) shell_exec('which ffmpeg'));
        if (empty($ffmpeg)) {
            $this->markTestSkipped('ffmpeg is not installed on the system; skipping real integration test.');
        }

        // Real execution test if ffmpeg is available
        config(['recording.ffmpeg_path' => $ffmpeg]);

        $job = new MergeAudio($this->meeting);
        $job->handle();

        $this->meeting->refresh();
        $this->assertNotNull($this->meeting->merged_audio_path);
    }
}
