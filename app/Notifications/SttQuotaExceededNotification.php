<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SttQuotaExceededNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Meeting $meeting,
        public int $retryAfterSeconds
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
        return [
            'meeting_id' => $this->meeting->id,
            'title' => 'Kuota STT Harian Habis',
            'message' => "Transkripsi untuk rapat '{$this->meeting->title}' ditunda karena batas kuota harian STT tercapai. Sistem akan melanjutkan secara otomatis.",
            'retry_after_seconds' => $this->retryAfterSeconds,
        ];
    }
}
