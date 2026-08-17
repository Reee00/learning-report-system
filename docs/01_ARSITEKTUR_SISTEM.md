# 🏗️ Arsitektur Sistem

**Terakhir diperbarui:** 17 Agustus 2026
**Basis:** kode aktual — `routes/web.php`, `bootstrap/app.php`, `app/Services/`, skema SQLite via `PRAGMA`

---

## 1. Gambaran Arsitektur

Aplikasi mengikuti pola MVC Laravel dengan satu tambahan penting: **lapisan Service** yang menampung
keputusan otorisasi dan scope data, sehingga controller tidak mengulang logika akses.

```
┌─────────────────────────────────────────────────────────────────────┐
│                            CLIENT (Browser)                          │
│                Blade + Bootstrap 5.3 (CDN) — tanpa build step         │
└─────────────────────────────────┬────────────────────────────────────┘
                                  │ HTTP
┌─────────────────────────────────▼────────────────────────────────────┐
│                          ROUTING  routes/web.php                     │
│   44 route aplikasi + health endpoint /up (bootstrap/app.php)         │
└─────────────────────────────────┬────────────────────────────────────┘
                                  │
┌─────────────────────────────────▼────────────────────────────────────┐
│                            MIDDLEWARE                                │
│   auth            → Illuminate\Auth\Middleware\Authenticate           │
│   permission:x    → PermissionMiddleware        (satu capability)     │
│   permission_any  → PermissionAnyMiddleware     (salah satu)          │
│   role:x,y        → RoleMiddleware              (coarse gate)         │
└─────────────────────────────────┬────────────────────────────────────┘
                                  │
┌─────────────────────────────────▼────────────────────────────────────┐
│                           CONTROLLER (13)                            │
│   Memvalidasi request, MENGULANG pemeriksaan capability di boundary,  │
│   lalu mendelegasikan scope ke Service.                              │
└─────────────────────────────────┬────────────────────────────────────┘
                                  │
┌─────────────────────────────────▼────────────────────────────────────┐
│                            SERVICE (3)                               │
│   AuthorizationService     peta role→permission, school/class scope   │
│   AttendanceScopeService   builder absensi yang sudah ter-scope        │
│   AttendanceExportService  CSV streaming dari builder yang sama       │
└─────────────────────────────────┬────────────────────────────────────┘
                                  │
┌─────────────────────────────────▼────────────────────────────────────┐
│                        MODEL / ELOQUENT (10)                         │
│   User · School · SchoolClass · Student · CoachClass · Report         │
│   ReportAttendance · ReportMedia · Program · ProgramClass             │
└─────────────────────────────────┬────────────────────────────────────┘
                                  │
┌─────────────────────────────────▼────────────────────────────────────┐
│                     DATABASE  SQLite (13 tabel)                      │
└──────────────────────────────────────────────────────────────────────┘
                                  │
                    ┌─────────────┴──────────────┐
                    ▼                            ▼
        ┌──────────────────────┐   ┌──────────────────────────┐
        │ Cloudinary (media)   │   │ FastExcel (import siswa) │
        │ CloudinaryHelper     │   │ rap2hpoutre/fast-excel   │
        │ ⚠ ISSUE-014 terbuka  │   └──────────────────────────┘
        └──────────────────────┘
```

## 2. Request Lifecycle

```
1  public/index.php
2  bootstrap/app.php            — routing, alias middleware, trustProxies(*)
3  Global middleware web        — session (driver: file), CSRF, cookie encryption
4  Route matching               — routes/web.php
5  Route middleware
      auth                      → belum login? redirect ke route('login')
      permission:<cap>          → AuthorizationService::allows() gagal? 403
      permission_any:<a>,<b>    → tidak satu pun terpenuhi? 403
      role:<x>,<y>              → role tidak cocok? 403
6  Controller
      $request->validate([...])
      ensurePermission('<cap>') / abort_unless(...)     ← diulang, sengaja
      resolve object dari DB (bukan dari form)
7  Service
      accessibleSchoolIds()  → null (global) | array (dibatasi)
      query scope diterapkan LEBIH DULU
      filter dari request diterapkan SESUDAHNYA
8  Eloquent → SQLite
9  Blade view / StreamedResponse (CSV)
```

