# Learning Report System (LRS)

Disinkronkan dengan kode: **2026-09-28**.

## Deskripsi Proyek

**Learning Report System** adalah aplikasi web untuk mengelola dan melaporkan hasil pembelajaran: data master sekolah, jadwal mengajar, laporan coach beserta kehadiran murid dan lampiran media, alur review laporan, hingga notifikasi ke perangkat.

## Fitur Utama

- **Tujuh peran dengan kewenangan berbasis capability** — SuperAdmin, Relation, SPV Coach, Coach, PIC DK SCHOOL, TEACHER SCHOOL, dan Finance. Kewenangan didefinisikan di kode (`AuthorizationService`), bukan di tabel database.
- **Pelaporan pembelajaran** — materi, tujuan, aktivitas, catatan, kehadiran murid, serta lampiran foto/video/bukti kehadiran. Alur: draft → dikirim → direview → disetujui/ditolak → dikirim ulang. **Satu sesi jadwal hanya boleh punya satu laporan.**
- **Jadwal mengajar dua lapis** — **pola** berulang per hari + sekolah (tanggal mulai dan jumlah pertemuan melekat pada blok sekolah) yang di-*generate* menjadi **sesi** bertanggal; dilengkapi impor dari berkas Excel workbook operasional.
- **Manajemen master data** — Sekolah (dengan halaman *workspace* per sekolah), Kelas, Program, Murid, Coach, dan pengguna.
- **Kehadiran** — halaman `/attendance` bertingkat (tanggal → sekolah → kelas → sesi). Rekap akumulasi hanya muncul pada dokumen yang diunduh (CSV/Excel/PDF), tidak di halaman.
- **Penyimpanan media lokal privat** — foto, video, dan bukti kehadiran disimpan di disk privat di luar `public/`, hanya dapat diakses melalui route ber-otorisasi.
- **Notifikasi** — notifikasi dalam aplikasi (kanal database) dan **Web Push** ke perangkat (VAPID, dikirim lewat queue) untuk pengingat laporan dan pengumuman operasional.
- **PWA** — aplikasi dapat dipasang di perangkat, dengan halaman offline dan pintasan ke menu utama.
- **Activity log** — jejak audit aksi penting, hanya dapat dibaca SuperAdmin, retensi 7 hari.

## Tech Stack

| Komponen | Versi / Keterangan |
|---|---|
| PHP | `^8.4` |
| Laravel | `^12.0` |
| Database | MySQL untuk produksi; SQLite untuk pengujian dan setup cepat |
| Frontend | Blade + Bootstrap 5.3 + Bootstrap Icons (CDN), Vite untuk aset aplikasi |
| Excel | `rap2hpoutre/fast-excel` (impor), OpenSpout (workbook DIGISchool) |
| PDF | Dompdf |
| Web Push | `minishlink/web-push` |
| PWA | Service worker buatan sendiri di `public/sw.js` |
| Testing | PHPUnit `^11.5` + suite JavaScript untuk service worker |

## Prasyarat

- PHP **8.4**
- Composer
- Node.js & npm
- MySQL (produksi) — SQLite cukup untuk setup lokal cepat
- Ekstensi PHP: `openssl`, `pdo`, `mbstring`, `fileinfo`, `curl`

## Instalasi Lokal

### 1. Clone repositori

```bash
git clone https://github.com/Reee00/learning-report-system.git
cd learning-report-system
```

### 2. Jalankan setup otomatis

```bash
composer setup
```

Skrip ini menjalankan `composer install`, menyalin `.env.example` ke `.env`, membuat *app key*, menjalankan migrasi, mengunduh dependensi npm, dan menjalankan `npm run build`.

### 3. Jalankan queue worker

**Wajib.** Notifikasi dan job lain dikirim melalui queue (`QUEUE_CONNECTION=database`). Tanpa worker yang berjalan, notifikasi tidak akan terkirim dan tabel `jobs` akan menumpuk.

