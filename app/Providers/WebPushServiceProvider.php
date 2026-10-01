<?php

namespace App\Providers;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Illuminate\Support\ServiceProvider;
use Minishlink\WebPush\WebPush;

/**
 * Menyediakan instance Minishlink\WebPush yang sudah dikonfigurasi VAPID untuk
 * kanal push milik aplikasi (App\Notifications\Channels\WebPushChannel).
 *
 * Kenapa binding sendiri, bukan memakai binding paket?
 * Paket laravel-notification-channels/webpush hanya mengikat WebPush untuk
 * kelas kanalnya sendiri lewat contextual binding. Kanal milik aplikasi
 * membutuhkan instance yang sama benarnya — kalau tidak, container akan
 * membuat `new WebPush()` tanpa auth dan pengiriman push selalu gagal.
 *
 * KEAMANAN: private key VAPID dibaca dari config('webpush.vapid.private_key')
 * (berasal dari .env) dan HANYA hidup di dalam proses ini. Nilainya tidak
 * pernah dikirim ke klien, tidak masuk payload, dan tidak pernah dicatat di log.
 */
class WebPushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(WebPush::class, function (): WebPush {
            $config = config('webpush');

            return (new WebPush(
                $this->vapidAuth(),
                [],
                new Client($config['client_options'] ?? []),
                new HttpFactory,
                new HttpFactory,
            ))
                ->setReuseVAPIDHeaders(true)
                ->setAutomaticPadding($config['automatic_padding'] ?? true);
        });
    }

    /**
     * Susun konfigurasi auth VAPID dari config.
     *
     * Sengaja mengembalikan array kosong bila salah satu kunci tidak ada:
     * Minishlink akan menolak kunci yang tidak lengkap, dan kanal push sudah
     * lebih dulu berhenti (no-op) saat VAPID belum dikonfigurasi.
     *
     * @return array<string, mixed>
     */
    protected function vapidAuth(): array
    {
        $config = config('webpush.vapid', []);

        $publicKey = is_string($config['public_key'] ?? null) ? trim($config['public_key']) : '';
        $privateKey = is_string($config['private_key'] ?? null) ? trim($config['private_key']) : '';

        if ($publicKey === '' || $privateKey === '') {
            return [];
        }

        $subject = is_string($config['subject'] ?? null) ? trim($config['subject']) : '';

        return [
            'VAPID' => [
                // Subject wajib berupa URL atau mailto:. Bila tidak diisi,
                // pakai URL aplikasi sebagai fallback yang sah.
                'subject' => $subject !== '' ? $subject : url('/'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];
    }
}
