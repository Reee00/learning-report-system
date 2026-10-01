# Autentikasi dan Otorisasi

Disinkronkan dengan kode: **2026-09-29**. Sumber: `app/Services/AuthorizationService.php`, `bootstrap/app.php`, `routes/web.php`.

## Autentikasi

Login dan logout memakai session authentication Laravel. Route terlindungi middleware `auth`.

Hardening login (audit keamanan 2026-09-11):

- Rate limiting berkunci `email|ip` — 5 percobaan gagal per kombinasi dengan decay progresif.
- Pesan galat generik yang identik untuk email salah maupun password salah (anti account enumeration).
- Session ID di-regenerate saat login sukses; logout meng-invalidate session dan me-regenerate CSRF token.

Pendaftaran perangkat Web Push (`push-subscriptions`) terbuka untuk **semua peran yang login** — kanal push melekat pada pengguna, bukan pada peran.

## Middleware

| Alias | Kelas | Arti |
|---|---|---|
| `auth` | `Authenticate` | Harus login |
| `role` | `RoleMiddleware` | Peran harus salah satu yang disebut |
| `permission` | `PermissionMiddleware` | Harus memiliki capability |
| `permission_any` | `PermissionAnyMiddleware` | Cukup salah satu capability |

`bootstrap/app.php` juga menyetel `trustProxies(at: '*')` dan merender `PostTooLargeException` menjadi JSON 413 (permintaan AJAX) atau redirect-back berisi pesan galat (form biasa).

## Sumber Kebenaran Capability

`AuthorizationService::ROLE_PERMISSIONS` adalah peta peran → capability. `allows()` mengembalikan `true` tanpa syarat untuk **SuperAdmin** (wildcard), lalu mencocokkan capability secara ketat untuk peran lain.

**`users.manage` tidak diberikan ke peran mana pun**, sehingga hanya SuperAdmin yang dapat mengelola pengguna dan membaca activity log. Ini disengaja.

Daftar 7 peran dan matriks lengkapnya ada di [reference/permissions.md](reference/permissions.md).

## Kelompok Capability

| Awalan | Cakupan |
|---|---|
| `dashboard.view` | Dasbor admin |
| `schools.*` | Master sekolah |
| `programs.*`, `program_classes.*` | Master program dan kelas |
| `coaches.*` | Master coach dan penugasannya |
| `students.*` | Master murid |
| `schedules.*` | Jadwal mengajar |
| `reports.*` | Laporan |
| `attendance.*` | Kehadiran |
| `notifications.send` | Notifikasi manual |
| `accident_notes.view` | Catatan kecelakaan (menu pribadi coach) |
| `users.manage` | Pengguna & activity log (SuperAdmin saja) |

Perhatikan bahwa `reports.view` (laporan sendiri, coach) berbeda dari `reports.view_all` (lintas sekolah), dan `reports.review` berbeda dari keduanya. Demikian pula `attendance.export` berbeda dari `attendance.export_csv`: yang pertama mencakup CSV + Excel + PDF, yang kedua hanya CSV data mentah. Sejak review meeting 2026-10-01 Finance memegang `attendance.export`, sehingga `attendance.export_csv` kini tidak dipegang role mana pun.

## Scope Sekolah

| Peran | Scope |
|---|---|
| SuperAdmin, Relation, SPV Coach | Global operasional |
| Coach | Laporan milik sendiri; kelas lewat `coach_classes` **atau** sesi mengajar tempat ia bertugas |
| PIC DK SCHOOL, TEACHER SCHOOL | Sekolah yang diplot lewat pivot `school_user`, ditambah `users.school_id` legacy; laporan/kehadiran yang tampil harus `approved` |
| Finance | **All-school** — scope datang dari peran (`accessibleSchoolIds()` mengembalikan `null`), bukan dari plot sekolah; satu-satunya pembatasnya adalah status `approved` |

Finance tidak termasuk `User::schoolScopedRoles()`, sehingga form akun tidak meminta plot sekolah untuk peran ini.

## Akses Satu Laporan: `canAccessReport()` (2026-09-29)

Capability di middleware baru menyatakan **jenis** akses, bukan **objek** mana yang boleh disentuh. Untuk laporan, batas objeknya ditulis satu kali di `AuthorizationService::canAccessReport()`:

| Peran | Aturan objek |
|---|---|
| Coach | `reports.coach_id` = dirinya, **atau** terlibat pada sesi laporan itu (`teaching_schedules` lewat `scopeForCoach` — coach utama maupun pendamping). Per **sesi**, bukan per sekolah. |
| Peran lain | `canAccessSchool()` seperti sebelumnya. |

Role coach **tidak** memakai `canAccessSchool()` karena `accessibleSchoolIds()` bernilai `null` (global) untuk coach — memakai scope sekolah berarti meloloskan coach ke seluruh sekolah. Karena itu `Admin\ReportController::download()` bercabang: role coach diperiksa dengan `canAccessReport()`, role lain dengan `ensureSchoolAccess()`.

Dipakai oleh `coach.reports.show`, `coach.reports.download`, dan `admin.reports.download`. Sebelum perbaikan 2026-09-29, coach mendapat **HTTP 200** di `admin.reports.download` untuk laporan coach/sekolah lain (rute hanya butuh `reports.download`, yang dimiliki coach); sekarang → **403**. Perbaikan ini tidak mengubah akses role lain.

Gerbang **media** (`MediaController`) tetap lebih ketat dan tidak memakai `canAccessReport()`: coach hanya boleh berkas pada laporan **miliknya** (`reports.coach_id`). Lihat [modules/media.md](modules/media.md).

## Dua Kewenangan Menulis Laporan

`canReportOnClass()` mengenali tepat dua sumber kewenangan coach:

1. **Permanen** — penugasan `coach_classes`, berlaku untuk tanggal mana pun.
2. **Sementara** — sesi mengajar (`teaching_schedules`), sebagai coach utama maupun coach pendamping, **tanpa** mengubah `coach_classes`.

Coach yang ditarik dari sesi kehilangan kewenangan sementara itu, tetapi laporan yang sudah dibuat tetap tersimpan. Coach pendamping juga boleh membaca roster kelas yang diajar (`canViewClassRoster()`) tanpa memperoleh wewenang pengelolaan murid.

## Aturan yang Tidak Boleh Dilanggar

- **Scope diperiksa di backend.** Filter dari klien hanya dapat mempersempit; ia tidak pernah memperluas. Service menerapkan scope lebih dulu, baru filter request.
- **Keamanan tidak boleh bergantung pada tombol yang disembunyikan.** Setiap tombol yang tidak dirender harus punya pasangan penolakan di server.
- `admin/*` bukan bukti adanya peran `admin`.