Langkah 6 terlihat redundan terhadap langkah 5 dan itu memang disengaja: middleware menjaga *route*,
controller menjaga *object*. Route boleh benar sementara object yang diminta milik sekolah lain.

## 3. Lapisan Aplikasi

### 3.1 Controller (13 file)

| Controller | Route family | Capability |
|---|---|---|
| `Admin\DashboardController` | `admin.dashboard` | `dashboard.view` |
| `Admin\UserController` | `admin.users.*` | `users.manage` |
| `Admin\ReportController` | `admin.reports.*` | `reports.review` |
| `Admin\SchoolController` | `admin.schools.*` | `schools.view/create/update/delete` |
| `Admin\ClassController` | `admin.classes.*` | `program_classes.view/create/delete` |
| `Admin\ProgramController` | `admin.programs.*` | `programs.view/create` |
| `Admin\CoachController` | `admin.coaches.*` | `coaches.view/create/update/assign/reassign` |
| `AttendanceController` | `attendance.index`, `attendance.export` | `attendance.view`, `attendance.export`/`attendance.export_csv` |
| `Auth\LoginController` | `login`, `logout` | — (publik) |
| `Coach\ReportController` | `coach.reports.*` | `reports.view/create/update` + `role:coach` |
| `SchoolPic\DashboardController` | `pic.*` | `role:school_pic` + `attendance.view` |
| `StudentController` | `students.*` | `students.view/create/delete` |
| `Controller` | — | base class |

### 3.2 Service (3 file, `app/Services/`)

**`AuthorizationService`** — satu-satunya sumber kebenaran otorisasi.

```php
private const ROLE_PERMISSIONS = [
    'relation'   => ['schools.view','schools.create','students.view','students.create',
                     'program_classes.view','program_classes.create','programs.view',
                     'programs.create','attendance.view','attendance.export'],
    'spv_coach'  => ['dashboard.view','coaches.view','coaches.create','coaches.update',
                     'coaches.assign','coaches.reassign','attendance.view','attendance.export'],
    'coach'      => ['reports.view','reports.create','reports.update','students.view',
                     'students.create','students.delete','accident_notes.view'],
    'school_pic' => ['attendance.view','attendance.export','students.view'],
    'finance'    => ['attendance.view','attendance.export_csv'],
];

public function allows(User $user, string $permission): bool
{
    if ($user->isSuperAdmin()) { return true; }               // wildcard
    return in_array($permission, self::ROLE_PERMISSIONS[$user->role] ?? [], true);
}

public function accessibleSchoolIds(User $user): ?array
{
    if ($user->isSuperAdmin() || $user->isRelationUser() || $user->role === User::ROLE_SPV_COACH) {
        return null;                                          // operasional global
    }
    return $user->assignedSchoolIds();                        // dibatasi
}
```

Dua properti yang menopang keamanan:

- `allows()` adalah **allow-list ketat**. Role tak dikenal mendapat `[]` — tidak mendapat apa-apa,
  bukan mendapat segalanya.
- `accessibleSchoolIds()` membedakan `null` dari `[]`. `null` berarti "tanpa filter sekolah"; `[]`
  berarti `whereIn('school_id', [])` → nol baris. Kesalahan yang paling berbahaya di kelas ini adalah
  membiarkan `[]` merosot menjadi "tanpa filter", dan itu tidak terjadi di sini.

**`AttendanceScopeService`**

```php
public function query(User $user, array $filters = []): Builder
// 1. scope role & sekolah diterapkan lewat whereHas(scopeReports)
// 2. baru kemudian ->when($filters['school_id'] ...) dan filter lain
```

Scope per role di `scopeReports()`:

| Role | Scope |
|---|---|
| `superadmin`, `relation` | seluruh laporan |
| `spv_coach` | laporan dari kelas yang punya minimal satu coach assignment |
| `coach` | `coach_id = $user->id` |
| `school_pic`, `finance` | `whereIn('school_id', accessibleSchoolIds())` **dan** `status = approved` |

**`AttendanceExportService`**

```php
public function download(Builder $query, string $filename): StreamedResponse
// streamDownload + chunkById(500); eager load ikut terjaga per chunk
```

Header CSV yang stabil (dijaga test):

