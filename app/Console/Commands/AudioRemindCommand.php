<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Models\Scopes\OrganizationScope;
use App\Notifications\AudioRetentionExpiringNotification;
use Illuminate\Console\Command;

class AudioRemindCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audio:remind';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim pengingat dalam aplikasi kepada notulis pembuat sekitar 7 hari sebelum audio terhapus bila notulen belum final.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = now();
        $threshold = $now->copy()->addDays(7);

        // Find meetings whose audio will expire in the next 7 days, not yet purged
        $meetings = Meeting::withoutGlobalScope(OrganizationScope::class)
            ->whereNotNull('audio_expires_at')
            ->whereNull('audio_deleted_at')
            ->where('audio_expires_at', '>', $now)
            ->where('audio_expires_at', '<=', $threshold)
            ->with('creator')
            ->get();

        if ($meetings->isEmpty()) {
            $this->info('Tidak ada rapat yang memerlukan pengingat retensi audio.');

            return self::SUCCESS;
        }

        $remindedCount = 0;

        foreach ($meetings as $meeting) {
            // Skip if minutes are already finalized
            if ($meeting->isMinutesFinal()) {
                continue;
            }

            $creator = $meeting->creator;
            if (! $creator) {
                continue;
            }

            // Check if reminder notification was already sent to avoid duplicate spam
            $alreadySent = $creator->notifications()
                ->where('type', AudioRetentionExpiringNotification::class)
                ->where('data->meeting_id', $meeting->id)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $creator->notify(new AudioRetentionExpiringNotification($meeting));
            $this->info("Pengingat retensi dikirim kepada {$creator->email} untuk rapat #{$meeting->id} ('{$meeting->title}').");
            $remindedCount++;
        }

        $this->info("Selesai. Total pengingat terkirim: {$remindedCount}");

        return self::SUCCESS;
    }
}
