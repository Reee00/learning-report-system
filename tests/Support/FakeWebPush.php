<?php

namespace Tests\Support;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\SubscriptionInterface;
use Minishlink\WebPush\WebPush;

/**
 * Pengganti Minishlink\WebPush untuk pengujian (PWA Phase 2).
 *
 * Tidak ada jaringan dan tidak ada kriptografi di sini: yang diuji adalah
 * keputusan kanal push milik aplikasi — penerima mana yang dikirimi, payload
 * apa yang dikirim, perangkat mana yang dibuang saat kedaluwarsa, dan bahwa
 * kegagalan pengiriman tidak pernah merusak notifikasi database.
 *
 * Pengiriman sungguhan (enkripsi payload + JWT VAPID) adalah tanggung jawab
 * pustaka Minishlink dan diuji terpisah.
 */
class FakeWebPush extends WebPush
{
    /** @var list<array{endpoint: string, payload: ?string}> */
    public array $queued = [];

    /**
     * Laporan yang akan dihasilkan flush(), berurutan.
     *
     * @var list<array{endpoint: string, success: bool, status: ?int, reason: string}>
     */
    public array $reports = [];

    /** Kegagalan yang harus dilempar saat flush() (mis. penyedia push tidak bisa dihubungi). */
    public ?string $flushError = null;

    public function __construct()
    {
        // Klien Guzzle asli hanya untuk memenuhi syarat konstruktor induk;
        // tidak ada request yang benar-benar dikirim karena flush() di-override.
        parent::__construct([], [], new Client(['timeout' => 1]));
    }

    public function queueNotification(SubscriptionInterface $subscription, ?string $payload = null, array $options = [], array $auth = []): void
    {
        $this->queued[] = [
            'endpoint' => $subscription->getEndpoint(),
            'payload' => $payload,
        ];
    }

    public function flush(?int $batchSize = null): \Generator
    {
        if ($this->flushError !== null) {
            throw new \RuntimeException($this->flushError);
        }

        foreach ($this->reports as $report) {
            yield new MessageSentReport(
                new Request('POST', $report['endpoint']),
                $report['status'] === null ? null : new Response($report['status']),
                $report['success'],
                $report['reason'],
            );
        }
    }

    /** Antrian berhasil dikirim semua (HTTP 201) untuk tiap endpoint. */
    public function succeedAll(): self
    {
        $this->reports = array_map(
            fn (array $item): array => [
                'endpoint' => $item['endpoint'],
                'success' => true,
                'status' => 201,
                'reason' => 'Created',
            ],
            $this->queued,
        );

        return $this;
    }

    /** @return list<string> */
    public function queuedEndpoints(): array
    {
        return array_column($this->queued, 'endpoint');
    }

    /** @return array<string, mixed>|null */
    public function payloadFor(string $endpoint): ?array
    {
        foreach ($this->queued as $item) {
            if ($item['endpoint'] === $endpoint) {
                return json_decode((string) $item['payload'], true);
            }
        }

        return null;
    }
}
