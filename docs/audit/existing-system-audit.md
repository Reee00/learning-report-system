# Existing System Audit

Tanggal audit: 14 Agustus 2026  
Scope: Phase 0 â€” Existing Project Audit  
Sumber: inspeksi source code, migration, konfigurasi, dokumentasi repo, dan static search.

## Executive Summary

Codebase saat ini adalah aplikasi Laravel 12 berbasis Blade dengan alur learning report yang sudah berjalan untuk tiga role:

- `admin`
- `coach`
- `school_pic`

Role disimpan langsung sebagai string pada kolom `users.role`. Belum ada permission package, Policy, role model, Service layer, Program entity, export attendance, Finance, Relation, atau integrasi WaHa.

Fitur existing yang dapat dipakai sebagai extension point:

- autentikasi session berbasis Laravel;
- master School, Class, Student, dan User;
- assignment Coach ke Class melalui `coach_classes`;
- Coach membuat report beserta attendance dan media;
- Admin mereview report;
- PIC melihat report approved berdasarkan satu `school_id`.

Phase berikutnya harus memperluas struktur tersebut secara incremental. Migrasi role `admin` ke `Relation` tidak dapat dilakukan sebagai rename label sederhana karena `admin` saat ini adalah role operasional yang menjaga seluruh route admin, review report, user management, dan master data.

## Current Architecture

### Stack dan runtime

| Area | Kondisi aktual |
|---|---|
| Backend | PHP 8.2 constraint, Laravel `^12.0` |
| Frontend | Blade, Bootstrap 5 melalui CDN, JavaScript minimal |
| Database | Default SQLite; konfigurasi juga menyediakan MySQL, MariaDB, PostgreSQL, SQL Server |
| Media | Cloudinary melalui `App\Helpers\CloudinaryHelper` |
| Import | Fast-Excel pada import Student |
| Asset tooling | `vite.config.js` tersedia, tetapi `package.json` hanya memiliki script build dan tidak mendeklarasikan dependency frontend |
| Auth | Laravel session guard `web`, Eloquent provider `App\Models\User` |
| Authorization | Middleware custom `RoleMiddleware`; beberapa controller memakai pengecekan role/scope manual |
| Architecture | MVC Laravel; tidak ada `app/Policies` atau `app/Services` |
| API | Tidak ada `routes/api.php`; endpoint AJAX ditempatkan di `routes/web.php` |

Struktur domain utama:

```text
User --< CoachClass >-- SchoolClass --< Student
  |                         |
  |                         +-- School
  +-- School (PIC one-school)
  |
  +--< Report --< ReportAttendance >-- Student
           |
           +--< ReportMedia
```

## Current Authentication

Authentication berada di `app/Http/Controllers/Auth/LoginController.php`:

1. `GET /login` menampilkan form.
2. `POST /login` memvalidasi email/password dan menjalankan `Auth::attempt()`.
3. Session ID diregenerasi setelah login.
4. Redirect dipilih berdasarkan string `User::role`.
5. Logout menghapus session, invalidate, dan regenerate CSRF token.

Guard `web` menggunakan session dan provider Eloquent. Tidak ada registrasi, password reset UI, email verification, atau multi-guard yang terlihat pada implementasi saat ini.

Role middleware didaftarkan sebagai alias `role` di `bootstrap/app.php`. Middleware mengizinkan request hanya jika `Auth::user()->role` cocok dengan parameter role; user yang belum login diarahkan ke login dan role yang tidak cocok mendapat 403.

## Current Roles

| Role aktual | Implementasi dan akses |
|---|---|
| `admin` | Dashboard, user management, report review, school/class/coach management. Ini merupakan role admin penuh saat ini. |
| `coach` | Membuat, melihat, dan mengedit report miliknya; mengisi attendance; upload foto/video. |
| `school_pic` | Melihat report approved untuk satu sekolah yang disimpan pada `users.school_id`. |

Role target PRD (`superadmin`, `relation`, `spv_coach`, `coach`, `pic_sekolah`, `finance`) belum tersedia. Tidak ada `Relation`, `SPV Coach`, `Finance`, atau `SuperAdmin` sebagai nilai role tersendiri.

