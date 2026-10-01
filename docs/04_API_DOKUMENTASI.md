# Route dan Endpoint

Disinkronkan dengan kode: **2026-10-01**. Sumber: `routes/web.php`, diverifikasi dengan `php artisan route:list --except-vendor`.

**Tidak ada `routes/api.php`.** Seluruh endpoint didefinisikan di `routes/web.php` dan memakai session auth — tidak ada token API, tidak ada OpenAPI, dan tidak ada JSON API umum. Middleware `web` (session, CSRF) berlaku pada semua rute dan tidak diulang pada tabel di bawah.

**Total: 97 rute** (di luar rute vendor).

## Konvensi

- Capability diperiksa middleware `permission:` / `permission_any:`; artinya lihat [reference/permissions.md](reference/permissions.md).
- `role:` membatasi peran secara eksplisit, terpisah dari capability. Grup `coach` memakai `role:coach`, grup `pic` memakai `role:school_pic`.
- Grup rute `admin` **tidak** memakai `role:` — otorisasinya murni lewat capability, sehingga Relation, SPV Coach, PIC, dan SuperAdmin dapat memakai prefix yang sama sesuai kewenangannya.

## Urutan Pendaftaran Rute yang Kritis

Rute statis **wajib** didaftarkan sebelum rute ber-parameter pada segmen yang sama, jika tidak model binding akan menangkap kata literal sebagai id dan mengembalikan 404:

| Rute statis | Harus mendahului |
|---|---|
| `admin/reports/review` | `admin/reports/{report}` |
| `admin/schedules/pattern` | `admin/schedules/{schedule}` |
| `admin/schedules/templates` | `admin/schedules/{schedule}` |
| `admin/schedules/import` | `admin/schedules/{schedule}` |
| `admin/schedules/create` | `admin/schedules/{schedule}` |
| `admin/schedules/semester` | `admin/schedules/{schedule}` |
| `admin/schedules/template` | `admin/schedules/{schedule}` |

Jangan mengubah urutan ini tanpa menjalankan ulang test terkait.

## Daftar Lengkap

