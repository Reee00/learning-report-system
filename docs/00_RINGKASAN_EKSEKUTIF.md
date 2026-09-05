# Ringkasan Eksekutif

LRS adalah aplikasi Laravel untuk sekolah, siswa, kelas, program, attendance, Coach Report, review, dan media pembelajaran.

## Role Aktif
SuperAdmin, Relation, SPV Coach, Coach, PIC DK SCHOOL, TEACHER SCHOOL, dan Finance. `admin` bukan role runtime; `/admin/*` hanya namespace kompatibilitas.

## Alur Utama
Relation/SuperAdmin menyiapkan master data dan assignment. Coach mengisi report dan attendance lalu submit. Relation atau SuperAdmin melakukan review: approve atau reject. Report rejected dapat diperbaiki Coach dan disubmit kembali. Role sekolah hanya melihat data sekolah yang diplot; report sekolah yang terlihat harus approved.

## Storage dan Database
MySQL adalah target production, tetapi driver aktif tidak dapat diverifikasi tanpa `.env`. Konfigurasi repository fallback ke SQLite. Binary media tidak disimpan di database: file baru berada pada Laravel local private disk, sedangkan database menyimpan reference/metadata `ReportMedia`. Cloudinary hanya legacy compatibility dan migration support.

## Status Dokumentasi
Detail implementasi berada di [contextproject.md](../contextproject.md) dan dokumen topik bernomor. Informasi environment deployment yang tidak tersedia di source diberi `Need Verification`.
