<?php

namespace Tests\Support;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Notifikasi probe untuk menguji kanal push (PWA Phase 2 hardening).
 *
 * KENAPA TIDAK MEMAKAI CustomNotification LANGSUNG:
 *   CustomNotification dan ReportReminderNotification sama-sama
 *   `implements ShouldQueue`. Saat `Queue::fake()` dipasang, Laravel menahan
 *   job notifikasinya, sehingga kanal — termasuk kanal push — tidak pernah
 *   dipanggil dan yang terukur bukan lagi perilaku kanal push.
 *
 *   Probe ini SENGAJA tidak mengantre: `notify()` langsung menjalankan kanal,
 *   jadi pengujian bisa memeriksa apa yang kanal jadwalkan ke antrean tanpa
 *   ikut terjebak antrean notifikasi. Integrasi dengan kedua notifikasi asli
 *   tetap diuji terpisah tanpa Queue::fake().
 *
 * Payload-nya sengaja dibuat mirip CustomNotification (title, body, type,
 * url) supaya assertion payload tetap mewakili bentuk sungguhan.
 */
class ImmediatePushProbe extends Notification
{
    public function __construct(
        public string $title,
        public string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => 'operational',
            'action_url' => '/coach/reports',
        ];
    }

    public function toWebPush(object $notifiable): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->message)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag('lrs-test-probe')
            ->data([
                'type' => 'operational',
                'notification_id' => $this->id,
                'url' => '/coach/reports',
            ]);
    }
}