| Method | URI | Name | Middleware |
|---|---|---|---|
| `GET` | `//` | — | — |
| `GET` | `/admin/activity-logs` | `admin.activity-logs.index` | auth + permission:users.manage |
| `GET` | `/admin/activity-logs/{log}` | `admin.activity-logs.show` | auth + permission:users.manage |
| `GET` | `/admin/classes` | `admin.classes.index` | auth + permission:program_classes.view |
| `POST` | `/admin/classes` | `admin.classes.store` | auth + permission:program_classes.create |
| `PUT` | `/admin/classes/{class}` | `admin.classes.update` | auth + permission:program_classes.update |
| `DELETE` | `/admin/classes/{class}` | `admin.classes.destroy` | auth + permission:program_classes.delete |
| `GET` | `/admin/coaches` | `admin.coaches.index` | auth + permission:coaches.view |
| `POST` | `/admin/coaches` | `admin.coaches.store` | auth + permission:coaches.create |
| `GET` | `/admin/coaches/{coach}` | `admin.coaches.show` | auth + permission:coaches.view |
| `PUT` | `/admin/coaches/{coach}` | `admin.coaches.update` | auth + permission:coaches.update |
| `POST` | `/admin/coaches/{coach}/assign` | `admin.coaches.assign` | auth + permission:coaches.assign |
| `DELETE` | `/admin/coaches/{coach}/assignments/{assignment}` | `admin.coaches.unassign` | auth + permission:coaches.reassign |
| `GET` | `/admin/dashboard` | `admin.dashboard` | auth + permission:dashboard.view |
| `POST` | `/admin/notifications` | `admin.notifications.store` | auth + permission:notifications.send |
| `GET` | `/admin/notifications/create` | `admin.notifications.create` | auth + permission:notifications.send |
| `GET` | `/admin/programs` | `admin.programs.index` | auth + permission:programs.view |
| `POST` | `/admin/programs` | `admin.programs.store` | auth + permission:programs.create |
| `GET` | `/admin/programs/{program}` | `admin.programs.show` | auth + permission:programs.view |
| `PUT` | `/admin/programs/{program}` | `admin.programs.update` | auth + permission:programs.update |
| `DELETE` | `/admin/programs/{program}` | `admin.programs.destroy` | auth + permission:programs.delete |
| `GET` | `/admin/reports` | `admin.reports.index` | auth + permission:reports.view_all |
| `POST` | `/admin/reports/remind` | `admin.reports.remind` | auth + permission:reports.remind |
| `GET` | `/admin/reports/review` | `admin.reports.review` | auth + permission:reports.review |
| `GET` | `/admin/reports/{report}` | `admin.reports.show` | auth + permission:reports.view_all |
| `PATCH` | `/admin/reports/{report}/approve` | `admin.reports.approve` | auth + permission:reports.review |
| `GET` | `/admin/reports/{report}/download` | `admin.reports.download` | auth + permission:reports.download |
| `PATCH` | `/admin/reports/{report}/reject` | `admin.reports.reject` | auth + permission:reports.review |
| `GET` | `/admin/schedules` | `admin.schedules.index` | auth + permission:schedules.view |
| `POST` | `/admin/schedules` | `admin.schedules.store` | auth + permission:schedules.manage |
| `POST` | `/admin/schedules/bulk` | `admin.schedules.bulk.store` | auth + permission:schedules.manage |
| `GET` | `/admin/schedules/class-students/{class}` | `admin.schedules.class-students` | auth + permission:schedules.manage |
| `GET` | `/admin/schedules/create` | `admin.schedules.create` | auth + permission:schedules.manage |
| `POST` | `/admin/schedules/import` | `admin.schedules.import` | auth + permission:schedules.manage |
| `GET` | `/admin/schedules/pattern` | `admin.schedules.pattern.show` | auth + permission:schedules.view |
| `DELETE` | `/admin/schedules/pattern` | `admin.schedules.pattern.destroy` | auth + permission:schedules.manage |
| `POST` | `/admin/schedules/pattern/generate` | `admin.schedules.pattern.generate` | auth + permission:schedules.manage |
| `GET` | `/admin/schedules/semester` | `admin.schedules.semester` | auth + permission:schedules.manage |
| `POST` | `/admin/schedules/semester` | `admin.schedules.semester.store` | auth + permission:schedules.manage |
| `GET` | `/admin/schedules/session/create` | `admin.schedules.sessions.create` | auth + permission:schedules.manage |
| `GET` | `/admin/schedules/template` | `admin.schedules.template` | auth + permission:schedules.manage |
| `GET` | `/admin/schedules/templates` | `admin.schedules.templates` | auth + permission:schedules.manage |
| `DELETE` | `/admin/schedules/templates/{template}` | `admin.schedules.templates.destroy` | auth + permission:schedules.manage |
| `POST` | `/admin/schedules/templates/{template}/generate` | `admin.schedules.templates.generate` | auth + permission:schedules.manage |
| `PUT` | `/admin/schedules/{schedule}` | `admin.schedules.update` | auth + permission:schedules.manage |
| `DELETE` | `/admin/schedules/{schedule}` | `admin.schedules.destroy` | auth + permission:schedules.manage |
| `PATCH` | `/admin/schedules/{schedule}/active` | `admin.schedules.toggle-active` | auth + permission:schedules.manage |
| `GET` | `/admin/schedules/{schedule}/edit` | `admin.schedules.edit` | auth + permission:schedules.manage |
| `PATCH` | `/admin/schedules/{schedule}/reschedule` | `admin.schedules.reschedule` | auth + permission:schedules.manage |
| `PATCH` | `/admin/schedules/{schedule}/status` | `admin.schedules.status` | auth + permission:schedules.manage |
| `GET` | `/admin/schools` | `admin.schools.index` | auth + permission:schools.view |
| `POST` | `/admin/schools` | `admin.schools.store` | auth + permission:schools.create |
| `GET` | `/admin/schools/{school}` | `admin.schools.show` | auth + permission:schools.view |
| `PUT` | `/admin/schools/{school}` | `admin.schools.update` | auth + permission:schools.update |
| `DELETE` | `/admin/schools/{school}` | `admin.schools.destroy` | auth + permission:schools.delete |
| `POST` | `/admin/schools/{school}/classes` | `admin.schools.classes.store` | auth + permission:program_classes.create |
| `PUT` | `/admin/schools/{school}/classes/{class}` | `admin.schools.classes.update` | auth + permission:program_classes.update |
| `DELETE` | `/admin/schools/{school}/classes/{class}` | `admin.schools.classes.destroy` | auth + permission:program_classes.delete |
| `GET` | `/admin/users` | `admin.users.index` | auth + permission:users.manage |
| `POST` | `/admin/users` | `admin.users.store` | auth + permission:users.manage |
| `PUT` | `/admin/users/{user}` | `admin.users.update` | auth + permission:users.manage |
| `DELETE` | `/admin/users/{user}` | `admin.users.destroy` | auth + permission:users.manage |
| `PATCH` | `/admin/users/{user}/reset-password` | `admin.users.reset-password` | auth + permission:users.manage |
| `GET` | `/api/classes/{class}/students` | — | auth + permission:students.view |
| `GET` | `/attendance` | `attendance.index` | auth + permission:attendance.view |
| `GET` | `/attendance/export` | `attendance.export` | auth + permission_any:attendance.export,attendance.export_csv |
| `GET` | `/attendance/schools/{school}` | `attendance.school` | auth + permission:attendance.view |
| `GET` | `/attendance/schools/{school}/classes/{class}` | `attendance.class` | auth + permission:attendance.view |
| `GET` | `/attendance/sessions/{report}` | `attendance.session` | auth + permission:attendance.view |
| `GET` | `/classes/{class}/students` | `students.show` | auth + permission:students.view |
| `POST` | `/classes/{class}/students` | `students.store` | auth + permission:students.create |
| `POST` | `/classes/{class}/students/import` | `students.import` | auth + permission:students.create |
| `DELETE` | `/classes/{class}/students/{student}` | `students.destroy` | auth + permission:students.delete |
| `GET` | `/coach/accident-notes` | `coach.accident-notes.index` | auth + role:coach + permission:accident_notes.view |
| `POST` | `/coach/notifications/{id}/read` | `coach.notifications.read` | auth + role:coach |
| `GET` | `/coach/reports` | `coach.reports.index` | auth + role:coach + permission:reports.view |
| `POST` | `/coach/reports` | `coach.reports.store` | auth + role:coach + permission:reports.create |
| `GET` | `/coach/reports/create` | `coach.reports.create` | auth + role:coach + permission:reports.create |
| `GET` | `/coach/reports/{report}` | `coach.reports.show` | auth + role:coach + permission:reports.view |
| `PUT` | `/coach/reports/{report}` | `coach.reports.update` | auth + role:coach + permission:reports.update |
| `GET` | `/coach/reports/{report}/download` | `coach.reports.download` | auth + role:coach + permission:reports.download |
| `GET` | `/coach/reports/{report}/edit` | `coach.reports.edit` | auth + role:coach + permission:reports.update |
| `GET` | `/coach/students` | `coach.students.index` | auth + role:coach + permission:students.view |
| `GET` | `/login` | `login` | — |
| `POST` | `/login` | — | — |
| `POST` | `/logout` | `logout` | — |
| `GET` | `/media/{media}` | `media.serve` | auth |
| `GET` | `/pic/dashboard` | `pic.dashboard` | auth + role:school_pic + permission:attendance.view |
| `POST` | `/pic/remind` | `pic.remind` | auth + role:school_pic + permission:attendance.view |
| `GET` | `/pic/reports/{report}` | `pic.reports.show` | auth + role:school_pic + permission:attendance.view |
| `GET` | `/pic/reports/{report}/download` | `pic.reports.download` | auth + role:school_pic + permission:attendance.view + permission:reports.download |
| `POST` | `/push-subscriptions` | `push-subscriptions.store` | auth |
| `DELETE` | `/push-subscriptions` | `push-subscriptions.destroy` | auth |
| `GET` | `/account` | `account.edit` | auth |
| `PATCH` | `/account` | `account.update` | auth |
| `PATCH` | `/account/password` | `account.password.update` | auth |
| `GET` | `/students/template` | `students.template` | auth + permission:students.view |

