# Dokumentasi Database

## Status
Terdapat 14 migration file. MySQL adalah target production, tetapi repository `config/database.php` memiliki default SQLite ketika `DB_CONNECTION` tidak diset. Driver dan database aktif bergantung pada `.env` dan berstatus `Need Verification` di audit ini.

## Entitas
`schools`, `users`, `classes`, `students`, `coach_classes`, `reports`, `report_attendances`, `report_media`, `programs`, `program_classes`, `school_user`, dan tabel sessions Laravel.

## Role Schema
Migration awal menyimpan enum legacy `admin`, `coach`, `school_pic`. Migration berikutnya mengonversi `admin` menjadi `relation` dan memperluas role. Runtime source of truth adalah `User::roleKeys()`: `superadmin`, `relation`, `spv_coach`, `coach`, `school_pic`, `teacher_school`, `finance`.

## Media
`report_media` menyimpan type, path, original name, nullable disk, dan nullable file size. Database menyimpan metadata/reference; binary disimpan pada disk filesystem.

## Relasi
School -> classes -> students; User -> coach_classes -> classes; User -> reports; Report -> report_attendances/media; Program <-> classes melalui `program_classes`; User <-> schools melalui `school_user`. `users.school_id` dipertahankan sebagai kompatibilitas legacy dan ikut dihitung pada school scope.

Tidak ada roles table, permissions table, soft-delete model, atau Coach model terpisah.
