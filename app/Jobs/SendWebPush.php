<?php

namespace App\Jobs;

use App\Models\PushSubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Pengiriman Web Push di luar siklus request (PWA Phase 2 hardening).
 *
 * KENAPA DIPISAH KE JOB:
 *   Penyedia push bisa lambat atau tidak bisa dihubungi. Selama pengiriman
 *   berjalan di dalam request, Relation/PIC yang menekan "kirim notifikasi"
 *   ikut menunggu timeout HTTP push — dan request itu yang menanggung
 *   risikonya. Dengan job:
 *     - baris `notifications` sudah ditulis kanal database SEBELUM job ini
 *       jalan (urutan `via()`), jadi sumber kebenaran tidak bergantung pada
 *       push sama sekali;
 *     - request selesai tanpa menunggu penyedia push;
 *     - kegagalan push hanya menjadi entri log + pembersihan perangkat.
 *
 * IDENTITAS NOTIFIKASI:
 *   Objek notifikasi diserialisasi UTUH, termasuk `$notification->id` yang
 *   sudah ditetapkan Laravel sebelum kanal dipanggil. Jadi `notification_id`
 *   di payload push tetap UUID baris `notifications` yang sama — satu
 *   notifikasi logis, bukan record kedua.
 *
 * PENERIMA:
 *   Yang disimpan adalah tipe + id (morph), bukan objek model. Job selalu
 *   membaca ulang perangkat milik penerima saat dijalankan, sehingga
 *   subscription yang ditambah/dihapus setelah job masuk antrean tetap
 *   dihormati dan model basi tidak pernah ikut terserialisasi.
 */
class SendWebPush implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Percobaan untuk kegagalan TRANSPORT (penyedia tidak bisa dihubungi). */
    public int $tries = 3;

    /** Jeda antar percobaan (detik): beri waktu gangguan jaringan reda. */
    public array $backoff = [10, 60];

    /** Batas waktu satu percobaan, selaras dengan timeout klien di config. */
    public int $timeout = 30;

    public function __construct(
        public string $notifiableType,
        public int|string $notifiableId,
        public Notification $notification,
    ) {}

    public function handle(): void
    {
        $notifiable = $this->resolveNotifiable();

        // Penerima sudah dihapus (mis. akun dinonaktifkan) -> tidak ada tujuan.
        if ($notifiable === null) {
            return;
        }

        if (! method_exists($notifiable, 'pushSubscriptions')
            || ! method_exists($this->notification, 'toWebPush')) {
            return;
        }

        /** @var Collection<int, PushSubscription> $subscriptions */
        $subscriptions = $notifiable->pushSubscriptions()->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $message = $this->notification->toWebPush($notifiable);

        if ($message === null) {
            return;
        }

        $payload = json_encode($message->toArray(), JSON_THROW_ON_ERROR);

        /** @var WebPush $webPush */
        $webPush = app(WebPush::class);

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(
                new WebPushSubscription(
                    $subscription->endpoint,
                    $subscription->public_key,
                    $subscription->auth_token,
                    $subscription->content_encoding ?? ContentEncoding::aes128gcm,
                ),
                $payload,
            );
        }

        // flush() melempar bila penyedia push tidak bisa dihubungi sama sekali.
        // Itu satu-satunya kegagalan yang layak dicoba ulang, jadi exception
        // dibiarkan naik ke antrean; kegagalan PER PERANGKAT ditangani di bawah.
        $this->handleReports($webPush->flush(), $subscriptions, $notifiable);
    }

    /**
     * Tangani laporan pengiriman per perangkat.
     *
     * @param  iterable<MessageSentReport>  $reports
     * @param  Collection<int, PushSubscription>  $subscriptions
     */
    protected function handleReports(
        iterable $reports,
        Collection $subscriptions,
        object $notifiable,
    ): void {
        // Endpoint -> subscription, supaya laporan bisa dipetakan kembali ke
        // baris yang tepat tanpa menebak.
        $byEndpoint = $subscriptions->keyBy('endpoint');

        foreach ($reports as $report) {
            if ($report->isSuccess()) {
                continue;
            }

            /** @var PushSubscription|null $subscription */
            $subscription = $byEndpoint->get($report->getEndpoint());

            $this->logFailure($subscription, $report->getReason(), $notifiable);

            if ($subscription === null) {
                continue;
            }

            // Hanya perangkat yang benar-benar dilaporkan kedaluwarsa yang
            // dibuang. Subscription milik user lain tidak pernah tersentuh
            // karena query selalu dibatasi ke pemiliknya.
            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()
                    ->whereKey($subscription->getKey())
                    ->where('subscribable_type', $notifiable->getMorphClass())
                    ->where('subscribable_id', $notifiable->getKey())
                    ->delete();
            }
        }
    }

    /**
     * Dipanggil antrean setelah percobaan terakhir gagal (transport).
     *
     * Tidak pernah melempar: kegagalan push tidak boleh menjadi kegagalan
     * aplikasi, dan notifikasi database sudah tersimpan sejak awal.
     */
    public function failed(?Throwable $e): void
    {
        Log::warning('Web push menyerah setelah beberapa percobaan.', [
            'notifiable_id' => $this->notifiableId,
            'notification' => $this->notification::class,
            'exception' => $e ? $e::class : null,
        ]);
    }

    /** Penerima notifikasi, dibaca ulang dari penyimpanan saat job dijalankan. */
    protected function resolveNotifiable(): ?object
    {
        // Mendukung morph map maupun nama kelas langsung.
        $class = Relation::getMorphedModel($this->notifiableType) ?? $this->notifiableType;

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        return (new $class)->newQuery()->find($this->notifiableId);
    }

    /**
     * Catat kegagalan pengiriman tanpa membocorkan rahasia.
     *
     * Endpoint sengaja TIDAK dicatat (ia mengidentifikasi perangkat spesifik);
     * yang dicatat hanya id baris subscription milik kita sendiri.
     */
    protected function logFailure(
        ?PushSubscription $subscription,
        string $reason,
        object $notifiable,
    ): void {
        Log::warning('Web push tidak terkirim ke salah satu perangkat.', [
            'push_subscription_id' => $subscription?->getKey(),
            'notifiable_id' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            'notification' => $this->notification::class,
            'reason' => $reason,
        ]);
    }
}