## Catatan per Kelompok

### Autentikasi

`GET|POST /login` dan `POST /logout`. Rute login sengaja **tanpa** middleware `auth`.

### Kehadiran

Lima rute, semuanya bermiddleware `permission:attendance.view` kecuali export. `/attendance/export` memakai `permission_any:attendance.export,attendance.export_csv` — Finance hanya lolos lewat `attendance.export_csv`. Rincian alur dan format ada di [modules/attendance.md](modules/attendance.md).

### Laporan

Rute coach memakai `role:coach` + capability `reports.*`. Rute admin memisahkan **antrean review** (`reports.review`) dari **arsip** (`reports.view_all`). Batas objek per laporan diperiksa di controller lewat `AuthorizationService::canAccessReport()` (coach: per sesi) — termasuk pada `admin.reports.download` bila pemanggilnya coach. Lihat [modules/reports.md](modules/reports.md).

### Accident Notes

`GET /coach/accident-notes` (`accident_notes.view`, `role:coach`) — daftar pengingat pribadi coach dari kolom `reports.notes` miliknya sendiri. Bukan notification center; tidak ada rute notifikasi untuk catatan ini.

### Jadwal

Seluruhnya di bawah prefix `admin/`. **Tidak ada rute jadwal untuk coach.** Lihat [modules/schedule.md](modules/schedule.md).

