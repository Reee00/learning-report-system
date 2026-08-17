# 📚 Dokumentasi Learning Report System

**Terakhir diperbarui:** 17 Agustus 2026
**Kondisi project:** Phase 0–12 selesai & stabil. Phase 13 (WaHa / WhatsApp) **belum diimplementasikan**.
**Stack terverifikasi:** Laravel 12.66.0 · PHP 8.4.24 · Composer 2.10.1 · SQLite · Blade + Bootstrap 5.3 (CDN)

---

## Cara membaca dokumentasi ini

Dokumen di folder ini dibagi menjadi empat kelompok dengan sifat yang berbeda. Membedakannya penting:
dua di antaranya adalah **snapshot bertanggal** yang sengaja tidak diperbarui, karena nilainya justru
sebagai catatan sejarah.

| Kelompok | Sifat | Perlakuan |
|---|---|---|
| `00`–`12` | Dokumentasi sistem — deskripsi kondisi **saat ini** | Selalu disinkronkan dengan kode |
| `audit/` | Peta arsitektur & audit Phase 1 | `route-map`, `role-permission-map`, `data-model-map` disinkronkan; `existing-system-audit` adalah snapshot 14 Agustus 2026 |
| `implementation/` | Catatan implementasi per phase | Append-only; entri lama tidak diubah |
| `stabilization/` | Hasil audit stabilisasi 17 Agustus 2026 | Snapshot bertanggal — **jangan diedit**, buat dokumen baru untuk audit berikutnya |

---

## 1. Dokumentasi sistem

| # | Dokumen | Isi |
|---|---|---|
| 00 | [Ringkasan Eksekutif](00_RINGKASAN_EKSEKUTIF.md) | Apa itu sistem ini, 6 role, modul, status Phase |
| 01 | [Arsitektur Sistem](01_ARSITEKTUR_SISTEM.md) | Layer, request lifecycle, service, ERD, middleware |
| 02 | [Dokumentasi Database](02_DATABASE_DOKUMENTASI.md) | 13 tabel, kolom, FK, index, aturan delete |
| 03 | [Autentikasi & Otorisasi](03_AUTENTIKASI_OTORISASI.md) | Login, capability model, school scope, matriks akses |
| 04 | [Dokumentasi API / Endpoint](04_API_DOKUMENTASI.md) | 44 route, capability, request/response per endpoint |
| 05 | [Proses Bisnis](05_PROSES_BISNIS.md) | Alur kerja tiap role, workflow laporan, skenario data |
| 06 | [Struktur Folder](06_STRUKTUR_FOLDER.md) | Peta file aktual: controller, model, service, view, test |
| 07 | [Panduan Instalasi](07_PANDUAN_INSTALASI.md) | Setup lokal dari nol, akun demo, troubleshooting |
| 08 | [Panduan Deployment](08_PANDUAN_DEPLOYMENT.md) | Docker, Railway, VPS, checklist produksi |
| 09 | [Fitur & Modul](09_FITUR_DAN_MODUL.md) | Daftar fitur per modul dan pemiliknya |
| 10 | [Frontend & Backend](10_FRONTEND_BACKEND.md) | Teknologi nyata di kedua sisi (tanpa Vite) |
| 11 | [Pengujian & Troubleshooting](11_PENGUJIAN_DAN_TROUBLESHOOTING.md) | 62 test, cara menjalankan, error yang pernah terjadi |
| 12 | [Keamanan, Pemeliharaan & Pengembangan](12_KEAMANAN_PEMELIHARAAN_PENGEMBANGAN.md) | Kontrol keamanan, maintenance, roadmap |

## 2. Audit arsitektur (Phase 1)

| Dokumen | Isi | Status |
|---|---|---|
| [audit/route-map.md](audit/route-map.md) | Inventaris route + capability | Disinkronkan 17 Agu 2026 |
| [audit/role-permission-map.md](audit/role-permission-map.md) | Matriks role × permission | Disinkronkan 17 Agu 2026 |
| [audit/data-model-map.md](audit/data-model-map.md) | Peta entity & relasi | Disinkronkan 17 Agu 2026 |
| [audit/existing-system-audit.md](audit/existing-system-audit.md) | Audit sistem sebelum Phase 2 | **Snapshot 14 Agu 2026 — historis** |

