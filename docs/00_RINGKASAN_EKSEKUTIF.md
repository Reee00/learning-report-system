# Ringkasan Eksekutif

Disinkronkan dengan kode: **2026-09-28**.

LRS adalah aplikasi Laravel untuk mengelola sekolah, siswa, kelas, program, jadwal mengajar, laporan Coach, kehadiran, review, dan media pembelajaran. Aplikasi sudah berupa **PWA** dengan **Web Push**.

## Peran Aktif

SuperAdmin, Relation, SPV Coach, Coach, PIC DK SCHOOL, TEACHER SCHOOL, dan Finance. `admin` bukan peran runtime; `/admin/*` hanya namespace kompatibilitas.

## Alur Utama

1. Relation/SuperAdmin menyiapkan master data (sekolah, program, kelas, siswa, pengguna).
2. Jadwal mengajar dibuat sebagai **pola** (`teaching_schedule_templates`) lalu di-*generate* menjadi **sesi** (`teaching_schedules`), atau diimpor dari berkas Excel.
3. Coach mengisi laporan untuk sesinya — materi, aktivitas, absensi murid, media — lalu menyimpan draft atau mengirim.
4. Relation/SuperAdmin meninjau di **antrean review** (hanya `submitted` dan `rejected`); arsip lengkap ada di halaman terpisah yang baca-saja.
5. Reviewer menyetujui atau menolak. Penolakan mewajibkan alasan. Laporan yang ditolak diperbaiki coach lalu dikirim ulang.
6. **Satu sesi = satu laporan.** Sesi nonaktif tidak menerima laporan baru.

Peran sekolah hanya melihat data sekolah yang diplot, dan hanya laporan berstatus `approved`.

## Perubahan Aturan Terbaru (2026-09-27 / 2026-09-28)

| Perubahan | Dampak |
|---|---|
| `reports.teaching_schedule_id` unik | Satu sesi maksimum satu laporan, siapa pun pembuatnya; menutup celah balapan lewat indeks unik |
| `teaching_schedules.is_active` | Sesi dapat dinonaktifkan tanpa dihapus; nonaktif ≠ hapus |
| Review dipisah dari arsip | Dua halaman, dua capability: `reports.review` vs `reports.view_all` |
| PWA + Web Push | Manifest, service worker, langganan push per perangkat |
| Media sepenuhnya lokal | Cloudinary dihapus dari kode |

## Media dan Penyimpanan

Media disimpan di disk privat `report_media` (`storage/app/report-media`), **di luar** `public/`, dan hanya disajikan lewat route terotorisasi `/media/{media}`. Database menyimpan metadata (`report_media`), bukan binernya.

Integrasi Cloudinary **sudah dihapus penuh** dari kode. Setiap referensi Cloudinary yang masih tersisa di dokumen lama bersifat historis dan tidak berlaku.

## Database

Target produksi adalah MySQL. `config/database.php` memiliki default SQLite ketika `DB_CONNECTION` tidak diset, sehingga driver yang benar-benar aktif bergantung pada `.env` dan berstatus `NEED VERIFICATION` bila `.env` tidak tersedia.

Schema saat ini dibangun oleh **28 migrasi**.

## Antrean (Queue)

`QUEUE_CONNECTION` default repositori adalah **`database`**. Aplikasi **memerlukan queue worker yang berjalan** agar notifikasi dan Web Push benar-benar terkirim. Lihat [operations/queue-worker.md](operations/queue-worker.md).

## Status Dokumentasi

- Seluruh dokumen topik bernomor, dokumentasi modul, dan referensi disinkronkan pada **2026-09-28**.
- Informasi yang hanya tersedia di environment deployment ditandai `NEED VERIFICATION`.
- Catatan audit dan stabilisasi lama dipindahkan ke [historical/](historical/README.md) dan dipertahankan sebagai catatan sejarah, bukan sebagai deskripsi kondisi saat ini.
