<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AudioRetentionExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Meeting $meeting
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $expiryDate = $this->meeting->audio_expires_at
            ? $this->meeting->audio_expires_at->translatedFormat('d F Y')
            : 'segera';

        return [
            'meeting_id' => $this->meeting->id,
            'title' => 'Pengingat Retensi Audio Rapat',
            'message' => "Audio rekaman untuk rapat '{$this->meeting->title}' akan dihapus pada {$expiryDate}. Notulen rapat belum final. Segera selesaikan dan finalkan notulen rapat.",
            'audio_expires_at' => $this->meeting->audio_expires_at?->toIso8601String(),
            'url' => route('meetings.show', $this->meeting),
        ];
    }
}
