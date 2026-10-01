# Modul: Web Push (VAPID)

Sinkron dengan kode: **2026-09-28**. Sumber: `app/Providers/WebPushServiceProvider.php`, `app/Notifications/Channels/WebPushChannel.php`, `app/Jobs/SendWebPush.php`, `app/Http/Controllers/PushSubscriptionController.php`, `app/Models/PushSubscription.php`, `config/webpush.php`.

## Ringkasan alur

```
Aksi bisnis (approve / reminder / notifikasi manual)
   └─> Notification::via() = ['database', WebPushChannel::class]
         ├─ database  → baris notifications (UUID)
         └─ WebPushChannel → validasi → SendWebPush::dispatch(...)   [job]
                                └─ worker → Minishlink\WebPush → endpoint browser
```

## Keamanan kunci VAPID

- `VAPID_PRIVATE_KEY` **hanya** dibaca dari `.env`. Tidak pernah dikirim ke klien, tidak masuk manifest, tidak masuk service worker, dan tidak masuk payload push.
- Hanya `VAPID_PUBLIC_KEY` yang sampai ke browser, dipakai sebagai `applicationServerKey` saat subscribe.
- Jangan mengisi nilai asli pada `.env.example` atau berkas apa pun yang di-commit.

## Penyedia layanan

`WebPushServiceProvider` (terdaftar di `bootstrap/providers.php`) mengikat `Minishlink\WebPush\WebPush` dengan konfigurasi VAPID dari `config/webpush.php`:

- `vapidAuth()` mengembalikan `[]` bila salah satu kunci kosong → **push dinonaktifkan total tanpa error**.
- `setReuseVAPIDHeaders(true)` — header VAPID dipakai ulang antar-permintaan dalam satu job.
- `setAutomaticPadding()` mengikuti konfigurasi.

## Konfigurasi (`config/webpush.php`)

| Kunci | Env | Catatan |
|---|---|---|
| `vapid.subject` | `VAPID_SUBJECT` | Kontak pengelola |
| `vapid.public_key` | `VAPID_PUBLIC_KEY` | Boleh sampai ke browser |
| `vapid.private_key` | `VAPID_PRIVATE_KEY` | **Rahasia** |
| `vapid.pem_file` | `VAPID_PEM_FILE` | Opsional |
| `model` | — | `App\Models\PushSubscription` |
| `table_name` | `WEBPUSH_DB_TABLE` | Default `push_subscriptions` |
| `database_connection` | `WEBPUSH_DB_CONNECTION` | Default mengikuti `DB_CONNECTION` |
| `client_options.timeout` | `WEBPUSH_TIMEOUT` | Default **5** detik |
| `client_options.connect_timeout` | `WEBPUSH_CONNECT_TIMEOUT` | Default **5** detik |
| `automatic_padding` | `WEBPUSH_AUTOMATIC_PADDING` | Default `true`; jadikan `false` hanya untuk Firefox Android dengan endpoint v1 |

Timeout sengaja pendek: pengiriman berjalan **di dalam job**, jadi endpoint push yang lambat hanya menahan worker — bukan request pengguna. Batas ini juga menjaga satu job tidak menggantung lebih lama dari `timeout` job.

Generate kunci: `php artisan webpush:vapid`.

## Kanal `WebPushChannel`

Lima prinsip (lihat juga [notifications.md](notifications.md)):

1. Tidak pernah membuat baris notifikasi kedua — pencatatan milik kanal `database`.
2. Tidak pernah melempar exception ke pemanggil; kegagalan push tidak menggagalkan aksi bisnis.
3. No-op bila VAPID belum dikonfigurasi.
4. Hanya menghapus subscription yang dilaporkan kedaluwarsa (404/410), dan hanya milik notifiable bersangkutan.
5. Payload dibatasi: `title`, `body`, `icon`, `badge`, `type`, `notification_id`, `url`.

Job di-dispatch dengan objek notifikasi utuh: `SendWebPush::dispatch($notifiable->getMorphClass(), $notifiable->getKey(), $notification)`, sehingga `$notification->id` (UUID baris database) tetap identik di sisi worker dan notifikasi yang diklik dapat ditandai terbaca.

## Job `SendWebPush`

| Properti | Nilai |
|---|---|
| `$tries` | 3 |
| `$backoff` | `[10, 60]` detik |
| `$timeout` | 30 detik |

- Notifiable di-resolve **ulang saat job berjalan**, bukan diserialisasi — data pengguna selalu segar.
- `handleReports()` memakai `$report->isSubscriptionExpired()` dan **hanya** menghapus baris subscription yang kedaluwarsa, ter-scope ke pemiliknya.
- `logFailure()` mencatat `push_subscription_id` — **endpoint tidak pernah masuk log**.
- `failed(?Throwable $e)` tidak pernah melempar exception.

## Subscription

`PushSubscription` adalah turunan model paket; `User` memakai trait `Notifiable` + `HasPushSubscriptions`.

`PushSubscriptionController`:

- `store()` — memvalidasi payload lalu memanggil `$request->user()->updatePushSubscription(...)`. **Pemilik selalu diambil dari `$request->user()`**, tidak pernah dari input klien.
- `destroy()` — memanggil `$request->user()->deletePushSubscription($data['endpoint'])`. Endpoint milik pengguna lain tidak dapat dihapus.

Rute `push-subscriptions` (POST/DELETE) hanya bermiddleware `auth` — tidak ada capability khusus; yang menjaga adalah kepemilikan di atas.

## Batas privasi

Payload push berisi teks notifikasi dan id, bukan isi laporan, bukan data murid, bukan berkas. Endpoint subscription tidak pernah dicatat ke log. Isi laporan tetap hanya dapat dibuka lewat halaman terautorisasi.

## Deployment

Push **tidak akan terkirim** tanpa queue worker, karena pengiriman selalu lewat job. Lihat [queue-worker.md](../operations/queue-worker.md) untuk perintah dan pengawasan worker.

Bila `VAPID_PUBLIC_KEY` kosong, tombol aktifkan notifikasi tidak dirender sama sekali (lihat `resources/views/partials/push-notifications.blade.php`) — jadi fitur mati dengan rapi, bukan gagal diam-diam.

## Test terkait

`WebPushTest` (memakai `tests/Support/FakeWebPush.php` dan `tests/Support/ImmediatePushProbe.php`).