```csv
tanggal,sekolah,kelas,coach,siswa,status_absensi,status_laporan
```

`Content-Type: text/csv; charset=UTF-8`.

### 3.3 Middleware (3 file, `app/Http/Middleware/`)

Alias terdaftar di [bootstrap/app.php](../bootstrap/app.php):

```php
$middleware->alias([
    'role'           => \App\Http\Middleware\RoleMiddleware::class,
    'permission'     => \App\Http\Middleware\PermissionMiddleware::class,
    'permission_any' => \App\Http\Middleware\PermissionAnyMiddleware::class,
    'auth'           => \Illuminate\Auth\Middleware\Authenticate::class,
]);
$middleware->trustProxies(at: '*');
```

| Middleware | Bentuk pemakaian | Fungsi |
|---|---|---|
| `PermissionMiddleware` | `permission:attendance.view` | Wajib punya satu capability |
| `PermissionAnyMiddleware` | `permission_any:attendance.export,attendance.export_csv` | Cukup salah satu — dipakai supaya Relation/SPV/PIC dan Finance berbagi satu route export |
| `RoleMiddleware` | `role:coach` / `role:school_pic` | Coarse gate untuk portal khusus role, dipasang **bersama** capability |

### 3.4 Model (10 file)

| Model | Tabel | Relasi utama |
|---|---|---|
| `User` | `users` | `school()` legacy · `schools()` pivot `school_user` · `coachClasses()` · `reports()` |
| `School` | `schools` | `classes()` · `reports()` · `users()` |
| `SchoolClass` | `classes` | `school()` · `students()` · `coachAssignments()` · `reports()` · `programClasses()` |
| `Student` | `students` | `schoolClass()` |
| `CoachClass` | `coach_classes` | `coach()` · `schoolClass()` |
| `Report` | `reports` | `coach()` · `school()` · `schoolClass()` · `approver()` · `attendances()` · `media()` |
| `ReportAttendance` | `report_attendances` | `report()` · `student()` |
| `ReportMedia` | `report_media` | `report()` |
| `Program` | `programs` | `programClasses()` · `classes()` (belongsToMany via `program_classes`) |
| `ProgramClass` | `program_classes` | `program()` · `schoolClass()` |

Catatan desain: **tidak ada model `Coach`**. Coach adalah `User` dengan `role = 'coach'`. Demikian pula
tidak ada model `Role` — role adalah kolom string pada `users`, dengan metadata terpusat di
`User::roleKeys()` / `roleLabels()` / `roleBadgeColors()` / `schoolScopedRoles()`.

### 3.5 Helper

`app/Helpers/CloudinaryHelper.php` — `upload()` dan `delete()`. `upload()` mengembalikan
`json_decode($response, true)`, sehingga **kegagalan menghasilkan array berisi `error.message` tanpa
`secure_url`**. Pemanggil wajib memeriksa keberadaan `secure_url`; melakukan `$result['secure_url']`
langsung adalah penyebab BUG-012 dulu.

## 4. Entity Relationship Diagram

