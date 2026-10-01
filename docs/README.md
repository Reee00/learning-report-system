# Dokumentasi Learning Report System

Dokumentasi ini disinkronkan dengan codebase pada **2026-09-29**. Dokumen yang tidak tersentuh perubahan kode 2026-09-29 (instalasi, deployment, jadwal, kehadiran, PWA, web push) masih memuat tanggal sinkronisasi sebelumnya.

Mulai dari [contextproject.md](../contextproject.md), lalu baca dokumen bernomor sesuai topik, atau langsung ke dokumentasi modul bila Anda mencari satu topik tertentu.

Sumber kebenaran adalah **kode**: `routes/web.php`, `app/Models`, `app/Services`, controller, `database/migrations`, `config`, dan `tests`. Bila dokumen dan kode berbeda, kode yang benar — perbarui dokumennya. Bila sebuah nilai hanya ada di `.env` dan tidak ada di repositori, dokumen menandainya `NEED VERIFICATION`.

## Peta dokumentasi

### Dokumen topik bernomor

| Dok | Topik |
|---|---|
| [00](00_RINGKASAN_EKSEKUTIF.md) | Ringkasan eksekutif |
| [01](01_ARSITEKTUR_SISTEM.md) | Arsitektur sistem |
| [02](02_DATABASE_DOKUMENTASI.md) | Database |
| [03](03_AUTENTIKASI_OTORISASI.md) | Autentikasi & otorisasi |
| [04](04_API_DOKUMENTASI.md) | Route & endpoint |
| [05](05_PROSES_BISNIS.md) | Proses bisnis |
| [06](06_STRUKTUR_FOLDER.md) | Struktur folder |
| [07](07_PANDUAN_INSTALASI.md) | Panduan instalasi |
| [08](08_PANDUAN_DEPLOYMENT.md) | Panduan deployment |
| [09](09_FITUR_DAN_MODUL.md) | Fitur & modul |
| [10](10_FRONTEND_BACKEND.md) | Frontend & backend |
| [11](11_PENGUJIAN_DAN_TROUBLESHOOTING.md) | Pengujian & troubleshooting |
| [12](12_KEAMANAN_PEMELIHARAAN_PENGEMBANGAN.md) | Keamanan & pemeliharaan |

### Dokumentasi modul

| Modul | Isi |
|---|---|
| [reports.md](modules/reports.md) | Siklus hidup laporan, aturan satu sesi = satu laporan, review vs arsip |
| [attendance.md](modules/attendance.md) | Drill-down kehadiran, scope, export CSV/Excel/PDF |
| [schedule.md](modules/schedule.md) | Template/pola, sesi, `is_active`, coach pendamping, impor Excel |
| [notifications.md](modules/notifications.md) | Kanal database & push, reminder per sesi |
| [media.md](modules/media.md) | Penyimpanan lokal privat dan penyajian terotorisasi |
| [pwa.md](modules/pwa.md) | Manifest, service worker, strategi cache |
| [web-push.md](modules/web-push.md) | VAPID, kanal push, job pengiriman |
| [accounts.md](modules/accounts.md) | Account Settings semua role (nama, nomor WhatsApp, ganti password), normalisasi nomor, tautan `wa.me`, dan tombol tampil/sembunyikan password |

### Operasional, pengembangan, referensi

| Dokumen | Isi |
|---|---|
| [operations/queue-worker.md](operations/queue-worker.md) | Queue, worker, deployment wajib |
| [development/seeder.md](development/seeder.md) | Akun uji dan data seed |
| [reference/permissions.md](reference/permissions.md) | Matriks 7 peran × seluruh capability |
| [historical/README.md](historical/README.md) | Arsip dokumen audit/stabilisasi |

Dokumentasi QA berada di [qa/](qa/), dan manual pengguna di [user-manual/manual-book.md](user-manual/manual-book.md).

## Peran resmi

SuperAdmin, Relation, SPV Coach, Coach, PIC DK SCHOOL, TEACHER SCHOOL, dan Finance. Namespace `Admin` dan prefix URL `/admin/*` hanyalah kompatibilitas — **tidak ada peran bernama `admin`**.
