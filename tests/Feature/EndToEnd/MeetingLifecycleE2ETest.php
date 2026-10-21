<?php

namespace Tests\Feature\EndToEnd;

use App\Contracts\SpeechToTextProvider;
use App\DTOs\TranscriptionResult;
use App\Models\Attendee;
use App\Models\AudioSegment;
use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\ShareLink;
use App\Models\TranscriptSegment;
use App\Models\User;
use App\Support\CheckinCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MeetingLifecycleE2ETest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_meeting_server_lifecycle_end_to_end(): void
    {
        // ---------------------------------------------------------------------
        // Setup: Mock storage, process, and external STT provider
        // ---------------------------------------------------------------------
        $disk = config('recording.disk', 'local');
        Storage::fake($disk);

        Process::fake([
            '*' => function ($process) {
                $cmd = $process->command;
                // If this is the FFmpeg audio merge command, create the output file on disk
                if (is_array($cmd)) {
                    $outputFile = end($cmd);
                    if (is_string($outputFile) && str_ends_with($outputFile, '.m4a')) {
                        @file_put_contents($outputFile, 'SIMULATED_MERGED_AUDIO_AAC');
                    }
                }

                return Process::result(output: 'FFmpeg 6.0 simulated', exitCode: 0);
            },
        ]);

        $mockStt = $this->createMock(SpeechToTextProvider::class);
        $mockStt->method('transcribe')
            ->willReturnCallback(function (string $path, string $mime, ?string $language) {
                return new TranscriptionResult(
                    text: 'Rapat koordinasi kurikulum dibuka. Pembahasan capaian pembelajaran disepakati. Target semester depan ditetapkan.',
                    segments: [
                        ['start' => 0.0, 'end' => 30.0, 'text' => 'Rapat koordinasi kurikulum dibuka.'],
                        ['start' => 30.0, 'end' => 60.0, 'text' => 'Pembahasan capaian pembelajaran disepakati. Target semester depan ditetapkan.'],
                    ],
                    duration: 60.0
                );
            });
        $this->app->instance(SpeechToTextProvider::class, $mockStt);

        $org = Organization::factory()->create([
            'name' => 'SMK Bintang Mandiri',
            'quota_minutes' => 600,
        ]);

        $notulis = User::factory()->notulis()->create([
            'organization_id' => $org->id,
            'name' => 'Siti Notulis',
            'email' => 'siti@sekolah.id',
        ]);

        $pimpinan = User::factory()->pimpinan()->create([
            'organization_id' => $org->id,
            'name' => 'Drs. Pimpinan Sekolah',
            'email' => 'pimpinan@sekolah.id',
        ]);

        $admin = User::factory()->admin()->create([
            'organization_id' => $org->id,
            'name' => 'Budi Admin',
            'email' => 'admin@sekolah.id',
        ]);

        // ---------------------------------------------------------------------
        // Step 1: Notulis creates a meeting room (draft status)
        // ---------------------------------------------------------------------
        $meetingDate = now()->addDay()->format('Y-m-d');
        $createResponse = $this->actingAs($notulis)->post(route('meetings.store'), [
            'title' => 'Rapat Pleno Penyusunan RPP & Kurikulum Merdeka',
            'meeting_date' => $meetingDate,
            'start_time' => '09:00',
            'location' => 'Ruang Sidang Utama',
            'style' => 'lengkap',
            'late_after_minutes' => 15,
            'use_followup' => true,
        ]);

        $createResponse->assertRedirect();
        $meeting = Meeting::where('title', 'Rapat Pleno Penyusunan RPP & Kurikulum Merdeka')->firstOrFail();
        $this->assertSame('draft', $meeting->status);
        $this->assertSame((int) $notulis->id, (int) $meeting->created_by);
        $this->assertSame((int) $org->id, (int) $meeting->organization_id);

        // ---------------------------------------------------------------------
        // Step 2: Add invited attendees & perform QR check-in & close attendance
        // ---------------------------------------------------------------------
        // Add Attendee 1 (who will attend)
        $this->actingAs($notulis)->post(route('meetings.attendees.store', $meeting), [
            'name' => 'Ahmad Dahlan',
            'position' => 'Waka Kurikulum',
            'phone' => '0812-3456-7890',
        ])->assertRedirect();

        // Add Attendee 2 (who will be absent)
        $this->actingAs($notulis)->post(route('meetings.attendees.store', $meeting), [
            'name' => 'Dewi Sartika',
            'position' => 'Guru Bahasa',
            'phone' => '0898-7654-3210',
        ])->assertRedirect();

        $attendeeHadir = Attendee::where('meeting_id', $meeting->id)
            ->where('phone_e164', '6281234567890')
            ->firstOrFail();
        $attendeeAbsen = Attendee::where('meeting_id', $meeting->id)
            ->where('phone_e164', '6289876543210')
            ->firstOrFail();

        // Attendee 1 performs guest check-in via QR code URL
        $checkinCode = CheckinCode::generate($meeting->id);
        $checkinResponse = $this->post("/c/{$meeting->id}", [
            'code' => $checkinCode,
            'attendee_id' => $attendeeHadir->id,
            'phone' => '081234567890',
        ]);
        $checkinResponse->assertOk();
        $attendeeHadir->refresh();
        $this->assertSame('hadir', $attendeeHadir->status);
        $this->assertSame('qr', $attendeeHadir->method);
        $this->assertNotNull($attendeeHadir->checked_in_at);

        // Host closes attendance
        $closeAttendanceResponse = $this->actingAs($notulis)->post(route('meetings.attendance.close', $meeting));
        $closeAttendanceResponse->assertRedirect();
        $meeting->refresh();
        $this->assertNotNull($meeting->absensi_closed_at);

        $attendeeAbsen->refresh();
        $this->assertSame('tanpa_keterangan', $attendeeAbsen->status);

        // ---------------------------------------------------------------------
        // Step 3: Start recording, upload audio segment, finish recording
        // ---------------------------------------------------------------------
        // Start recording
        $startRecordResponse = $this->actingAs($notulis)->postJson(route('meetings.record.start', $meeting));
        $startRecordResponse->assertOk();
        $meeting->refresh();
        $this->assertSame('recording', $meeting->status);

        // Upload 1 segment of 60 seconds audio
        $dummyAudioContent = 'FAKE_AUDIO_SEGMENT_BINARY_DATA_E2E';
        $uploadedFile = UploadedFile::fake()->createWithContent('seg-1.webm', $dummyAudioContent);
        $checksum = hash('sha256', $dummyAudioContent);

        $segmentUploadResponse = $this->actingAs($notulis)->postJson(route('meetings.record.segments', $meeting), [
            'file' => $uploadedFile,
            'seq' => 1,
            'checksum' => $checksum,
            'duration_sec' => 60.0,
        ]);
        $segmentUploadResponse->assertStatus(201);

        $segment = AudioSegment::where('meeting_id', $meeting->id)->where('seq', 1)->firstOrFail();
        $this->assertSame('uploaded', $segment->status);
        Storage::disk($disk)->assertExists($segment->path);

        // Finish recording (triggers transcription pipeline synchronously in tests)
        $finishResponse = $this->actingAs($notulis)->postJson(route('meetings.record.finish', $meeting), [
            'expected_segments' => 1,
        ]);
        $finishResponse->assertOk();
        $finishResponse->assertJsonPath('completed', true);

        // ---------------------------------------------------------------------
        // Step 4: Verify transcription pipeline, assembly, audio merge & draft generation
        // ---------------------------------------------------------------------
        $meeting->refresh();
        // Pipeline moves through uploaded -> transcribing -> ready (assembly finished)
        $this->assertSame('ready', $meeting->status);
        $this->assertNotNull($meeting->audio_saved_at);
        $this->assertNotNull($meeting->audio_expires_at);
        $this->assertNotNull($meeting->merged_audio_path);

        // Transcript segments assembled
        $transcriptSegments = TranscriptSegment::where('meeting_id', $meeting->id)->get();
        $this->assertCount(2, $transcriptSegments);
        $this->assertDatabaseHas('transcript_segments', [
            'meeting_id' => $meeting->id,
            'text' => 'Rapat koordinasi kurikulum dibuka.',
        ]);

        // Minute draft v1 automatically generated by FakeSummarizer
        $draftMinuteV1 = Minute::where('meeting_id', $meeting->id)->where('version', 1)->firstOrFail();
        $this->assertSame('draft', $draftMinuteV1->status);

        // ---------------------------------------------------------------------
        // Step 5: Notulis edits draft minute v1
        // ---------------------------------------------------------------------
        $editResponse = $this->actingAs($notulis)->put(route('meetings.minutes.update', $meeting), [
            'minute_id' => $draftMinuteV1->id,
            'ringkasan' => 'Ringkasan resmi telah disunting oleh Siti Notulis.',
            'agenda' => ['Evaluasi Kurikulum', 'Penganggaran Buku'],
            'pembahasan' => 'Pembahasan mendalam materi kurikulum dan sarana penunjang.',
            'keputusan' => ['Menyetujui silabus baru semester ganjil'],
            'action_items' => [
                [
                    'description' => 'Susun modul ajar kurikulum',
                    'assignee_name' => 'Ahmad Dahlan',
                    'due_date' => now()->addDays(14)->format('Y-m-d'),
                    'status' => 'pending',
                ],
            ],
        ]);
        $editResponse->assertRedirect();
        $draftMinuteV1->refresh();
        $this->assertSame('Ringkasan resmi telah disunting oleh Siti Notulis.', $draftMinuteV1->getSection('ringkasan'));

        // ---------------------------------------------------------------------
        // Step 6: Notulis finalizes minute v1 (creates ShareLink)
        // ---------------------------------------------------------------------
        $shareExpiresAt = now()->addDays(7)->format('Y-m-d H:i:s');
        $finalizeResponse = $this->actingAs($notulis)->post(route('meetings.minutes.finalize', $meeting), [
            'minute_id' => $draftMinuteV1->id,
            'share_expires_at' => $shareExpiresAt,
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
        ]);
        $finalizeResponse->assertRedirect();

        $draftMinuteV1->refresh();
        $this->assertSame('final', $draftMinuteV1->status);
        $this->assertNotNull($draftMinuteV1->finalized_at);

        $shareLink = ShareLink::where('meeting_id', $meeting->id)->whereNull('revoked_at')->firstOrFail();
        $this->assertSame('phone', $shareLink->access_mode);
        $this->assertSame('hadir', $shareLink->recipients_scope);

        // Retrieve the plain token generated for the share link from session
        $plainToken = $finalizeResponse->getSession()->get('plain_share_token');
        $this->assertNotEmpty($plainToken);
        $this->assertSame(hash('sha256', $plainToken), $shareLink->token_hash);

        // ---------------------------------------------------------------------
        // Step 7: Public share link tests (phone verification gate)
        // ---------------------------------------------------------------------
        // 7a. Uninvited phone is rejected with uniform error message
        $uninvitedResponse = $this->from('/n/'.$plainToken)->post('/n/'.$plainToken.'/verify', [
            'phone' => '085555555555',
        ]);
        $uninvitedResponse->assertRedirect('/n/'.$plainToken);
        $uninvitedResponse->assertSessionHasErrors('gate');
        $this->assertSame(config('sharing.uniform_error_message'), session('errors')->first('gate'));

        // 7b. Absent attendee phone is rejected because scope is 'hadir'
        $absentResponse = $this->from('/n/'.$plainToken)->post('/n/'.$plainToken.'/verify', [
            'phone' => '0898-7654-3210',
        ]);
        $absentResponse->assertRedirect('/n/'.$plainToken);
        $absentResponse->assertSessionHasErrors('gate');
        $this->assertSame(config('sharing.uniform_error_message'), session('errors')->first('gate'));

        // 7c. Present attendee phone is accepted
        $presentResponse = $this->post('/n/'.$plainToken.'/verify', [
            'phone' => '0812-3456-7890',
        ]);
        $presentResponse->assertRedirect('/n/'.$plainToken);
        $presentResponse->assertSessionMissing('errors');

        // 7d. Render public view: displays final minutes content, strictly NO audio or raw transcripts
        $publicViewResponse = $this->get('/n/'.$plainToken);
        $publicViewResponse->assertOk();
        $publicViewResponse->assertViewIs('public_share.show');
        $publicViewResponse->assertSee('Rapat Pleno Penyusunan RPP & Kurikulum Merdeka');
        $publicViewResponse->assertSee('Ringkasan resmi telah disunting oleh Siti Notulis.');
        $publicViewResponse->assertSee('Menyetujui silabus baru semester ganjil');
        $publicViewResponse->assertDontSee('<audio', false);
        $publicViewResponse->assertDontSee('audio_play');
        $publicViewResponse->assertDontSee('Transkrip otomatis');

        // ---------------------------------------------------------------------
        // Step 8: Pimpinan acknowledges the finalized minute
        // ---------------------------------------------------------------------
        $ackResponse = $this->actingAs($pimpinan)->post(route('meetings.minutes.acknowledge', [
            'meeting' => $meeting,
            'minute' => $draftMinuteV1,
        ]));
        $ackResponse->assertRedirect();
        $this->assertTrue($draftMinuteV1->isAcknowledgedBy($pimpinan));
        $this->assertDatabaseHas('minute_acknowledgements', [
            'minute_id' => $draftMinuteV1->id,
            'user_id' => $pimpinan->id,
        ]);

        // ---------------------------------------------------------------------
        // Step 9: Notulis creates revision (v2) and finalizes it
        // ---------------------------------------------------------------------
        $reviseResponse = $this->actingAs($notulis)->post(route('meetings.minutes.revise', $meeting), [
            'revision_reason' => 'Perubahan butir silabus dan target kurikulum hasil rapat lanjutan.',
        ]);
        $reviseResponse->assertRedirect();

        $draftMinuteV2 = Minute::where('meeting_id', $meeting->id)->where('version', 2)->firstOrFail();
        $this->assertSame('draft', $draftMinuteV2->status);
        $this->assertSame('Perubahan butir silabus dan target kurikulum hasil rapat lanjutan.', $draftMinuteV2->revision_reason);

        // Edit v2 content
        $this->actingAs($notulis)->put(route('meetings.minutes.update', $meeting), [
            'minute_id' => $draftMinuteV2->id,
            'ringkasan' => 'Ringkasan Versi 2 yang telah direvisi.',
            'agenda' => ['Evaluasi Kurikulum', 'Penganggaran Buku'],
            'pembahasan' => 'Pembahasan diperbarui.',
            'keputusan' => ['Menyetujui silabus baru semester ganjil dengan addendum'],
            'action_items' => [
                [
                    'description' => 'Susun modul ajar kurikulum revisi',
                    'assignee_name' => 'Ahmad Dahlan',
                    'due_date' => now()->addDays(20)->format('Y-m-d'),
                    'status' => 'pending',
                ],
            ],
        ])->assertRedirect();

        // Finalize v2
        $finalizeV2Response = $this->actingAs($notulis)->post(route('meetings.minutes.finalize', $meeting), [
            'minute_id' => $draftMinuteV2->id,
            'share_expires_at' => now()->addDays(10)->format('Y-m-d H:i:s'),
            'access_mode' => 'phone',
            'recipients_scope' => 'hadir',
        ]);
        $finalizeV2Response->assertRedirect();

        $draftMinuteV2->refresh();
        $this->assertSame('final', $draftMinuteV2->status);

        // Retrieve newly generated share link token for revision from response session
        $newPlainToken = $finalizeV2Response->getSession()->get('plain_share_token');
        $this->assertNotEmpty($newPlainToken);
        $this->assertNotSame($plainToken, $newPlainToken);

        // Old share link is now revoked
        $oldLinkResponse = $this->get('/n/'.$plainToken);
        $oldLinkResponse->assertOk();
        $oldLinkResponse->assertSee('Tautan Telah Dicabut');

        // Verify with attendee phone on the new link
        $verifyV2Response = $this->post('/n/'.$newPlainToken.'/verify', [
            'phone' => '0812-3456-7890',
        ]);
        $verifyV2Response->assertRedirect('/n/'.$newPlainToken);

        // Check that new public share link now displays v2 with revision indicator
        $publicViewV2Response = $this->get('/n/'.$newPlainToken);
        $publicViewV2Response->assertOk();
        $publicViewV2Response->assertSee('Ringkasan Versi 2 yang telah direvisi.');
        $publicViewV2Response->assertSee('Hasil Revisi');

        // ---------------------------------------------------------------------
        // Step 10: Audio expiration and automated purge
        // ---------------------------------------------------------------------
        // Verify audio is streamable by creator before purge via signed URL
        $signedStreamUrl = URL::temporarySignedRoute(
            'meetings.audio.stream',
            now()->addMinutes(30),
            ['meeting' => $meeting->id]
        );
        $streamBeforeResponse = $this->actingAs($notulis)->get($signedStreamUrl);
        $streamBeforeResponse->assertOk();

        // Simulate expiration: set audio_expires_at into the past
        $meeting->update([
            'audio_expires_at' => now()->subDay(),
        ]);

        // Run audio:purge command
        $this->artisan('audio:purge', ['--meeting' => $meeting->id])
            ->assertSuccessful();

        $meeting->refresh();
        $this->assertNotNull($meeting->audio_deleted_at);
        $this->assertNull($meeting->merged_audio_path);

        // Physical files deleted from storage
        $this->assertFalse(Storage::disk($disk)->exists($segment->path));

        // Audio stream attempt now rejected (policy rejects expired/purged audio)
        $streamAfterSignedUrl = URL::temporarySignedRoute(
            'meetings.audio.stream',
            now()->addMinutes(30),
            ['meeting' => $meeting->id]
        );
        $streamAfterResponse = $this->actingAs($notulis)->get($streamAfterSignedUrl);
        $streamAfterResponse->assertForbidden();

        // Audit log for purge recorded
        $this->assertDatabaseHas('audit_logs', [
            'organization_id' => $org->id,
            'meeting_id' => $meeting->id,
            'action' => 'audio_purge',
        ]);

        // Crucial: Raw transcript segments and finalized minutes stay completely intact!
        $this->assertDatabaseHas('transcript_segments', [
            'meeting_id' => $meeting->id,
            'text' => 'Rapat koordinasi kurikulum dibuka.',
        ]);
        $this->assertSame(2, Minute::where('meeting_id', $meeting->id)->count());
        $this->assertDatabaseHas('minutes', [
            'id' => $draftMinuteV1->id,
            'status' => 'final',
        ]);
        $this->assertDatabaseHas('minutes', [
            'id' => $draftMinuteV2->id,
            'status' => 'final',
        ]);
    }
}