```
                        ┌───────────────┐
                        │    schools    │
                        │ id, name,     │
                        │ address,      │
                        │ pic_name      │
                        └───┬───┬───┬───┘
        ┌───────────────────┘   │   └──────────────────────┐
        │ 1:N                   │ 1:N (pivot)              │ 1:N
┌───────▼────────┐      ┌───────▼────────┐        ┌────────▼───────┐
│    classes     │      │  school_user   │        │    reports     │
│ id, school_id, │      │ school_id,     │        │ id, coach_id,  │
│ name           │      │ user_id        │        │ school_id,     │
└──┬──┬──┬───┬───┘      │ UNIQUE(pair)   │        │ class_id,      │
   │  │  │   │          └───────┬────────┘        │ report_date,   │
   │  │  │   │                  │ N:1             │ lesson_material│
   │  │  │   │          ┌───────▼────────┐        │ activity_      │
   │  │  │   └─────────►│     users      │◄───────┤  summary,      │
   │  │  │      1:N     │ id, name,      │  N:1   │ notes,         │
   │  │  │              │ email,         │        │ photo_path,    │
   │  │  │              │ password,      │        │ status,        │
   │  │  │              │ role,          │        │ admin_notes,   │
   │  │  │              │ school_id      │        │ approved_by,   │
   │  │  │              └───────┬────────┘        │ approved_at    │
   │  │  │                      │ 1:N             └───┬────────┬───┘
   │  │  │              ┌───────▼────────┐            │ 1:N    │ 1:N
   │  │  └─────────────►│ coach_classes  │    ┌───────▼──────┐ │
   │  │        1:N      │ coach_id,      │    │report_       │ │
   │  │                 │ class_id       │    │ attendances  │ │
   │  │                 │ UNIQUE(pair)   │    │ report_id,   │ │
   │  │                 └────────────────┘    │ student_id,  │ │
   │  │                                       │ status       │ │
   │  │  ┌────────────────┐                   └───────┬──────┘ │
   │  └─►│    students    │◄──────────────────────────┘        │
   │ 1:N │ id, class_id,  │            N:1                     │
   │     │ name           │                            ┌───────▼──────┐
   │     └────────────────┘                            │ report_media │
   │                                                   │ report_id,   │
   │ 1:N                                               │ type, path,  │
┌──▼──────────────┐        ┌────────────────┐          │ original_name│
│ program_classes │───────►│    programs    │          └──────────────┘
│ program_id,     │  N:1   │ id, name,      │          ⚠ tanpa kolom
│ class_id        │        │ code UNIQUE,   │            cloudinary_
│ UNIQUE(pair)    │        │ description,   │            public_id
└─────────────────┘        │ status         │            (MEDIA-001)
                           └────────────────┘
```

## 5. Aturan Penghapusan (onDelete) — hasil `PRAGMA foreign_key_list`

Bagian ini pernah salah didokumentasikan. Nilai di bawah adalah hasil pembacaan langsung dari skema.

| Tabel anak | Kolom | Induk | onDelete |
|---|---|---|---|
| `classes` | `school_id` | `schools` | **CASCADE** |
| `students` | `class_id` | `classes` | **CASCADE** |
| `coach_classes` | `coach_id` | `users` | **CASCADE** |
| `coach_classes` | `class_id` | `classes` | **CASCADE** |
| `report_attendances` | `report_id` | `reports` | **CASCADE** |
| `report_attendances` | `student_id` | `students` | **CASCADE** |
| `report_media` | `report_id` | `reports` | **CASCADE** |
| `program_classes` | `program_id` | `programs` | **CASCADE** |
| `program_classes` | `class_id` | `classes` | **CASCADE** |
| `school_user` | `school_id` | `schools` | **CASCADE** |
| `school_user` | `user_id` | `users` | **CASCADE** |
| `users` | `school_id` | `schools` | **SET NULL** |
| `reports` | `approved_by` | `users` | **SET NULL** |
| `reports` | `school_id` | `schools` | **NO ACTION** (RESTRICT) |
| `reports` | `class_id` | `classes` | **NO ACTION** (RESTRICT) |
| `reports` | `coach_id` | `users` | **NO ACTION** (RESTRICT) |

Konsekuensi yang penting: **laporan tidak ikut terhapus** ketika sekolah, kelas, atau coach dihapus.
Karena FK-nya restrict, database akan menolak. Aplikasi mengubah penolakan itu menjadi pesan yang bisa
dibaca, bukan error 500:

```php
// Admin\SchoolController::destroy()  — pola yang sama di ClassController & UserController
if ($school->reports()->exists()) {
    return back()->with('error',
        'Sekolah tidak bisa dihapus karena masih memiliki laporan. '
        .'Hapus atau pindahkan laporan terkait terlebih dahulu.');
}
```

Sekolah/kelas **tanpa** laporan tetap bisa dihapus, dan turunannya (kelas, siswa, assignment) ikut
terhapus lewat CASCADE. Perilaku ini diuji oleh 6 test di `MasterDataIntegrityTest`.

## 6. Redirect setelah Login

`Auth\LoginController::redirectByRole()` — enam role dipetakan eksplisit, tanpa fallback ke `/`:

