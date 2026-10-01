# Fitur dan Modul

Disinkronkan dengan kode: **2026-10-01**.

## Autentikasi

Login/logout berbasis session, dengan rate limiting `email|ip`, pesan galat generik, dan regenerasi session ID saat login.

Field password memakai label biasa **"Password"** dan tombol mata untuk menampilkan/menyembunyikan isi; tombol itu `type="button"` sehingga tidak pernah menyerobot pengiriman form, dan `autocomplete="current-password"` tetap ada agar password manager browser bekerja.

## Akun Sendiri

- **Pengaturan Akun** (`/account`) — terbuka untuk seluruh role, tanpa capability: setiap user hanya menyunting akunnya sendiri. Field: Nama, Nomor WhatsApp, dan Password.
- Ganti password memerlukan `current_password`, memakai kebijakan `min:6` + konfirmasi yang sudah berlaku, dan disimpan dengan hashing yang sama seperti pembuatan akun. Nilai password tidak pernah masuk activity log.
- **Nomor WhatsApp** coach tampil sebagai tautan klik `https://wa.me/…` bagi role berwenang; rincian di [modules/accounts.md](modules/accounts.md).

## Master Data

- **Sekolah** — CRUD, plus School Workspace di halaman detail sekolah.
- **Kelas** — CRUD; duplikat nama kelas dalam satu sekolah ditolak.
- **Program** — CRUD.
- **Program Kelas** — sumber data kelas yang dipakai ulang, dengan kolom Sekolah, Program, dan "Digunakan di" (jumlah murid/jadwal/laporan).
- **Murid** — CRUD terbatas scope, impor XLSX/XLS/CSV via FastExcel, dan unduh template.
- **Pengguna** — CRUD plus reset password. Hanya SuperAdmin (`users.manage`).
- **Coach** — CRUD akun coach dan penugasan/pemindahan coach ke kelas.

## School Workspace (2026-09-24)

Halaman detail sekolah menjadi satu tempat persiapan: informasi sekolah, kelas yang ter-assign, dan program per kelas. Dapat membuat kelas baru, memindahkan kelas master yang masih kosong dari sekolah lain, mengubah nama, memasang/melepas program, dan menghapus kelas yang belum terpakai. Tetap memakai `SchoolClass` + pivot `program_classes` — tidak ada tabel kelas kedua.

## Jadwal Mengajar

- **Pola berulang** (`teaching_schedule_templates`) dengan identitas **(hari + sekolah + tanggal mulai)**; tiap sekolah punya `start_date` dan `meeting_count` sendiri. Tidak ada periode global.
- Daftar utama per **pola hari** (default SENIN) dengan detail pertemuan tergenerate.
- **Jumlah pertemuan per blok sekolah**, banyak kelas/coach per blok, deteksi duplikat baris/blok, deteksi bentrok coach, dan copy pola hari.
- **Impor Excel dua format**: format normal, dan workbook per-perusahaan yang membaca tanggal mulai per blok sekolah dari kolom KET.
- **`is_active`** per sesi — nonaktif ≠ hapus.
- **Coach pendamping** lewat pivot, dapat membuat laporan untuk sesi tempat ia bertugas.
- Generate bersifat idempoten; menghapus pola tidak menghapus sesi yang sudah dibuat.

## Coach Report

Draft → submit → review → approve/reject → perbaikan → submit ulang. Mendukung materi, tujuan materi, aktivitas, catatan, absensi murid, dan media (foto, video, bukti absensi). Coach hanya dapat mengedit `draft`/`rejected`. **Satu sesi = satu laporan**, ditegakkan validasi aplikasi **dan** indeks unik database.

Coach punya **halaman detail laporan** (`coach.reports.show`) dan daftar "Riwayat Laporan" yang menampilkan kolom **"Materi yang Dipelajari"** dari `reports.lesson_material` (label `Week N` dari `meeting_number` sesi bila tertaut). Batas baca/unduhnya per **sesi**, bukan per sekolah: `AuthorizationService::canAccessReport()` — laporan miliknya, atau sesi tempat ia terlibat.

## Review dan Arsip

Dua halaman terpisah: **antrean review** (`reports.review`, hanya `submitted`+`rejected`, dengan hitungan pending/rejected) dan **arsip** (`reports.view_all`, riwayat baca-saja per sekolah → kelas).

## Kehadiran

Drill-down `/attendance` → sekolah → kelas → sesi laporan. **Akumulasi hanya di dokumen unduh**, tidak di halaman. Export: CSV (juga untuk Finance), Excel via OpenSpout, PDF via Dompdf.

## Notifikasi dan Pengingat

- Tiga jenis notifikasi manual: Perubahan Jadwal, Reminder Laporan, Warning/Info Operasional. Target di luar scope ditolak server-side.
- Pengingat laporan dinilai **per sesi**; dipicu manusia, tidak dijadwalkan otomatis.
- Notifikasi ditampilkan sebagai komponen di layout; penandaan dibaca memeriksa kepemilikan.
- **Accident Notes bukan bagian dari kanal ini.** Catatan kecelakaan (`reports.notes`) adalah pengingat pribadi coach dengan halaman sendiri (`GET coach/accident-notes`); tidak ada database notification, web push, atau item notification center yang dibuat darinya.

## Media

Disk privat `report_media` di luar `public/`, penyajian terotorisasi lewat `/media/{media}`, pembersihan berkas dan direktori kosong otomatis.

## PWA

Manifest, service worker, halaman offline, ikon (termasuk maskable), dan tiga pintasan aplikasi (Buat Laporan, Laporan Saya, Kehadiran). Strategi cache: network-only untuk navigasi, passthrough untuk path terproteksi, stale-while-revalidate untuk aset statis. **Tidak ada data terautentikasi yang masuk Cache Storage.**

## Web Push

Langganan per perangkat, kanal push yang tidak pernah menggagalkan aksi bisnis, job `SendWebPush` dengan 3 percobaan dan backoff `[10, 60]`. Nonaktif tanpa error bila VAPID kosong.

## Activity Log

Tabel `activity_logs` dengan satu jalur tulis (`ActivityLogService::log()`), penyaringan metadata otomatis (password/token/secret dibuang), konsol SuperAdmin dengan filter, dan retensi 7 hari.

## Komponen UI Bersama

- **Paginasi** — `Paginator::useBootstrapFive()` plus view bersama berlabel Indonesia, dipakai semua pemanggil `->links()`.
- **Angka tidak terpecah** — aturan `overflow-wrap`/`word-break` untuk sel tabel dan badge.
- **Form sekolah responsif** — dua kolom yang menumpuk di tablet/ponsel, modal dibuka kembali saat validasi gagal.
- **Form pola jadwal** — template `<template>` + klon DOM, sehingga `name` dibangun ulang dari posisi DOM sebelum submit.
- **Nomor baris tabel** melanjutkan antar halaman.

## Yang Belum Ada

Tidak ada API bertoken, tidak ada tabel `roles`/`permissions`, dan tidak ada UI terpisah khusus Finance/TEACHER SCHOOL/Relation.

> Pernyataan lama "tidak ada modul Accident Notes terpisah" **tidak berlaku lagi** (2026-09-29) — coach kini punya menu sidebar **Accident Notes** (`GET coach/accident-notes`) berisi catatan pribadinya sendiri. Datanya tetap kolom `reports.notes`; tidak ada tabel atau migrasi baru, dan catatan ini tidak pernah menjadi notification center item.

> Pernyataan lama "belum ada PWA" **tidak berlaku lagi** — PWA dan Web Push sudah terpasang.