## Current Permissions

Tidak ada permission package atau tabel permission/role-permission. Authorization saat ini terdiri dari:

- route middleware `role:admin`, `role:coach`, dan `role:school_pic`;
- pengecekan role langsung di `StudentController`;
- pengecekan ownership report pada `Coach\ReportController`;
- pengecekan school scope pada `SchoolPic\DashboardController`;
- pengecekan role/ownership assignment pada `Admin\CoachController`.

Sebagian besar controller admin bergantung sepenuhnya pada route middleware. Belum ada Policy atau permission service yang dapat digunakan ulang untuk query scope dan export.

## Current Models

| Entity | Model/table | Status |
|---|---|---|
| User | `User` / `users` | Ada; role string/enum, nullable `school_id` |
| School | `School` / `schools` | Ada |
| Program Kelas | `SchoolClass` / `classes` | Ada secara konseptual sebagai kelas sekolah; belum ada Program Kelas entity terpisah |
| Student | `Student` / `students` | Ada; terhubung ke class |
| Coach | Tidak ada model Coach khusus | Coach direpresentasikan sebagai `User` dengan `role = coach` |
| Coach Assignment | `CoachClass` / `coach_classes` | Ada; assignment Coach ke Class |
| Report | `Report` / `reports` | Ada; report pembelajaran |
| Attendance | `ReportAttendance` / `report_attendances` | Ada; attendance melekat pada report |
| Media | `ReportMedia` / `report_media` | Ada; photo/video report |
| Accident Notes | Tidak ada | Field `reports.notes` adalah catatan report umum, bukan Accident Notes terstruktur |
| Program | Tidak ada | Belum ada model, migration, controller, route, atau view |
| Notification | Tidak ada model/service/channel | Belum ada implementasi |
| School plotting | Tidak ada entity pivot | PIC hanya memiliki satu `users.school_id` |

## Current Relationships

- `School hasMany SchoolClass`, `School hasMany User`.
- `User belongsTo School` untuk kebutuhan `school_pic`.
- `User hasMany CoachClass` sebagai Coach.
- `SchoolClass belongsTo School`, `hasMany Student`, dan `hasMany CoachClass`.
- `Student belongsTo SchoolClass` melalui `class_id`.
- `CoachClass belongsTo User` melalui `coach_id` dan `SchoolClass` melalui `class_id`.
- `Report belongsTo User` sebagai Coach, `School`, `SchoolClass`, dan approver User.
- `Report hasMany ReportAttendance` dan `ReportMedia`.
- `ReportAttendance belongsTo Report` dan `Student`.
- `ReportMedia belongsTo Report`.

Konsekuensi penting: PIC saat ini hanya dapat diplot ke satu sekolah. Model many-to-many untuk multi-school plotting belum ada.

## Current Routes

### Public/authentication

| Method | Path | Akses | Handler |
|---|---|---|---|
| GET | `/` | publik | redirect ke login |
| GET | `/login` | publik | login form |
| POST | `/login` | publik | login |
| POST | `/logout` | login | logout |

### Student/class routes

`/classes/{class}/students`, import, delete, dan `/students/template` hanya memakai `auth` di route. Scope tambahan diperiksa manual oleh `StudentController` untuk `admin`, `coach` yang assigned ke class, dan `school_pic` pada school yang sama.

Endpoint `/api/classes/{class}/students` juga berada di `web.php` dan hanya memakai `auth`; endpoint ini belum menerapkan role atau school scope.

### Coach routes

Prefix `/coach`, name prefix `coach.`, middleware `auth` + `role:coach`:

- resource report: index, create, store, edit, update.

### Admin routes

Prefix `/admin`, name prefix `admin.`, middleware `auth` + `role:admin`:

- dashboard;
- user index/store/update/reset-password/destroy;
- report index/show/approve/reject;
- resource schools;
- resource classes;
- coach index/show/assign/unassign.

### PIC routes

Prefix `/pic`, name prefix `pic.`, middleware `auth` + `role:school_pic`:

- dashboard;
- report detail.

### Route observations

- `Route::resource('schools')` dan `Route::resource('classes')` menghasilkan route `create`, `show`, dan `edit`, tetapi controller terkait tidak memiliki method tersebut. Route yang tidak dipakai ini perlu dirapikan atau dilengkapi pada fase lanjutan.
- Nama namespace/controller masih memakai `Admin`, sehingga migrasi role tidak cukup dilakukan hanya pada data role.
- Tidak ada route attendance view/export.
- Tidak ada route Program, Finance, Relation, SuperAdmin, atau school plotting.

## Current Attendance Flow

1. Coach membuka form report.
2. Daftar class pada halaman create dibatasi berdasarkan `coach_classes` milik Coach.
3. Browser memuat student melalui endpoint AJAX yang auth-only.
4. Coach mengirim materi, ringkasan, notes, media, dan status attendance.
5. `Coach\ReportController@store` membuat `Report`, `ReportMedia`, dan `ReportAttendance`.
6. Admin melihat detail report dan approve/reject.
7. PIC hanya melihat report berstatus `approved` dengan `school_id` miliknya.

Status attendance yang tersedia: `present`, `absent`, `sick`, `permission`.

Belum ada attendance query service atau school-scope abstraction yang dapat dipakai bersama oleh Relation, SPV Coach, PIC, Finance, dan SuperAdmin.

### Scope/security observations

- Halaman create membatasi class, tetapi `store()` hanya memvalidasi `class_id` exists; backend belum memastikan Coach memang assigned ke class tersebut.
- `store()` juga belum memastikan setiap `attendance[*]` adalah student dari class report.
- Endpoint student AJAX auth-only dapat dipanggil oleh authenticated user tanpa verifikasi role, assignment, atau school scope.
- Scope PIC pada index dan show diterapkan di backend melalui `report.school_id`, sehingga pola ini dapat dijadikan acuan untuk query scope baru.

## Current Export Flow

Belum ada export attendance/report. Fast-Excel hanya digunakan untuk import Student pada `StudentController@import`. Template student dibuat sebagai streamed CSV sederhana.

Tidak ada:

- export route;
- export controller/service;
- CSV export Finance;
- permission `attendance.export`;
- audit/logging export.

## Current Program Flow

Tidak ada entity atau workflow Program. Coach saat ini memasukkan `lesson_material` dan `activity_summary` langsung sebagai bagian dari Report. `SchoolClass` adalah kelas sekolah, bukan Program.

Karena belum ada model Program, hubungan Program â†” School/Class/Coach belum dapat dipastikan dari codebase dan harus diputuskan pada Phase 1 sebelum migration baru dibuat.

## Current Notification Flow

Tidak ada model Notification, event/listener, notification channel, webhook, HTTP client adapter, atau konfigurasi WaHa. Search terhadap `WAHA`, `WaHa`, `WhatsApp`, `webhook`, dan notification application flow tidak menemukan integrasi.

Credential Cloudinary sudah dibaca dari `.env` melalui `config/services.php`; tidak ada credential WaHa. Sesuai planning, implementasi WaHa harus berhenti pada audit/blocker sampai base URL, authentication, session, endpoint, payload, response, dan trigger bisnis tersedia.

## Current UI/Views

- Layout utama: `resources/views/layouts/app.blade.php`.
- UI menggunakan Bootstrap CDN; file Vite belum dipakai oleh layout.
- View tersedia untuk login, dashboard/admin, master school/class/coach, users, coach report, PIC report, dan students.
- Belum ada view Program, attendance export, Finance, Relation, SPV Coach, school plotting, atau Accident Notes.
- Accident Notes belum memiliki komponen maupun styling urgent merah.
- UI navigation dan label masih mengacu langsung pada `admin`, `coach`, dan `school_pic`.

## Existing Tests

Test yang tersedia hanya boilerplate:

- `tests/Unit/ExampleTest.php`: assertion `true`.
- `tests/Feature/ExampleTest.php`: meminta `GET /` dan mengharapkan 200.
- `tests/TestCase.php`: base Laravel TestCase tanpa helper tambahan.

