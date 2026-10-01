<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notifikasi operasional custom (audit UX 2026-09-11) — dikirim oleh
 * Relation/SuperAdmin (scope global) atau PIC School (scope sekolah plot)
 * ke coach: perubahan jadwal, reminder laporan, atau warning operasional
 * lain. Tersimpan di database channel bersama reminder laporan, sehingga
 * behavior mark-as-read coach yang existing tetap berlaku.
 *
 * PWA Phase 2: kanal WebPushChannel ditambahkan sebagai jalur pengiriman
 * TAMBAHAN dengan prinsip yang sama — satu `notify()` = satu baris
 * `notifications`, target penerima TIDAK berubah, dan kegagalan push tidak
 * pernah menggagalkan notifikasi database.
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
        return ['database', WebPushChannel::class];
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

    /**
     * Tujuan klik notifikasi.
     *
     * `action_url` berasal dari input Relation/PIC, jadi hanya path internal
     * yang diterima: URL absolut, protokol-relatif (`//host`), atau skema lain
     * ditolak dan jatuh ke daftar laporan coach. Service worker memakai
     * validasi yang sama sebagai lapis kedua (anti open-redirect).
     */
    public function targetUrl(): string
    {
        $fallback = '/coach/reports';
        $url = $this->actionUrl;

        if (! is_string($url) || $url === '') {
            return $fallback;
        }

        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $fallback;
        }

        return $url;
    }

    /**
     * Payload Web Push — dibatasi title/body/icon/badge + metadata.
     * `notification_id` menunjuk baris `notifications` yang sama.
     */
    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->message)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag('lrs-notification')
            ->data([
                'type' => $this->type,
                'notification_id' => $this->id,
                'url' => $this->targetUrl(),
            ]);
    }
}
