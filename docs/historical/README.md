# Dokumentasi Historis

Disinkronkan: **2026-09-28**.

Folder ini menampung **catatan sejarah** — audit, laporan stabilisasi, dan catatan implementasi dari periode tertentu. Isinya **bukan** deskripsi kondisi sistem saat ini.

> **Status:** catatan di sini bersifat arsip. Jangan memakai angka, daftar berkas, atau klaim status dari dokumen historis sebagai fakta terkini. Untuk kondisi sekarang, rujuk [dokumentasi modul](../README.md) dan dokumen topik bernomor.

## Mengapa dokumen-dokumen ini dikonsolidasikan

Sebelum sinkronisasi 2026-09-28, terdapat sepuluh berkas di `docs/audit/`, `docs/stabilization/`, dan `docs/implementation/`. Setelah diperiksa, **seluruhnya hanya berisi pointer tanpa substansi** — masing-masing tiga baris yang mengarahkan pembaca ke dokumen lain. Tidak ada temuan, angka, atau analisis yang hilang bila berkas-berkas itu digabungkan.

Karena itu isinya diringkas di halaman ini, dan berkas aslinya dihapus. Referensi yang menunjuk ke sana (`implementation_planning.md`, `_backup_unused/MOVE_HISTORY.md`) sudah diperbarui agar mengarah ke halaman ini.

## Isi yang pernah ada

### `docs/audit/`

| Berkas lama | Topik | Status materi |
|---|---|---|
| `existing-system-audit.md` | Audit sistem yang berjalan | Pointer; tidak ada temuan tersimpan |
| `data-model-map.md` | Peta model data | Pointer; menyebut "14 migration" — angka itu **usang** (kini 28) |
| `role-permission-map.md` | Peta peran & izin | Pointer; peta yang berlaku ada di [reference/permissions.md](../reference/permissions.md) |
| `route-map.md` | Peta route | Pointer; peta yang berlaku ada di [04_API_DOKUMENTASI.md](../04_API_DOKUMENTASI.md) |

### `docs/stabilization/`

| Berkas lama | Topik | Status materi |
|---|---|---|
| `bug-list.md` | Daftar bug | Pointer |
| `data-integrity-report.md` | Integritas data | Pointer |
| `regression-test-report.md` | Laporan regresi | Pointer |
| `security-audit.md` | Audit keamanan | Pointer; temuan yang masih berlaku sudah diserap ke [12_KEAMANAN_PEMELIHARAAN_PENGEMBANGAN.md](../12_KEAMANAN_PEMELIHARAAN_PENGEMBANGAN.md) |
| `stabilization-report.md` | Laporan stabilisasi | Pointer |

### `docs/implementation/`

| Berkas lama | Topik | Status materi |
|---|---|---|
| `implementation-notes.md` | Catatan implementasi | Pointer |

## Catatan periode yang masih relevan

Beberapa keputusan dari periode tersebut masih berlaku dan sudah dipindahkan ke dokumentasi aktif:

| Tanggal | Keputusan | Kini didokumentasikan di |
|---|---|---|
| 2026-09-11 | Hardening login, activity log + retensi 7 hari | [12](../12_KEAMANAN_PEMELIHARAAN_PENGEMBANGAN.md) |
| 2026-09-11 | Docker dihapus; deployment bukan berbasis Docker | [08](../08_PANDUAN_DEPLOYMENT.md) |
| 2026-09-11 | Cloudinary dihapus dari kode; media lokal privat | [modules/media.md](../modules/media.md) |
| 2026-09-13 | Akumulasi kehadiran hanya di dokumen unduh | [modules/attendance.md](../modules/attendance.md) |
| 2026-09-24 | School Workspace dan komponen UI bersama | [10](../10_FRONTEND_BACKEND.md) |
| 2026-09-25 | Identitas pola jadwal = (hari + sekolah + tanggal mulai) | [modules/schedule.md](../modules/schedule.md) |
| 2026-09-27 | `is_active` pada sesi jadwal | [modules/schedule.md](../modules/schedule.md) |
| 2026-09-28 | Satu sesi = satu laporan; review dipisah dari arsip; PWA + Web Push | [modules/reports.md](../modules/reports.md), [modules/pwa.md](../modules/pwa.md) |

## Dokumen QA

Laporan QA **tidak** dipindahkan ke sini karena masih memuat temuan yang dapat ditindaklanjuti. Lihat [qa/](../qa/) — status tiap isu di sana sudah diperbarui pada 2026-09-28 untuk mencerminkan kondisi terkini.

## Menambah catatan historis

Bila kelak ada dokumen yang tidak lagi menggambarkan sistem namun layak disimpan (misalnya laporan insiden atau hasil audit yang sudah selesai ditindaklanjuti), tempatkan di folder ini dengan tanggal pada nama berkas dan cantumkan status arsipnya di bagian atas dokumen.
