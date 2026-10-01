# Operasional: Queue & Worker

Sinkron dengan kode: **2026-09-28**. Sumber: `config/queue.php`, `.env.example`, `app/Notifications/CustomNotification.php`, `app/Jobs/SendWebPush.php`, `database/migrations/2026_09_28_000003_create_jobs_table.php`.

## Kesimpulan singkat

**Aplikasi ini memerlukan queue worker yang berjalan terus-menerus.** Tanpa worker:

- Notifikasi manual dan pemberitahuan approve/reject **tidak terkirim** (`CustomNotification implements ShouldQueue`).
- **Web Push tidak terkirim sama sekali** — pengiriman selalu lewat job `SendWebPush`.

Yang tetap berjalan tanpa worker: penulisan baris notifikasi pengingat ke tabel `notifications`, karena `ReportReminderNotification` tidak diantrekan.

## Konfigurasi

```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'database'),
```

**Default repositori adalah `database`** — bukan `sync`. Artinya, pada instalasi baru tanpa `.env` khusus, job benar-benar masuk tabel dan menunggu worker; job tidak lagi dijalankan seketika di dalam request.

`.env.example`:

```env
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
```

Koneksi `database`:

| Kunci | Env | Default |
|---|---|---|
| `connection` | `DB_QUEUE_CONNECTION` | mengikuti koneksi DB utama |
| `table` | `DB_QUEUE_TABLE` | `jobs` |
| `queue` | `DB_QUEUE` | `default` |
| `retry_after` | `DB_QUEUE_RETRY_AFTER` | 90 detik |
| `after_commit` | — | `false` |

Tabel `jobs` dibuat oleh migrasi `2026_09_28_000003_create_jobs_table`. Tabel `failed_jobs` menampung job yang gagal permanen.

## Perintah

```bash
# Produksi — worker tetap, auto-restart bila kode berubah
php artisan queue:work --tries=3 --timeout=60

# Pemantauan job gagal
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
```

### `retry_after` vs `--timeout`

Nilai `retry_after` (90 detik) **harus lebih besar** daripada `--timeout` worker. Bila `--timeout` melebihi `retry_after`, satu job yang masih berjalan akan dianggap gagal dan dijalankan **dua kali**. Karena `SendWebPush` memakai `$timeout = 30` detik, nilai `--timeout=60` masih aman terhadap `retry_after=90`.

## Job yang ada

| Job | `$tries` | `$backoff` | `$timeout` | Fungsi |
|---|---|---|---|---|
| `App\Jobs\SendWebPush` | 3 | `[10, 60]` | 30 detik | Mengirim satu notifikasi push |

`SendWebPush` me-resolve notifiable **saat job berjalan**, tidak menyimpan salinannya, sehingga data pengguna selalu segar. Kegagalan permanen ditangani `failed(?Throwable $e)` yang tidak pernah melempar exception.

## Di balik layar saat sebuah notifikasi dibuat

1. Kanal `database` menulis baris `notifications` (UUID) — **selalu**, baik ada worker maupun tidak untuk kasus reminder.
2. `WebPushChannel` memvalidasi lalu `SendWebPush::dispatch(...)`.
3. Worker mengambil job, mengenkripsi payload, mengirim ke endpoint, dan menghapus subscription yang dijawab 404/410.

Karena langkah 3 hanya terjadi di worker, subscription yang kedaluwarsa **tidak akan dibersihkan** jika worker mati — daftar subscription perlahan menumpuk sampai worker dijalankan kembali. Ini gejala pertama yang terlihat saat worker lupa dinyalakan.

## Deployment

Wajib dipenuhi sebelum aplikasi dianggap siap produksi:

1. `QUEUE_CONNECTION` di-set (default `database` sudah benar untuk instalasi standar).
2. Tabel `jobs` dan `failed_jobs` sudah dimigrasi.
3. Sebuah **queue worker berjalan terus-menerus**, diawasi supervisor/systemd/Windows Service agar otomatis dijalankan ulang bila mati.
4. `php artisan queue:restart` dijalankan setiap kali kode di-deploy, agar worker memuat kode baru.
5. Bila Web Push diinginkan: `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` terisi (lihat [web-push.md](../modules/web-push.md)).

Perintah terjadwal yang ada hanya `activity-logs:purge` (harian 02:17, `routes/console.php`), dijalankan scheduler pada cron/penjadwal sistem — bukan oleh queue worker.

## Saat pengembangan & pengujian

`phpunit.xml` mengatur `QUEUE_CONNECTION=sync`, sehingga job dijalankan langsung di dalam test dan hasilnya deterministik. **Jangan** mengandalkan perilaku itu di produksi — `sync` menyembunyikan setiap masalah yang hanya muncul saat job benar-benar diantrekan.

## Gejala & penyebab

| Gejala | Penyebab paling sering |
|---|---|
| Notifikasi tidak pernah sampai ke perangkat | Worker tidak berjalan |
| Baris `notifications` bertambah tetapi tidak ada push | Worker tidak berjalan |
| Push terkirim dua kali | `--timeout` worker melebihi `retry_after` |
| Tabel `jobs` terus bertambah | Worker tidak berjalan, atau worker crash berulang |
| Perubahan kode tidak terpakai worker | `php artisan queue:restart` belum dijalankan |
| Push tidak terkirim meski worker hidup | Kunci VAPID kosong → kanal menjadi no-op (bukan error) |

## Test terkait

`WebPushTest` (memakai `QUEUE_CONNECTION=sync` dari `phpunit.xml`), `CustomNotificationTest`.