Secara static, `GET /` mengembalikan redirect ke `/login`, sehingga feature test tersebut kemungkinan gagal dengan status 302 vs 200. PHPUnit/Laravel test belum dapat dijalankan dalam audit ini karena executable PHP tidak tersedia di environment.

Belum ada test untuk login, role middleware, school scope, CRUD, assignment, attendance, media, export, atau cross-school security.

## Runtime Verification Status

Perintah yang dicoba:

- `php artisan about`
- `php artisan route:list --except-vendor`
- `php artisan migrate:status`
- `php artisan test`

Semua terblokir sebelum aplikasi berjalan karena `php` tidak dikenali. Pemeriksaan environment juga tidak menemukan `composer`, `docker`, atau `podman`. Oleh sebab itu, status migration dan route runtime perlu diverifikasi ulang pada environment yang memiliki PHP CLI dan dependency terpasang.

## Potential Conflicts

1. **Admin â†’ Relation:** `admin` bukan sekadar label; role ini menguasai route dan business flow admin. Migration perlu memisahkan full-access SuperAdmin dari permission operasional Relation.
2. **Database role enum:** `users.role` dibatasi enum `admin`, `coach`, `school_pic`; penambahan enam role target memerlukan migration yang aman dan kompatibilitas dengan data existing.
3. **PIC plotting:** `users.school_id` hanya mendukung satu sekolah; requirement multi-school belum terkonfirmasi.
4. **Program domain:** tidak ada entity existing, sehingga relationship dan field tidak boleh diasumsikan.
5. **Attendance export:** belum ada query/export abstraction; implementasi baru harus mencegah duplikasi dan menerapkan scope sebelum export.
6. **Role checks tersebar:** route, controller, seeder, login redirect, dan Blade meng-hardcode role. Blind global replacement berisiko merusak alur.
7. **Existing route resources:** beberapa action resource belum tersedia di controller.
8. **Seeder:** `DatabaseSeeder` membuat user/data dengan `User::create()` tanpa idempotency; Docker command juga menjalankan `db:seed` saat startup.
9. **Frontend dependencies:** `vite.config.js` mengimpor plugin yang tidak tercantum pada `package.json`; pipeline asset perlu diverifikasi sebelum perubahan frontend.
10. **Security regression:** assignment Coach dan endpoint AJAX student perlu diperketat sebelum fitur school-scoped diperluas.

## Recommended Extension Points

### Aman untuk dipakai ulang

- `User` sebagai pusat authentication dan role awal;
- `School`, `SchoolClass`, `Student` untuk master data existing;
- `CoachClass` sebagai basis assignment Coach ke Program Kelas/class;
- `Report` + `ReportAttendance` sebagai sumber attendance existing;
- `SchoolPic\DashboardController` sebagai referensi backend school scope;
- `FastExcel` untuk extension export setelah query scope disatukan;
- Bootstrap layout dan pola Blade yang sudah ada.

### Perlu ditambahkan setelah Phase 1

- role/permission mapping yang eksplisit;
- Policy atau service untuk authorization dan school scope;
- strategi migrasi `admin` menjadi `superadmin`/`relation` tanpa privilege escalation;
- entity/pivot school plotting bila requirement multi-school dikonfirmasi;
- Program model dan relationship final;
- reusable attendance query/export service;
- Finance/Relation/SPV route dan view;
- Accident Notes UI berdasarkan field/model yang benar;
- WaHa adapter hanya setelah API contract tersedia;
- feature/security tests.

## Phase 0 Conclusion

Phase 0 audit selesai pada level source/static inspection. Codebase actual berbeda dari target planning terutama pada role architecture, permission layer, Program, export, Finance, school plotting, Accident Notes, dan WaHa. Tidak ada perubahan database atau business logic yang dilakukan pada Phase 0.

Urutan aman berikutnya adalah Phase 1: membuat data-model, role-permission, dan route mapping berdasarkan temuan audit ini, lalu meminta keputusan untuk blocker bisnis yang tercatat sebelum migration/authorization implementation.


