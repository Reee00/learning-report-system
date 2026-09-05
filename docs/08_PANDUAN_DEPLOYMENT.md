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

Dockerfile saat ini menggunakan PHP 8.3, sedangkan Composer meminta PHP `^8.4`; kompatibilitas image belum terverifikasi dan perlu diselesaikan pada deployment. Dokumentasi ini tidak menyatakan Cloudinary aktif. Command migrasi Cloudinary hanya untuk data legacy setelah backup dan verifikasi.