## 3. Catatan implementasi

| Dokumen | Isi |
|---|---|
| [implementation/implementation-notes.md](implementation/implementation-notes.md) | Ringkasan tiap Phase 2–12: apa yang dibuat, file yang berubah, follow-up |

## 4. Audit stabilisasi (17 Agustus 2026)

Lima dokumen ini adalah hasil audit Phase 0–12 dan **snapshot bertanggal**. Isinya tidak boleh
diperbarui belakangan — kalau ada audit baru, buat dokumen baru.

| Dokumen | Isi |
|---|---|
| [stabilization/stabilization-report.md](stabilization/stabilization-report.md) | Verdict utama: `READY FOR WAHA`, status per phase, gate §48/§49 |
| [stabilization/bug-list.md](stabilization/bug-list.md) | BUG-001…BUG-013: reproduksi, root cause, fix, test |
| [stabilization/security-audit.md](stabilization/security-audit.md) | 7 kategori keamanan, F-1…F-9 |
| [stabilization/data-integrity-report.md](stabilization/data-integrity-report.md) | 16 integrity check, DATA-001, CONFIG-001, MEDIA-001 |
| [stabilization/regression-test-report.md](stabilization/regression-test-report.md) | 62 test / 301 assertion, mapping fix → test |

---

## Kondisi project per 17 Agustus 2026

```text
Phase 0–12          STABLE
Phase 13 (WaHa)     NOT IMPLEMENTED
Sistem              READY FOR WAHA

php artisan test          62 passed (301 assertions), 0 failure
php artisan migrate:status 13 migrations, all Ran, 0 Pending
php artisan route:list     Showing [44] routes
npm run build              'No build step required'   (tidak ada Vite pipeline)
integrity check 01–16      0 dari 16 gagal
```

### Yang masih terbuka

| ID | Sev | Ringkas |
|---|---|---|
| ISSUE-014 | P2 | `CLOUDINARY_CLOUD_NAME` berisi identifier MediaFlows, bukan Programmable Media cloud name → upload media laporan ditolak `401 cloud_name mismatch`. Laporan tanpa media tetap normal. |
| ISSUE-015 / MEDIA-001 | P3 | `report_media` tidak punya kolom `cloudinary_public_id`, jadi hapus media tidak menghapus aset di Cloudinary. |
| Q-A…Q-D | — | Empat pertanyaan requirement yang **sengaja belum diimplementasikan**. Lihat [03](03_AUTENTIKASI_OTORISASI.md#8-pertanyaan-requirement-yang-masih-terbuka). |
| P3-4 | P3 | Debris root: `_dbcheck.php`, `_logincheck.php`, `_audit_db.php`, dua file backup `database/database.sqlite.bak-*`. |

### Sebelum memulai Phase 13 (WaHa)

1. `QUEUE_CONNECTION` masih `sync`. Panggilan keluar WhatsApp tidak boleh berjalan di dalam request
   cycle — pindah ke `database`/Redis dan jalankan worker.
2. `APP_DEBUG` harus `false` di environment mana pun yang menyimpan token WaHa.
3. Kalau Phase 13 akan mengirim foto laporan, **ISSUE-014 menjadi prasyarat keras** — tanpa itu tidak ada
   media tersimpan yang bisa dikirim.

---

## Aturan menjaga dokumentasi ini tetap benar

1. Angka dan nama harus berasal dari perintah, bukan ingatan: `route:list`, `migrate:status`,
   `php artisan test`, `PRAGMA table_info`.
2. Jangan menuliskan keputusan bisnis yang belum diputuskan. Tandai `TBD` dan sebutkan sumber
   ambiguitasnya.
3. Jangan pernah menuliskan credential nyata. Akun demo dari seeder boleh, karena memang publik.
4. Snapshot bertanggal (`stabilization/`, `audit/existing-system-audit.md`) tidak diperbarui —
   ditambahkan dokumen baru.