```bash
php artisan queue:work
```

Jalankan sebagai proses terpisah dari server web, dan gunakan supervisor di produksi. Lihat [`docs/operations/queue-worker.md`](docs/operations/queue-worker.md).

### 4. Jalankan server lokal

```bash
composer dev
```

Aplikasi dapat diakses di `http://localhost:8000`.

## Konfigurasi Environment

Nilai di bawah dibaca dari `.env`. Semua kunci sudah tersedia di `.env.example`.

| Kunci | Default | Keterangan |
|---|---|---|
| `DB_CONNECTION` | `mysql` | Gunakan `sqlite` untuk setup cepat |
| `SESSION_DRIVER` | `database` | Memerlukan tabel `sessions` |
| `QUEUE_CONNECTION` | `database` | **Wajib** ada queue worker |
| `CACHE_STORE` | `database` | Lihat catatan di bawah |
| `FILESYSTEM_DISK` | `local` | Penyimpanan umum Laravel |
| `REPORT_MEDIA_DISK` | `report_media` | Disk privat khusus media laporan (opsional; ada default di config) |
| `VAPID_SUBJECT` | `${APP_URL}` | Kontak pengelola, format `mailto:` atau URL |
| `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` | kosong | Kunci Web Push; lihat di bawah |

### Web Push (opsional)

Web Push nonaktif bila kunci VAPID kosong — aplikasi tetap berjalan normal dan tombol aktifkan notifikasi tidak muncul. Untuk mengaktifkannya:

```bash
php artisan webpush:vapid
```

Perintah ini menuliskan `VAPID_PUBLIC_KEY` dan `VAPID_PRIVATE_KEY` ke `.env`. Simpan kunci privat sebagai rahasia; menggantinya akan membatalkan seluruh langganan push yang ada. Lihat [`docs/modules/web-push.md`](docs/modules/web-push.md).

### Catatan: `CACHE_STORE`

`.env.example` menyetel `CACHE_STORE=database`, tetapi **belum ada migrasi yang membuat tabel `cache` dan `cache_locks`**. Bila aplikasi memakai cache, jalankan:

```bash
php artisan make:cache-table    # lalu: php artisan migrate
```

Atau alihkan `CACHE_STORE` ke `file`. Ini adalah temuan terbuka yang sudah didokumentasikan, bukan perilaku yang disengaja — lihat [`docs/02_DATABASE_DOKUMENTASI.md`](docs/02_DATABASE_DOKUMENTASI.md).

## Menjalankan Test

```bash
php artisan test        # suite PHP (Feature + Unit)
npm run test:pwa        # suite JavaScript untuk routing service worker
```

## Dokumentasi

Referensi lengkap ada di direktori [`docs/`](docs):

- [`docs/README.md`](docs/README.md) — peta seluruh dokumentasi
- [`docs/00_RINGKASAN_EKSEKUTIF.md`](docs/00_RINGKASAN_EKSEKUTIF.md) — ringkasan sistem
- [`docs/01_ARSITEKTUR_SISTEM.md`](docs/01_ARSITEKTUR_SISTEM.md) — arsitektur
- [`docs/04_API_DOKUMENTASI.md`](docs/04_API_DOKUMENTASI.md) — seluruh route aplikasi
- [`docs/07_PANDUAN_INSTALASI.md`](docs/07_PANDUAN_INSTALASI.md) — instalasi
- [`docs/08_PANDUAN_DEPLOYMENT.md`](docs/08_PANDUAN_DEPLOYMENT.md) — deployment
- [`docs/modules/`](docs/modules/) — dokumentasi per modul (laporan, kehadiran, jadwal, notifikasi, media, PWA, web push)
- [`docs/reference/permissions.md`](docs/reference/permissions.md) — matriks capability × peran
- [`docs/user-manual/manual-book.md`](docs/user-manual/manual-book.md) — panduan pengguna

---

*Dibangun dengan framework [Laravel](https://laravel.com/).*
