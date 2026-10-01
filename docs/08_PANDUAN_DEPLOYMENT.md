# Panduan Deployment

Disinkronkan dengan kode: **2026-09-28**.

## Target

PHP 8.4 (`^8.4`) dan MySQL. Deployment **bukan** berbasis Docker — `Dockerfile` sudah dihapus (2026-09-11), sehingga item QA lama "Docker PHP 8.3 vs requirement 8.4" **tidak lagi relevan**.

Nilai host, credential, `APP_ENV`, queue, cache, session, dan filesystem harus diverifikasi dari environment deployment; repositori tidak menyertakan `.env`.

## Checklist Deployment

### Wajib

- [ ] PHP 8.4 dan ekstensi yang dibutuhkan Composer terpasang.
- [ ] `composer install --no-dev --optimize-autoloader`.
- [ ] `.env` produksi terisi: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, kredensial MySQL.
- [ ] `php artisan migrate --force`.
- [ ] `storage/` dan `bootstrap/cache/` dapat ditulis proses web.
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
- [ ] **Queue worker berjalan terus-menerus** (lihat bagian di bawah).
- [ ] Scheduler sistem memanggil `php artisan schedule:run` setiap menit.
- [ ] **Queue worker di-restart setiap deploy:** `php artisan queue:restart`.

### Media

- [ ] `storage/app/report-media` ada dan writable.
- [ ] Direktori itu **tidak** diekspos web server dan **tidak** ada symlink ke `public/`.
- [ ] Akses media hanya lewat `/media/{media}`.

### Batas Unggahan PHP

Sesuaikan pada konfigurasi PHP server produksi:

```ini
upload_max_filesize = 101M
post_max_size       = 310M
memory_limit        = 256M
```

Nilai ini mengikuti batas aplikasi: video 3 × 100 MB dan foto/bukti absensi 10 × 10 MB. Bila server menolak lebih dulu, `PostTooLargeException` dirender menjadi JSON 413 (AJAX) atau redirect-back berisi pesan galat.

### Web Push (bila diaktifkan)

- [ ] `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` terisi di `.env` produksi.
- [ ] `VAPID_PRIVATE_KEY` **tidak** pernah masuk repositori, log, atau berkas yang di-commit.
- [ ] Aplikasi disajikan lewat **HTTPS** — service worker dan Web Push hanya berjalan pada secure context.

## Queue Worker — Wajib

`QUEUE_CONNECTION` default repositori adalah `database`. Tanpa worker yang berjalan:

- Notifikasi manual dan pemberitahuan approve/reject **tidak terkirim**.
- **Web Push tidak terkirim sama sekali.**

```bash
php artisan queue:work --tries=3 --timeout=60
```

Jalankan di bawah supervisor, systemd, atau Windows Service agar otomatis dijalankan ulang bila mati.

**Penting:** pastikan `--timeout` worker **lebih kecil** dari `retry_after` koneksi database queue (default 90 detik). Bila `--timeout` lebih besar, satu job yang masih berjalan akan dianggap gagal dan dijalankan **dua kali**.

## Scheduler

Satu-satunya tugas terjadwal adalah retensi activity log:

```
02:17 harian — php artisan activity-logs:purge
```

Tambahkan satu entri cron:

```
* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

## Pemeriksaan Pasca-Deploy

- [ ] Login berhasil dan tidak ada galat 500.
- [ ] `/up` membalas 200.
- [ ] Unggah satu foto laporan berhasil, dan berkasnya muncul di `storage/app/report-media`.
- [ ] `/media/{id}` menyajikan berkas hanya untuk pengguna yang berhak; akses lintas sekolah ditolak.
- [ ] Kirim notifikasi manual, lalu pastikan barisnya masuk ke tabel `notifications` **dan** worker memprosesnya (`php artisan queue:failed` kosong).
- [ ] Aktivasi notifikasi push di browser, lalu pastikan baris `push_subscriptions` bertambah.
- [ ] Buka `/manifest.json` dan pastikan service worker terdaftar (DevTools → Application).

## Catatan Historis

- Cloudinary **sudah dihapus penuh** dari kode (2026-09-11). Tidak ada perintah migrasi Cloudinary, tidak ada helper, dan tidak ada pemanggilan API. Berkas lama di Cloudinary, bila masih ada, harus dipindahkan manual di luar aplikasi.
- Tidak ada langkah deployment khusus Docker.
