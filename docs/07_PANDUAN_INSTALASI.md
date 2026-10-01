# Panduan Instalasi

Disinkronkan dengan kode: **2026-09-28**.

## Prasyarat

| Kebutuhan | Versi | Sumber |
|---|---|---|
| PHP | `^8.4` | `composer.json` |
| Laravel | `^12.0` | `composer.json` |
| Composer | — | — |
| MySQL | Target produksi | `config/database.php` |
| Node/NPM | Hanya untuk `npm run test:pwa` | `package.json` |

SQLite dapat dipakai untuk pengembangan cepat karena `config/database.php` menjadikannya default ketika `DB_CONNECTION` tidak diset. Produksi memakai MySQL.

## Langkah Setup

```bash
composer install
cp .env.example .env          # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Atau jalankan sekaligus:

```bash
composer setup
```

`composer setup` menjalankan rangkaian di atas, dengan `migrate --force`.

`npm run build` saat ini **no-op** (`echo 'No build step required'`) — layout utama memakai Bootstrap 5.3 dan Bootstrap Icons dari CDN, dan tidak ada `@vite` di layout. `npm install` hanya diperlukan bila Anda ingin menjalankan test service worker.

## Environment

Set sesuai environment Anda:

| Variabel | Catatan |
|---|---|
| `APP_*` | Nama, env, URL, key |
| `DB_*` | MySQL untuk produksi |
| `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | **`database`** — lihat peringatan di bawah |
| `CACHE_STORE` | `database` pada `.env.example`; lihat peringatan |
| `FILESYSTEM_DISK` | `local` |
| `REPORT_MEDIA_DISK` | Default `report_media` |
| `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` | Opsional; bila kosong, Web Push dinonaktifkan total |

Nilai `.env` yang sebenarnya berstatus `NEED VERIFICATION` — repositori hanya menyertakan `.env.example`.

> ### Dua hal yang wajib diperiksa sebelum menjalankan
>
> **1. Queue worker.** `QUEUE_CONNECTION=database` berarti job benar-benar mengantre. Notifikasi dan **seluruh Web Push tidak akan terkirim** tanpa worker yang berjalan. Saat pengembangan jalankan `php artisan queue:work` di terminal terpisah, atau `composer dev` yang sudah menyertakan `queue:listen`. Lihat [operations/queue-worker.md](operations/queue-worker.md).
>
> **2. Tabel cache.** `.env.example` menyetel `CACHE_STORE=database`, tetapi **tidak ada migrasi yang membuat tabel `cache`/`cache_locks`**. Bila tabelnya tidak ada, operasi cache akan gagal. Pilih salah satu: tambahkan migrasi tabel cache, atau ubah `CACHE_STORE` ke `file`. Ini **temuan terbuka**, bukan perilaku yang sudah diverifikasi.

## Media

Media laporan berada pada disk `report_media` di `storage/app/report-media` — **di luar** `public/`, tanpa symlink, dan hanya dapat diakses lewat route terotorisasi `/media/{media}`. Pastikan direktori storage dapat ditulis oleh proses web.

## Web Push (Opsional)

```bash
php artisan webpush:vapid
```

Salin nilai yang dihasilkan ke `.env`. Bila salah satu kunci kosong, kanal push menjadi no-op — fitur mati dengan rapi, bukan gagal diam-diam, dan tombol aktivasi tidak dirender.

## Menjalankan Test

```bash
php artisan test      # atau: composer test
npm run test:pwa      # 37 pemeriksaan service worker
```

`phpunit.xml` memakai SQLite `:memory:` dan `QUEUE_CONNECTION=sync`, sehingga test tidak menyentuh database MySQL pengembangan dan job berjalan seketika. Lihat [11_PENGUJIAN_DAN_TROUBLESHOOTING.md](11_PENGUJIAN_DAN_TROUBLESHOOTING.md).
