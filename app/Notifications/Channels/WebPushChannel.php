<?php

namespace App\Notifications\Channels;

use App\Jobs\SendWebPush;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kanal Web Push untuk PWA LRS (Phase 2).
 *
 * PRINSIP — notifikasi database adalah SATU-SATUNYA sumber kebenaran; push
 * hanyalah jalur pengiriman tambahan yang bersifat best-effort:
 *
 *   1. Tidak pernah membuat record notifikasi kedua. Satu `notify()` =
 *      satu baris `notifications` + N pengiriman push (satu per perangkat).
 *   2. Tidak pernah melempar exception ke pemanggil. Kegagalan push TIDAK BOLEH
 *      menggagalkan pembuatan notifikasi database maupun membuat request 500.
 *   3. Diam (no-op) bila VAPID belum dikonfigurasi atau notifikasi tidak
 *      menyediakan payload push — bukan error.
 *   4. Menghapus HANYA subscription yang dilaporkan kedaluwarsa/invalid oleh
 *      penyedia push (HTTP 404/410), dan hanya milik penerima notifikasi.
 *      Perangkat lain tidak terpengaruh.
 *   5. Payload dibatasi: title, body, icon, badge + metadata (type,
 *      notification_id, url). Tidak ada token, kredensial, isi laporan privat,
 *      atau data siswa.
 *
 * KANAL INI RINGAN DENGAN SENGAJA (Phase 2 hardening): ia hanya memvalidasi
 * dan MENITIPKAN pengiriman ke App\Jobs\SendWebPush. Seluruh pekerjaan HTTP
 * ke penyedia push — yang bisa lambat atau gagal — tidak lagi berjalan di
 * dalam request pembuat notifikasi. Kanal database sudah menulis barisnya
 * lebih dulu (urutan `via()`), jadi sumber kebenaran tidak pernah bergantung
 * pada hasil push.
 *
 * Kanal ini sengaja tidak memakai WebPushChannel bawaan paket karena kanal
 * bawaan melempar exception saat VAPID kosong dan tidak punya jalur "gagal
 * diam-diam" yang dibutuhkan di sini.
 */
class WebPushChannel
{
    /**
     * Titipkan pengiriman push ke antrean.
     *
     * Selalu mengembalikan void: pemanggil (NotificationSender) tidak boleh
     * melihat kegagalan push sebagai kegagalan pengiriman notifikasi.
     */
    public function send(object $notifiable, Notification $notification): void
    {
        try {
            // 1. Fitur belum dikonfigurasi -> tidak ada yang bisa dikirim, dan
            //    tidak ada gunanya membuat job.
            if (! $this->isConfigured()) {
                return;
            }

            // 2. Penerima harus punya relasi subscription, dan notifikasi harus
            //    menyediakan payload push.
            if (! method_exists($notifiable, 'pushSubscriptions')
                || ! method_exists($notifiable, 'getKey')
                || ! method_exists($notification, 'toWebPush')) {
                return;
            }

            // 3. Yang disimpan adalah identitas penerima, bukan modelnya:
            //    perangkat dibaca ulang saat job berjalan. `$notification`
            //    ikut UTUH sehingga `$notification->id` — UUID baris
            //    `notifications` yang baru saja ditulis — tetap sama dan
            //    payload push menunjuk notifikasi logis yang sama.
            SendWebPush::dispatch(
                $notifiable->getMorphClass(),
                $notifiable->getKey(),
                $notification,
            );
        } catch (Throwable $e) {
            // Jaring pengaman terakhir. Pada QUEUE_CONNECTION=sync job berjalan
            // di sini juga, jadi kegagalan transport dari worker tetap tertangkap
            // dan tidak pernah sampai ke request pembuat notifikasi.
            // Pesan exception bisa memuat endpoint, jadi yang dicatat hanya
            // kelas exception dan pesan ringkasnya.
            Log::warning('Web push gagal dikirim.', [
                'notifiable_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
                'notification' => $notification::class,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    protected function isConfigured(): bool
    {
        return $this->configString('webpush.vapid.public_key') !== ''
            && $this->configString('webpush.vapid.private_key') !== '';
    }

    protected function configString(string $key): string
    {
        $value = config($key);

        return is_string($value) ? trim($value) : '';
    }
}
