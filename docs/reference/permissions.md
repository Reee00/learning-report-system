# Referensi: Peran & Izin (Permissions)

Sinkron dengan kode: **2026-09-29**. Sumber tunggal: `app/Services/AuthorizationService.php` (konstanta `ROLE_PERMISSIONS`), `bootstrap/app.php` (alias middleware), `routes/web.php`.

Dokumen ini adalah turunan dari kode. Bila terjadi perbedaan, **kode yang benar** — perbarui dokumen ini, bukan sebaliknya.

## Tujuh peran

| Peran (nilai `users.role`) | Sebutan |
|---|---|
| `superadmin` | SuperAdmin |
| `relation` | Relation |
| `spv_coach` | SPV Coach |
| `coach` | Coach |
| `school_pic` | PIC DK SCHOOL |
| `teacher_school` | Teacher School |
| `finance` | Finance |

## Cara kerja pengecekan

`AuthorizationService::allows(User, string $permission)`:

```php
if ($user->isSuperAdmin()) {
    return true;                       // wildcard
}
return in_array($permission, self::ROLE_PERMISSIONS[$user->role] ?? [], true);
```

- **SuperAdmin adalah wildcard.** Peran ini selalu lolos, termasuk untuk capability yang belum ada. Ini disengaja: capability baru otomatis global bagi SuperAdmin.
- Peran tak dikenal → daftar kosong → ditolak.
- **`users.manage` sengaja tidak diberikan ke peran mana pun**, sehingga hanya SuperAdmin yang dapat mengelola pengguna dan membaca activity log. Ini bukan kelalaian; jangan menambahkannya ke `ROLE_PERMISSIONS` tanpa keputusan produk.

## Middleware

Terdaftar di `bootstrap/app.php`:

| Alias | Kelas | Arti |
|---|---|---|
| `role` | `RoleMiddleware` | Peran pengguna harus salah satu yang disebut |
| `permission` | `PermissionMiddleware` | Harus memiliki capability tersebut |
| `permission_any` | `PermissionAnyMiddleware` | Cukup memiliki **salah satu** capability yang disebut |
| `auth` | `Authenticate` | Harus login |

## Matriks capability per peran

Baris = capability, kolom = peran. ✓ = dimiliki.

| Capability | superadmin | relation | spv_coach | coach | school_pic | teacher_school | finance |
|---|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| `dashboard.view` | ✓ | ✓ | ✓ | | | | |
| `schools.view` | ✓ | ✓ | | | | | |
| `schools.create` | ✓ | ✓ | | | | | |
| `schools.update` | ✓ | ✓ | | | | | |
| `schools.delete` | ✓ | ✓ | | | | | |
| `students.view` | ✓ | ✓ | | ✓ | ✓ | | |
| `students.create` | ✓ | ✓ | | ✓ | | | |
| `students.delete` | ✓ | ✓ | | | | | |
| `program_classes.view` | ✓ | ✓ | | | | | |
| `program_classes.create` | ✓ | ✓ | | | | | |
| `program_classes.update` | ✓ | ✓ | | | | | |
| `program_classes.delete` | ✓ | ✓ | | | | | |
| `programs.view` | ✓ | ✓ | | | | | |
| `programs.create` | ✓ | ✓ | | | | | |
| `programs.update` | ✓ | ✓ | | | | | |
| `programs.delete` | ✓ | ✓ | | | | | |
| `coaches.view` | ✓ | ✓ | ✓ | | ✓ | | |
| `coaches.contact` | ✓ | ✓ | ✓ | | ✓ | | |
| `coaches.create` | ✓ | | ✓ | | | | |
| `coaches.update` | ✓ | | ✓ | | | | |
| `coaches.assign` | ✓ | ✓ | ✓ | | | | |
| `coaches.reassign` | ✓ | ✓ | ✓ | | | | |
| `schedules.view` | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| `schedules.manage` | ✓ | ✓ | | | ✓ | | |
| `reports.view` | ✓ | | | ✓ | | | |
| `reports.create` | ✓ | | | ✓ | | | |
| `reports.update` | ✓ | | | ✓ | | | |
| `reports.view_all` | ✓ | ✓ | ✓ | | ✓ | ✓ | |
| `reports.review` | ✓ | ✓ | | | | | |
| `reports.download` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | |
| `reports.remind` | ✓ | ✓ | | | ✓ | | |
| `notifications.send` | ✓ | ✓ | | | ✓ | | |
| `attendance.view` | ✓ | ✓ | ✓ | | ✓ | ✓ | ✓ |
| `attendance.export` | ✓ | ✓ | ✓ | | ✓ | ✓ | ✓ |
| `attendance.export_csv` | ✓ | | | | | | |
| `accident_notes.view` | ✓ | | | ✓ | | | |
| `users.manage` | ✓ | | | | | | |

Catatan:

- Kolom `superadmin` seluruhnya ✓ karena wildcard, bukan karena terdaftar di `ROLE_PERMISSIONS`.
- `attendance.export` dan `attendance.export_csv` adalah **dua capability berbeda**, dengan `attendance.export` sebagai yang lebih luas (CSV + Excel + PDF). Sejak review meeting 2026-10-01 **Finance memegang `attendance.export`**, sehingga ketiga format tersedia baginya sesuai kebutuhan pelaporan; aturan QA M-001 yang lama (Finance hanya CSV) digantikan. `attendance.export_csv` — yang hanya mencakup CSV data mentah — tetap terdefinisi di middleware route dan di tabel ini, tetapi kini **tidak dipegang role mana pun**; ia disimpan untuk peran "data mentah saja" bila suatu saat dibutuhkan. Lihat [attendance.md](../modules/attendance.md).
- `reports.review` hanya dimiliki Relation dan SuperAdmin — inilah pemisahan antara **antrean review** dan **arsip** (lihat [reports.md](../modules/reports.md)).
- `coach` tidak memiliki capability `dashboard.view`, dan grup rute `coach` memang tidak punya rute `dashboard`; halaman utamanya adalah `/coach/reports`. Sebaliknya `school_pic` punya dasbornya sendiri di `pic/dashboard` (tanpa middleware capability).
- `accident_notes.view` hanya dimiliki coach dan kini dipakai rute `GET coach/accident-notes` (`coach.accident-notes.index`) — halaman pengingat pribadi coach, bukan notification center.
- `coach` memiliki `schedules.view`, tetapi **tidak ada rute jadwal untuk coach** — capability itu tidak dipakai rute mana pun saat ini. Daftar capability adalah himpunan kewenangan; tidak setiap butir harus punya rute.
- `coaches.view` dan `coaches.contact` kini juga dimiliki `school_pic`, tetapi **hanya untuk coach di sekolahnya**. Scope-nya ditegakkan di `Admin\CoachController` (`scopeCoachQueryToSchools()` + `ensureCoachInScope()`) sehingga URL detail coach sekolah lain berakhir 403, bukan halaman terbuka. `coach` sengaja tidak memiliki keduanya — nomor coach lain tidak pernah terlihat oleh coach.
- `coaches.contact` mengatur nomor WhatsApp coach. Controller **membuang nilainya di sisi server** untuk peran tanpa capability ini, jadi menyembunyikan kolom di Blade bukan satu-satunya pengaman.
- `users.manage` tetap SuperAdmin-only. **Account Settings tidak memakai capability apa pun**: setiap user hanya menyunting akunnya sendiri (`AccountController` selalu memakai `$request->user()`), sehingga id user di URL/body tidak berpengaruh.

## Scope data (di luar capability)

Capability menentukan *boleh atau tidak*. **Scope** menentukan *sejauh mana*. Keduanya diperiksa terpisah:

| Metode | Arti |
|---|---|
| `canAccessClass(User, SchoolClass)` | SuperAdmin & Relation global; Coach lewat `coachClasses`; PIC/Teacher lewat `assignedSchoolIds()`; peran lain ditolak |
| `canReportOnClass(User, SchoolClass)` | Dua sumber kewenangan coach: penugasan permanen `coach_classes`, dan penugasan sementara lewat sesi mengajar (coach utama maupun pendamping) tanpa mengubah `coach_classes` |
| `canViewClassRoster(User, SchoolClass)` | Coach pendamping pada sesi boleh membaca roster kelas yang dia ajar, tetapi tidak memperoleh wewenang pengelolaan siswa |
| `accessibleSchoolIds(User)` | `null` = global; selain itu daftar id sekolah yang boleh diakses |
| `canAccessSchool(User, int $schoolId)` | Pembungkus `accessibleSchoolIds()` |
| `canAccessReport(User, Report)` | Aturan objek tingkat-laporan. Coach: laporan miliknya (`reports.coach_id`) **atau** sesi tempat ia terlibat (per sesi, bukan per sekolah). Peran lain: `canAccessSchool()`. Dipakai `coach.reports.show`, `coach.reports.download`, dan cabang coach di `admin.reports.download` (2026-09-29). |

**Scope selalu diterapkan lebih dulu, filter request hanya mempersempit.** Ini berlaku pada daftar laporan, kehadiran, jadwal, dan media — sehingga parameter filter dari klien tidak pernah dapat memperluas akses.

## Media

Akses berkas `/media/{media}` **tidak** memakai capability, melainkan aturan peran langsung di `MediaController`: SuperAdmin/Relation/SPV Coach bebas; Coach hanya laporan sendiri (`reports.coach_id` — lebih ketat daripada `canAccessReport()`, sehingga coach pendamping tidak ikut membuka media laporan sesi bersama); PIC/Teacher/Finance harus `canAccessSchool()` **dan** laporan berstatus `approved`. Unduhan memakai route yang sama dengan `?download=1` (attachment). Lihat [media.md](../modules/media.md).

## Cara menambah capability

1. Tambahkan nama capability ke peran yang berhak di `ROLE_PERMISSIONS` (`app/Services/AuthorizationService.php`).
2. Pasang middleware `permission:` atau `permission_any:` pada rute di `routes/web.php`.
3. Perbarui matriks di dokumen ini agar tetap sinkron.
4. Tambahkan test di `AuthorizationServiceTest` / `RoleIsolationTest`.

SuperAdmin tidak perlu ditambahkan — wildcard sudah mencakupnya.

## Test terkait

`tests/Unit/AuthorizationServiceTest.php`, `RoleIsolationTest`, `CrossSchoolSecurityTest`, `RoleRedirectTest`, `CoachReportAuthorizationTest`.
