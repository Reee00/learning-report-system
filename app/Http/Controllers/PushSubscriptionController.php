<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Pendaftaran perangkat untuk Web Push (PWA Phase 2).
 *
 * ATURAN KEAMANAN:
 *   - Hanya untuk user yang sudah login (middleware auth) + CSRF.
 *   - `user_id` TIDAK PERNAH dibaca dari request; pemilik subscription selalu
 *     diambil dari `$request->user()`. Klien tidak bisa mendaftarkan perangkat
 *     atas nama orang lain.
 *   - Endpoint push adalah URL yang nanti di-POST oleh server, jadi ia
 *     divalidasi sebagai anti-SSRF: wajib https, tanpa IP literal, tanpa port
 *     non-standar. Tanpa ini, user terautentikasi bisa memakai fitur push
 *     sebagai alat memindai jaringan internal.
 *   - Hapus subscription selalu dibatasi ke baris milik user yang login.
 */
class PushSubscriptionController extends Controller
{
    /**
     * Simpan/perbarui subscription perangkat milik user yang login.
     *
     * Idempoten: mendaftarkan ulang perangkat yang sama hanya memperbarui
     * kuncinya. Bila endpoint yang sama sebelumnya milik akun lain (mis.
     * berganti akun di browser yang sama), baris lama dipindahkan ke user ini
     * sehingga perangkat tidak lagi menerima notifikasi akun sebelumnya.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $this->assertSafeEndpoint($data['endpoint']);

        $subscription = $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['content_encoding'] ?? null,
        );

        // Balasan sengaja minimal: kunci enkripsi perangkat tidak pernah
        // dikembalikan ke klien.
        return response()->json([
            'status' => 'subscribed',
            'id' => $subscription->getKey(),
        ]);
    }

    /**
     * Lepas perangkat milik user yang login (unsubscribe).
     *
     * Idempoten: endpoint yang tidak dikenal tetap menghasilkan 200, sehingga
     * UI tidak perlu menangani error saat membersihkan perangkat yang sudah
     * hilang. Endpoint milik user lain tidak akan terhapus karena query selalu
     * dibatasi ke pemiliknya.
     */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
        ]);

        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['status' => 'unsubscribed']);
    }

    /**
     * Validasi anti-SSRF untuk endpoint push.
     *
     * Endpoint yang sah selalu berupa hostname publik lewat https:443
     * (fcm.googleapis.com, updates.push.services.mozilla.com, *.notify.windows.com,
     * web.push.apple.com). Yang di luar itu ditolak, terutama IP literal —
     * itulah bentuk serangan ke alamat internal (169.254.169.254, 127.0.0.1,
     * 10.x, 192.168.x, ::1).
     */
    protected function assertSafeEndpoint(string $endpoint): void
    {
        $parts = parse_url($endpoint);

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        $port = $parts['port'] ?? null;

        $valid = $parts !== false
            && $scheme === 'https'
            && $host !== ''
            && ($port === null || $port === 443)
            // IP literal (v4 maupun v6) selalu ditolak; push service memakai nama host.
            && filter_var($host, FILTER_VALIDATE_IP) === false
            // Host tanpa titik (mis. "localhost") ditolak.
            && str_contains($host, '.');

        if (! $valid) {
            throw ValidationException::withMessages([
                'endpoint' => 'Endpoint push tidak valid.',
            ]);
        }
    }
}
