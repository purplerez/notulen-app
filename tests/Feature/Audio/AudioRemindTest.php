<?php

namespace Tests\Feature\Audio;

use App\Models\Meeting;
use App\Models\Minute;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AudioRetentionExpiringNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AudioRemindTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;

    private User $creatorNotulis;

    private Meeting $expiringMeeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::factory()->create();

        $this->creatorNotulis = User::factory()->create([
            'organization_id' => $this->org->id,
            'role' => 'notulis',
        ]);

        // Meeting expiring in 5 days (within 7 days threshold)
        $this->expiringMeeting = Meeting::factory()->create([
            'organization_id' => $this->org->id,
            'created_by' => $this->creatorNotulis->id,
            'audio_saved_at' => now()->subDays(25),
            'audio_expires_at' => now()->addDays(5),
            'audio_deleted_at' => null,
            'status' => 'ready',
        ]);
    }

    public function test_audio_remind_sends_notification_when_audio_expires_within_7_days(): void
    {
        Notification::fake();

        $this->artisan('audio:remind')
            ->expectsOutputToContain("Pengingat retensi dikirim kepada {$this->creatorNotulis->email}")
            ->assertSuccessful();

        Notification::assertSentTo(
            $this->creatorNotulis,
            AudioRetentionExpiringNotification::class,
            function ($notification) {
                return $notification->meeting->id === $this->expiringMeeting->id;
            }
        );
    }

    public function test_audio_remind_does_not_send_duplicate_notifications(): void
    {
        // First run sends notification into database
        $this->artisan('audio:remind')->assertSuccessful();

        $this->assertEquals(1, $this->creatorNotulis->notifications()->count());

        // Second run must skip
        $this->artisan('audio:remind')
            ->expectsOutputToContain('Total pengingat terkirim: 0')
            ->assertSuccessful();

        $this->assertEquals(1, $this->creatorNotulis->notifications()->count());
    }

    public function test_audio_remind_skips_meetings_expiring_more_than_7_days_away(): void
    {
        $this->expiringMeeting->update([
            'audio_expires_at' => now()->addDays(15),
        ]);

        Notification::fake();

        $this->artisan('audio:remind')
            ->expectsOutputToContain('Tidak ada rapat yang memerlukan pengingat retensi audio.')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_audio_remind_skips_already_deleted_audio(): void
    {
        $this->expiringMeeting->update([
            'audio_deleted_at' => now(),
        ]);

        Notification::fake();

        $this->artisan('audio:remind')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_audio_remind_skips_when_minutes_are_already_finalized(): void
    {
        Minute::create([
            'organization_id' => $this->org->id,
            'meeting_id' => $this->expiringMeeting->id,
            'version' => 1,
            'status' => 'final',
            'content_json' => ['ringkasan' => 'Notulen sudah resmi final.'],
            'created_by' => $this->creatorNotulis->id,
            'finalized_at' => now()->subDay(),
        ]);

        Notification::fake();

        $this->artisan('audio:remind')
            ->expectsOutputToContain('Total pengingat terkirim: 0')
            ->assertSuccessful();

        Notification::assertNothingSent();
    }
}
