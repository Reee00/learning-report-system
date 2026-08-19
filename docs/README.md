# 📚 Dokumentasi Learning Report System

**Terakhir diperbarui:** Sesuai dengan status root project LRS terbaru.
**Stack terverifikasi:** Laravel 12.0 · PHP 8.4 · SQLite / MySQL · Blade + Bootstrap 5.3 (CDN)

---

## Cara membaca dokumentasi ini

Dokumen di folder ini dibagi menjadi beberapa kelompok dengan sifat yang berbeda. Membedakannya penting:
dua di antaranya adalah **snapshot bertanggal** yang sengaja tidak diperbarui, karena nilainya justru
sebagai catatan sejarah.

| Kelompok | Sifat | Perlakuan |
|---|---|---|
| `00`–`12` | Dokumentasi sistem — deskripsi kondisi **saat ini** | Selalu disinkronkan dengan kode |
| `audit/` | Peta arsitektur & audit Phase 1 | `route-map`, `role-permission-map`, `data-model-map` disinkronkan; `existing-system-audit` adalah snapshot historis |
| `implementation/` | Catatan implementasi per phase | Append-only; entri lama tidak diubah |
| `stabilization/` | Hasil audit stabilisasi lama | Snapshot bertanggal — **jangan diedit** |

---

## 1. Dokumentasi Sistem Inti

| # | Dokumen | Isi |
|---|---|---|
| 00 | [Ringkasan Eksekutif](00_RINGKASAN_EKSEKUTIF.md) | Apa itu sistem ini, 7 role (wildcard/scoped), modul utama |
| 01 | [Arsitektur Sistem](01_ARSITEKTUR_SISTEM.md) | Layer, request lifecycle, service, ERD, middleware |
| 02 | [Dokumentasi Database](02_DATABASE_DOKUMENTASI.md) | Tabel, kolom, FK, index, aturan delete |
| 03 | [Autentikasi & Otorisasi](03_AUTENTIKASI_OTORISASI.md) | Login, capability model RBAC, school scoping, matriks akses |
| 04 | [Dokumentasi API / Endpoint](04_API_DOKUMENTASI.md) | Route web, capability, request/response AJAX per endpoint |
| 05 | [Proses Bisnis](05_PROSES_BISNIS.md) | Alur kerja tiap role, workflow laporan, skenario data |
| 06 | [Struktur Folder](06_STRUKTUR_FOLDER.md) | Peta file aktual: controller, model, service, view |
| 07 | [Panduan Instalasi](07_PANDUAN_INSTALASI.md) | Setup lokal dari nol, akun demo, troubleshooting |
| 08 | [Panduan Deployment](08_PANDUAN_DEPLOYMENT.md) | Docker, Railway, VPS Ubuntu (PHP 8.4), checklist produksi |
| 09 | [Fitur & Modul](09_FITUR_DAN_MODUL.md) | Daftar fitur aktual per modul dan batasannya |
| 10 | [Frontend & Backend](10_FRONTEND_BACKEND.md) | Teknologi nyata (Controller, Service, AJAX, CSV Stream) |
| 11 | [Pengujian & Troubleshooting](11_PENGUJIAN_DAN_TROUBLESHOOTING.md) | Skenario UI manual, masalah data anomali (BUG-012) |
| 12 | [Keamanan, Pemeliharaan & Pengembangan](12_KEAMANAN_PEMELIHARAAN_PENGEMBANGAN.md) | Kontrol keamanan, isolasi data, roadmap WaHa & Cloudinary |

## 2. Audit Arsitektur (Historis / Maps)

| Dokumen | Isi |
|---|---|
| [audit/route-map.md](audit/route-map.md) | Inventaris route + capability |
| [audit/role-permission-map.md](audit/role-permission-map.md) | Matriks role × permission |
| [audit/data-model-map.md](audit/data-model-map.md) | Peta entity & relasi |
| [audit/existing-system-audit.md](audit/existing-system-audit.md) | Audit sistem sebelum Phase 2 (**Historis**) |

## 3. Catatan Implementasi

| Dokumen | Isi |
|---|---|
| [implementation/implementation-notes.md](implementation/implementation-notes.md) | Ringkasan tiap Phase: apa yang dibuat, file yang berubah |

---

## Aturan Menjaga Dokumentasi Ini Tetap Benar

1. Dokumentasikan apa yang ada (seperti kode), bukan apa yang diidealkan.
2. Jangan pernah menuliskan kredensial (*password* produksi). Akun *seed* publik tidak masalah.
3. Snapshot bertanggal (`stabilization/`, `audit/existing-system-audit.md`) merupakan museum dan tidak diperbarui—ditambahkan dokumen baru jika ingin audit total.
4. Ketika menambah model/tabel, segera *update* dokumen `02_DATABASE_DOKUMENTASI.md`.
5. Ketika menambah role baru (di `AuthorizationService`), mutlak memperbarui:
   - `00_RINGKASAN_EKSEKUTIF.md`
   - `01_ARSITEKTUR_SISTEM.md` (bagian redirect)
   - `03_AUTENTIKASI_OTORISASI.md`
   - `05_PROSES_BISNIS.md` (Permissions Matrix)
