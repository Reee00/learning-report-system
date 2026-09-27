# Panduan Deployment

## Target
Deployment production ditujukan menggunakan PHP 8.4 dan MySQL. Nilai host, credential, APP_ENV, queue/cache/session, dan filesystem harus diverifikasi dari environment deployment; repository tidak menyertakan `.env`.

## Checklist
- Install dependencies dan jalankan migration.
- Set database MySQL serta secret aplikasi.
- Pastikan `storage/app/report-media` writable dan tetap private.
- Pastikan route `/media/{media}` dipakai untuk akses media terotorisasi.
- Jalankan test sebelum release.
- Pantau storage dan ukuran media.

- Set upload limits di PHP server production: `upload_max_filesize=101M`, `post_max_size=310M`, `memory_limit=256M` (Add Video, 3×100MB).

Deployment BUKAN berbasis Docker — `Dockerfile` sudah dihapus (2026-09-11); item QA L-005 (Docker PHP 8.3 vs 8.4) tidak relevan lagi. Dokumentasi ini tidak menyatakan Cloudinary aktif. Command migrasi Cloudinary hanya untuk data legacy setelah backup dan verifikasi (saat ini 0 baris media eksternal di database).
