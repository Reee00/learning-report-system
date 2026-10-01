<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Reminder/warning laporan untuk Coach yang belum menyelesaikan laporan
 * wajibnya (sesi mengajar terjadwal tanpa laporan yang cocok).
 *
 * Dikirim melalui database channel — Coach melihat pengingat saat login.
 *
 * PWA Phase 2: kanal WebPushChannel ditambahkan sebagai jalur pengiriman
 * TAMBAHAN. Satu `notify()` tetap menghasilkan SATU baris `notifications`;
 * push hanya memberitahu perangkat bahwa baris itu ada. Bila push gagal,
 * baris database tetap dibuat dan tetap tampil di banner/history coach.
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
        return ['database', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'sender_name'  => $this->senderName,
            'sender_role'  => $this->senderRole,
            'missing_count' => $this->missingCount,
            'message'      => $this->messageText(),
        ];
    }

    /**
     * Teks pengingat. Dipakai bersama oleh payload database dan push supaya
     * isi keduanya tidak pernah berbeda.
     */
    public function messageText(): string
    {
        return $this->message
            ?? sprintf(
                'Anda memiliki %d sesi mengajar yang belum dilaporkan. Segera lengkapi laporan Anda.',
                $this->missingCount
            );
    }

    /**
     * Payload Web Push.
     *
     * Dibatasi pada title/body/icon/badge + metadata (type, notification_id,
     * url). `notification_id` adalah id baris `notifications` yang sama —
     * bukan notifikasi kedua. Tidak ada data laporan/siswa di sini.
     */
    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Pengingat Laporan')
            ->body($this->messageText())
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag('lrs-report-reminder')
            ->data([
                'type' => 'report_reminder',
                'notification_id' => $this->id,
                // Path relatif: service worker me-resolve ke origin aplikasi,
                // sehingga URL tetap benar walau APP_URL berbeda.
                'url' => '/coach/reports',
            ]);
    }
}
