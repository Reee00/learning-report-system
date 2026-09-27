<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi operasional custom (audit UX 2026-09-11) — dikirim oleh
 * Relation/SuperAdmin (scope global) atau PIC School (scope sekolah plot)
 * ke coach: perubahan jadwal, reminder laporan, atau warning operasional
 * lain. Tersimpan di database channel bersama reminder laporan, sehingga
 * behavior mark-as-read coach yang existing tetap berlaku.
 */
class CustomNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $senderName,
        public string $senderRole,
        public string $title,
        public string $message,
        public ?string $actionUrl = null,
        public string $type = 'operational',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'sender_name' => $this->senderName,
            'sender_role' => $this->senderRole,
            'title'       => $this->title,
            'message'     => $this->message,
            'type'        => $this->type,
            'action_url'  => $this->actionUrl,
        ];
    }
}
