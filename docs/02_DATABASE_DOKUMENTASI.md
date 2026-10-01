# Dokumentasi Database

Disinkronkan dengan kode: **2026-09-28**. Sumber: `database/migrations/` (28 berkas), `app/Models/` (14 model).

## Status

Schema dibangun oleh **28 migrasi**. Target produksi adalah MySQL. `config/database.php` memiliki default SQLite ketika `DB_CONNECTION` tidak diset, sehingga driver yang benar-benar aktif bergantung pada `.env` dan berstatus `NEED VERIFICATION` bila `.env` tidak tersedia.

> **Temuan yang perlu diverifikasi (2026-09-28):** `.env.example` menyetel `CACHE_STORE=database`, tetapi **tidak ada migrasi yang membuat tabel `cache` / `cache_locks`**. Bila konfigurasi itu dipakai apa adanya, operasi cache akan gagal karena tabelnya tidak ada. Perlu diputuskan: tambahkan migrasi tabel cache, atau ubah `CACHE_STORE` ke `file`. Dokumen ini tidak mengubah kode.

## Tabel

### Inti

| Tabel | Isi |
|---|---|
| `schools` | Sekolah |
| `users` | Pengguna; `role` menyimpan salah satu dari 7 peran, `whatsapp` = nomor WhatsApp pribadi (nullable, bentuk kanonik `08xxxxxxxxxx` — lihat [accounts.md](modules/accounts.md)) |
| `classes` | Kelas (`SchoolClass`) |
| `students` | Murid |
| `coach_classes` | Penugasan **permanen** coach → kelas |
| `programs`, `program_classes` | Program dan kaitannya ke kelas (pivot) |
| `school_user` | Plot sekolah untuk PIC / Teacher School |
| `sessions` | Sesi Laravel |

### Laporan & kehadiran

| Tabel | Isi |
|---|---|
| `reports` | Laporan coach; `teaching_schedule_id` **nullable + UNIQUE** |
| `report_attendances` | Kehadiran per (laporan, murid); unik pada pasangan itu |
| `report_media` | Metadata media: `type`, `path`, `original_name`, `disk`, `file_size` |

### Jadwal mengajar

| Tabel | Isi |
|---|---|
| `teaching_schedule_templates` | **Pola** berulang; identitas = (hari, sekolah, tanggal mulai) |
| `teaching_schedule_template_coach` | Coach pada pola (termasuk pendamping) |
| `teaching_schedules` | **Sesi** konkret per tanggal; punya `template_id`, `meeting_number`, `is_active` |
| `teaching_schedule_coach` | Coach pada sesi (termasuk pendamping) |

### Operasional

| Tabel | Isi |
|---|---|
| `activity_logs` | Jejak audit; retensi 7 hari |
| `notifications` | Notifikasi Laravel (UUID) untuk kanal `database` |
| `push_subscriptions` | Langganan Web Push per perangkat |
| `jobs`, `job_batches`, `failed_jobs` | Antrean database |

## Peran pada Schema

Migrasi awal menyimpan enum legacy `admin`, `coach`, `school_pic`. Migrasi `2026_08_14_000000_migrate_admin_role_to_relation_and_expand_roles` mengubah `admin` menjadi `relation` dan memperluas daftar peran.

Sumber kebenaran runtime adalah `User::roleKeys()`: `superadmin`, `relation`, `spv_coach`, `coach`, `school_pic`, `teacher_school`, `finance`. **Tidak ada peran `admin` saat runtime.**

## Media

`report_media` menyimpan `type`, `path`, `original_name`, `disk` (nullable), dan `file_size` (nullable). Binary **tidak** disimpan di database — berkas berada pada disk privat `report_media` di luar `public/`. Lihat [modules/media.md](modules/media.md).

## Relasi

```
School ──< SchoolClass ──< Student
School ──< SchoolUser >── User
User ──< CoachClass >── SchoolClass
User ──< Report >── School
Report ──< ReportAttendance >── Student
Report ──< ReportMedia
Report >── TeachingSchedule          (satu lawan satu, UNIQUE)
TeachingScheduleTemplate ──< TeachingSchedule
TeachingScheduleTemplate >──< User   (teaching_schedule_template_coach)
TeachingSchedule >──< User           (teaching_schedule_coach)
Program >──< SchoolClass             (program_classes)
```

`users.school_id` dipertahankan sebagai kompatibilitas legacy dan **ikut dihitung** dalam penentuan scope sekolah, bersama pivot `school_user`.

## Yang Tidak Ada

Tidak ada tabel `roles`, tidak ada tabel `permissions`, tidak ada soft-delete, dan tidak ada model `Coach` terpisah — coach adalah `User` dengan peran `coach`. Kewenangan didefinisikan di kode (`AuthorizationService`), bukan di database; lihat [reference/permissions.md](reference/permissions.md).

## Migrasi Penting

| Migrasi | Dampak |
|---|---|
| `2026_09_11_000006_add_unique_report_student_to_report_attendances` | Mencegah murid tercatat dua kali dalam satu laporan |
| `2026_09_11_000007_extend_teaching_schedules_for_management` | Menambah kolom pengelolaan jadwal |
| `2026_09_12_000001_create_teaching_schedule_templates` | Memisahkan pola dari sesi |
| `2026_09_25_000001_school_day_schedule_patterns` | Identitas pola = (hari, sekolah, tanggal mulai) |
| `2026_09_27_000001_add_is_active_to_teaching_schedules` | `is_active` + indeks `(is_active, session_date)` |
| `2026_09_28_000001_add_teaching_schedule_to_reports` | FK nullable + backfill terbatas + **UNIQUE** — satu sesi = satu laporan |
| `2026_09_28_000002_create_push_subscriptions_table` | Langganan Web Push |
| `2026_09_28_000003_create_jobs_table` | Antrean database (`jobs`, `job_batches`, `failed_jobs`) |