### Notifikasi

`admin/notifications/create` dan `admin/notifications` (capability `notifications.send`), serta `coach/notifications/{id}/read` (kepemilikan diperiksa di controller). **Tidak ada halaman indeks `/notifications`** — notifikasi dirender sebagai komponen di layout. Lihat [modules/notifications.md](modules/notifications.md).

### Media

`GET /media/{media}` — middleware `auth` saja; otorisasi per-laporan dilakukan di dalam `MediaController`, bukan lewat capability. Parameter `?download=1` menyajikan berkas yang sama sebagai `attachment` (dipakai tombol Download pada foto/video) tanpa rute tambahan. Lihat [modules/media.md](modules/media.md).

### Web Push

`POST` dan `DELETE /push-subscriptions` — middleware `auth` saja. Pemilik langganan selalu diambil dari `$request->user()`, tidak pernah dari input klien. Lihat [modules/web-push.md](modules/web-push.md).

### Account Settings

`GET` dan `PATCH /account` — middleware `auth` saja, tanpa capability, karena setiap user hanya menyunting akunnya sendiri (Nama + Nomor WhatsApp). Target selalu `$request->user()`; id user di URL maupun body diabaikan, dan `email` tidak ikut berubah.

`PATCH /account/password` — middleware `auth` saja, tanpa capability, dengan alasan yang sama: yang diganti selalu password pemilik sesi (`$request->user()`), `current_password` wajib cocok, dan password baru disimpan dengan hashing yang sama seperti pembuatan akun. Dipisah dari `account.update` supaya kegagalan konfirmasi password tidak pernah membatalkan penyimpanan profil. Lihat [modules/accounts.md](modules/accounts.md).

### AJAX

`GET /api/classes/{class}/students` dan `GET /classes/{class}/students` sama-sama memakai `permission:students.view`. Yang pertama dipakai form (JSON), yang kedua halaman. Keduanya memanggil `AuthorizationService::canAccessClass`.

### PIC

Empat rute di grup `pic` (`role:school_pic` + `permission:attendance.view`); unduhan laporan menambah `permission:reports.download`.

## Rute Vendor (di luar hitungan 97)

Tidak dihitung karena disediakan framework, tetapi tetap aktif:

| Method | URI | Sumber |
|---|---|---|
| `GET` | `/up` | Health check bawaan Laravel |
| `PUT` | `/storage/{path}` | `storage.local.upload` — unggahan sementara Laravel |

## Tidak Ada

Tidak ada endpoint API bertoken, tidak ada webhook, tidak ada halaman indeks notifikasi, tidak ada rute jadwal untuk coach, dan tidak ada berkas `routes/api.php`.

## Verifikasi Ulang

```bash
php artisan route:list --except-vendor
```

Bila jumlah rute tidak lagi 97, dokumen ini **wajib** diperbarui.
