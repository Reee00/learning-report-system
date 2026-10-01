# Pengembangan: Seeder

Sinkron dengan kode: **2026-09-28**. Sumber: `database/seeders/DatabaseSeeder.php`, `tests/Feature/DatabaseSeederTest.php`.

> **JANGAN menjalankan seeder ini di produksi.** Seeder ini membuat akun dengan kata sandi bersama yang lemah dan bersifat publik. Peruntukannya hanya pengembangan dan pengujian.

## Akun yang dibuat

Semua akun memakai kata sandi **`password`**.

| Email | Nama | Peran |
|---|---|---|
| `superadmin@lrs.com` | SuperAdmin Utama | `superadmin` |
| `admin@lrs.com` | Relation Utama | `relation` |
| `spv@lrs.com` | Sari Supervisor | `spv_coach` |
| `coach@lrs.com` | Rina Coachella | `coach` |
| `coach2@lrs.com` | Coach Pendamping | `coach` |
| `pic@lrs.com` | Budi Santoso | `school_pic` |
| `teacher@lrs.com` | Dewi Larasati | `teacher_school` |
| `finance@lrs.com` | Fajar Finance | `finance` |

Selain itu dibuat pula akun coach tambahan dari daftar `COACH_NAMES`, dengan email berpola slug nama → `nama@lrs.com` (contoh: `Mr Wildan (GS)` → `mr-wildan-gs@lrs.com`).

Kredensial ini **hanya** didokumentasikan di sini dan di docblock seeder. Email dipakai sebagai kunci natural, sehingga menjalankan ulang seeder bersifat aman (idempoten) — akun yang sudah ada diperbarui, bukan digandakan.

## Data master

`DIGISCHOOL` memetakan **10 sekolah** ke center, kelas, dan programnya:

PENABUR MODERNLAND, SIS, PENABUR GS, LEC, GRACIA, EST ALFA INDAH, IPEKA BSD, IPEKA PURI, SANUR, IPEKA IICS.

Dari peta itu seeder membangun:

- `seedSchools()` — daftar sekolah.
- `seedPrograms()` — daftar program.
- `seedClassesAndPrograms()` — kelas beserta kaitan programnya.
- `seedStudents()` — murid sintetis dari `STUDENT_POOL`, `STUDENTS_PER_CLASS = 5` per kelas.
- `seedCoachAssignments()` — penugasan coach utama dan coach pendamping (`COACH_ASSIGNMENTS`).

## Scope sekolah

`seedSchoolScopes()` memplot akses sekolah sesuai cara kerja nyata tiap peran:

| Akun | Plot |
|---|---|
| `pic@lrs.com` | Sekolah tertentu |
| `teacher@lrs.com` | Sekolah tertentu |
| `finance@lrs.com` | **Tidak di-plot** — akses all-school datang dari perannya, bukan dari plot sekolah |

Perbedaan ini penting: ia mencerminkan `AuthorizationService::accessibleSchoolIds()`, sehingga hasil seeder dapat dipakai menguji perilaku scope yang sebenarnya, bukan versi yang disederhanakan.

## Menjalankan

```bash
# Bangun ulang database dari nol lalu isi
php artisan migrate:fresh --seed

# Hanya mengisi data (aman diulang)
php artisan db:seed
```

Seeder berjalan di dalam satu `DB::transaction`, sehingga kegagalan di tengah jalan tidak meninggalkan data setengah jadi.

## Test

`DatabaseSeederTest` menjalankan seeder dan memverifikasi hasilnya. Seeder yang gagal akan menggagalkan test ini, jadi perubahan pada `DIGISCHOOL` atau daftar akun wajib disertai penyesuaian test.
