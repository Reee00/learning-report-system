<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Reminder/warning laporan untuk Coach yang belum menyelesaikan laporan
 * wajibnya (sesi mengajar terjadwal tanpa laporan yang cocok).
 *
 * Dikirim melalui database channel — Coach melihat pengingat saat login.
 */
class ReportReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $senderName,
        public string $senderRole,
        public int $missingCount,
        public ?string $message = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'sender_name'  => $this->senderName,
            'sender_role'  => $this->senderRole,
            'missing_count' => $this->missingCount,
            'message'      => $this->message
                ?? sprintf(
                    'Anda memiliki %d sesi mengajar yang belum dilaporkan. Segera lengkapi laporan Anda.',
                    $this->missingCount
                ),
        ];
    }
}