```php
return match ($role) {
    User::ROLE_SUPERADMIN => redirect()->route('admin.dashboard'),
    User::ROLE_RELATION   => redirect()->route('admin.schools.index'),
    User::ROLE_SPV_COACH  => redirect()->route('admin.coaches.index'),
    User::ROLE_COACH      => redirect()->route('coach.reports.index'),
    User::ROLE_SCHOOL_PIC => redirect()->route('pic.dashboard'),
    User::ROLE_FINANCE    => redirect()->route('attendance.index'),
    default               => abort(403, 'Role akun belum memiliki halaman awal. Hubungi SuperAdmin.'),
};
```

`default => redirect('/')` **dilarang**: `/` me-redirect ke `login`, jadi role tanpa mapping akan
terjebak loop `login ↔ /`. Itu persisnya BUG-005 yang menimpa `spv_coach`.

## 7. Alur Data Kritis

### 7.1 Coach mengirim laporan (atomik)

```
POST /coach/reports
  ↓ auth + role:coach + permission:reports.create
  ↓ validate(class_id, report_date, lesson_material, activity_summary,
             notes, photos[≤10], videos[≤3], attendance[])
  ↓ assignedClassOrFail(class_id)         ← kelas diambil dari coach_classes, BUKAN dari form
  ↓ assertAttendanceBelongsToClass(...)   ← KEY array attendance juga input; diverifikasi
  ↓ DB::transaction {
        Report::create(status = 'submitted')
        upload media → cek 'secure_url'; tidak ada → ValidationException (rollback)
        ReportAttendance::insert(...)
    }
  ↓ commit
  ↓ hapus aset Cloudinary lama (SETELAH commit, supaya rollback tidak menghapus file yang barisnya kembali)
  ↓ redirect coach.reports.index
```

### 7.2 Absensi dibaca & diekspor

```
GET /attendance                       GET /attendance/export
  permission:attendance.view            permission_any:attendance.export,attendance.export_csv
        │                                        │
        └──────────────┬─────────────────────────┘
                       ▼
        AttendanceScopeService::query($user, $validatedFilters)
                       │  scope role/sekolah DULU → filter request SESUDAHNYA
        ┌──────────────┴───────────────┐
        ▼                              ▼
  Blade attendance/index      AttendanceExportService::download()
  paginate                     streamDownload + chunkById(500)
```

Karena keduanya memanggi builder yang sama dengan `$filters` yang sama, isi CSV tidak mungkin lebih luas
dari isi tabel — diuji oleh `CrossSchoolSecurityTest::export_dataset_matches_the_filtered_table`.

## 8. Keputusan Arsitektur & Alasannya

| Keputusan | Alasan |
|---|---|
| Capability, bukan role string | Menambah role tidak berarti menyebar `if ($role === ...)` ke seluruh controller |
| Tanpa package permission (Spatie dsb.) | Kebutuhan masih satu peta statis; menambah dependency & 4 tabel tidak sepadan |
| Tanpa tabel `roles` | Role adalah nilai string pada `users` + metadata terpusat di model `User` |
| Prefix URL `admin.*` dipertahankan | Compatibility layer supaya link/bookmark lama tidak rusak; `admin` = prefix, bukan role |
| `SchoolClass` dipakai ulang untuk "Program Kelas" | Menghindari duplikasi master kelas; konteks program diwakili `ProgramClass` |
| Satu route export untuk semua role | `permission_any` mencegah lahirnya exporter duplikat per role |
| Absensi selalu lewat Service | Satu tempat untuk aturan scope; tabel dan export tidak bisa menyimpang |
| FK `reports` RESTRICT | Laporan historis adalah catatan; tidak boleh hilang karena master data dihapus |
| Tanpa Vite | Tidak ada CSS/JS custom yang perlu dibundel; Bootstrap 5.3 dari CDN cukup |

## 9. Batas Sistem (yang belum ada)

| Hal | Status |
|---|---|
| `routes/api.php` | Tidak ada. Endpoint AJAX siswa didefinisikan sebagai closure di `web.php` |
| REST API publik / token auth | Tidak ada. Semua akses berbasis session |
| Queue worker | `QUEUE_CONNECTION=sync` — tidak ada worker |
| Event / Listener / Job | Tidak ada |
| Policy Laravel | Tidak dipakai; perannya diambil `AuthorizationService` |
| SoftDeletes | Tidak dipakai di tabel mana pun |
| Notifikasi WhatsApp / WaHa | **Tidak ada** — Phase 13 |
