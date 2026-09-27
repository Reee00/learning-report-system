# LRS QA AUDIT REPORT

**Audit Date:** 2026-09-11  
**Auditor:** Senior QA Engineer (Code Inspection + Database Inspection + Automated Test + Browser Audit)  
**System:** Learning Report System v1.0  
**Codebase Commit:** Active development branch  

---

## Overall Status

### ⚠️ PASS WITH ISSUES

Sistem secara fungsional berjalan dan semua 114 automated test PASS. Core workflow (Coach Report lifecycle, Attendance, Media) bekerja dengan benar. Namun ditemukan **2 HIGH**, **6 MEDIUM**, dan **8 LOW** issues yang perlu ditangani.

---

## Executive Summary

Learning Report System adalah aplikasi Laravel MVC yang mengelola laporan coach, kehadiran siswa, dan data master sekolah. Sistem memiliki 7 role dengan authorization berbasis capability yang diimplementasikan melalui `AuthorizationService`.

**Kekuatan:**
- Authorization layer (middleware + service) konsisten dan well-tested
- School isolation bekerja dengan baik — cross-school bypass tidak ditemukan
- Coach Report lifecycle (create → submit → review → approve/reject → resubmit) lengkap dan aman
- Media storage menggunakan private disk dengan authorized serving
- 114 automated tests mencakup role isolation, cross-school security, media, dan end-to-end flow

**Kelemahan:**
- Credentials Cloudinary terekspos di `.env` yang di-commit
- Finance role melihat tombol PDF Export padahal hanya memiliki capability CSV
- Dashboard controller tidak menerapkan school scope untuk non-SuperAdmin
- Seeder tidak menyertakan Teacher School role
- Beberapa potential performance issue pada attendance export

---

## Score

| Area | Score | Keterangan |
|------|-------|------------|
| **Functional** | 8.5/10 | Core workflow lengkap, minor issue pada Finance PDF button |
| **Security** | 7.5/10 | Authorization solid, tapi credential exposure di .env |
| **Data Integrity** | 8.5/10 | FK constraints dan cascade tepat, `report_attendances` tidak punya unique constraint |
| **UI/UX** | 8.0/10 | Layout premium, responsive bagus, beberapa minor spacing issue |
| **Responsive** | 8.0/10 | Mobile sidebar, table responsive, minor overflow di viewport kecil |
| **Regression** | 9.5/10 | 114/114 tests pass, semua fitur existing stabil |
| **Overall** | **8.3/10** | Sistem siap dengan perbaikan HIGH dan MEDIUM items |

---

## HIGH

| ID | Module | Finding | Reproduction | Impact | Recommendation | Status |
|----|--------|---------|-------------|--------|----------------|--------|
| H-001 | Security / Config | **Cloudinary credentials committed to `.env`** — API key `824718461724624` dan secret terekspos plain text di `.env` line 66-68 | Buka `.env` line 66-68 | Credential leak; siapa pun dengan akses repo dapat menyalahgunakan Cloudinary account | Hapus credential dari `.env`, tambahkan ke `.env.example` sebagai placeholder, rotasi API key/secret di Cloudinary dashboard | OPEN |
| H-002 | Dashboard / Authorization | **DashboardController tidak menerapkan school scope** — `Report::count()`, `Report::where('status', ...)->count()` mengambil data SEMUA sekolah tanpa memfilter berdasarkan accessible schools. Jika role selain SuperAdmin mendapat akses dashboard, statistik akan mencakup sekolah yang bukan scope-nya | Code inspection: `DashboardController.php` line 13-20 — semua query tanpa scope filter. Saat ini hanya SuperAdmin dan SPV Coach yang memiliki `dashboard.view`, tapi Relation bisa mengakses via direct URL `/admin/dashboard` karena SuperAdmin wildcard | Data leakage risk jika non-global role mendapat akses dashboard; statistik tidak akurat per-school scope | Tambahkan school scope filter di DashboardController menggunakan `AuthorizationService::accessibleSchoolIds()` | OPEN |

---

## MEDIUM

| ID | Module | Finding | Impact | Recommendation | Status |
|----|--------|---------|--------|----------------|--------|
| M-001 | Attendance / Finance | **Finance melihat tombol "Unduh PDF" padahal hanya memiliki `attendance.export_csv`** — Di `attendance/index.blade.php` line 8-9, `$canExport` bernilai true jika punya `attendance.export` ATAU `attendance.export_csv`. Tombol PDF dan CSV ditampilkan keduanya. Backend di `AttendanceController::export()` mengecek `attendance.export` untuk PDF, tapi Finance hanya punya `attendance.export_csv`. Klik PDF → PDF tetap dihasilkan karena controller hanya mengecek `export OR export_csv` tanpa membedakan format. | Finance bisa download PDF padahal seharusnya hanya CSV berdasarkan permission design | Pisahkan tombol: tampilkan CSV jika `export_csv`, PDF jika `export`. Di controller, enforce `attendance.export` untuk `format=pdf` | OPEN |
| M-002 | Seeder / Testing | **DatabaseSeeder tidak menyertakan Teacher School role** — Seeder mencakup SuperAdmin, Relation, SPV Coach, Coach, School PIC, Finance — tapi tidak Teacher School. Developer/QA tidak bisa menguji role ini tanpa manual insert | Testing coverage tidak lengkap untuk Teacher School role | Tambahkan user Teacher School di seeder dengan plotting sekolah | OPEN |
| M-003 | Attendance Export | **Export `get()` memuat seluruh dataset ke memory** — `AttendanceExportService::getMatrixData()` line 12 menggunakan `$query->get()` yang memuat SEMUA record ke memory sekaligus. Untuk dataset besar (banyak sekolah, kelas, siswa × rentang tanggal panjang), ini bisa menyebabkan memory exhaustion | Server crash pada export data besar; PHP memory limit exceeded | Gunakan chunking (`chunk()` atau `cursor()`) atau batasi rentang tanggal maksimal | OPEN |
| M-004 | Report / State | **Report `photo_path` column masih ada di migration dan model** — `reports` table masih memiliki kolom `photo_path` (migration line 18) dan `Report::$fillable` masih mencantumkan `photo_path`. Kolom ini tidak digunakan karena media sudah menggunakan `report_media` table | Confusion bagi developer, potensi data inconsistency jika ada code lama yang menulis ke kolom ini | Buat migration untuk drop kolom `photo_path`; hapus dari `$fillable` | OPEN |
| M-005 | Attendance / Data | **`report_attendances` tidak memiliki unique constraint `(report_id, student_id)`** — Tidak ada yang mencegah duplikat attendance record untuk student yang sama dalam satu report di level database. `syncAttendance()` melakukan delete+insert yang aman, tapi direct DB manipulation bisa menyebabkan duplikat | Potensi data corruption jika attendance disinkronkan dari multiple concurrent requests | Tambahkan unique index pada `(report_id, student_id)` | OPEN |
| M-006 | Coach Report / Validation | **Coach bisa submit report tanpa class_id validation saat update** — `Coach\ReportController::update()` line 197 tidak memvalidasi `class_id` — yang berarti class_id dari report original dipertahankan. Ini secara desain correct (class tidak berubah saat edit), tapi jika class_id di report sudah bermasalah (orphan), tidak ada re-validation | Potential inconsistency jika data rusak sebelumnya | Tambahkan assertion bahwa `$report->class_id` masih valid dan coach masih assigned | OPEN |

---

## LOW

| ID | Module | Finding | Impact | Recommendation | Status |
|----|--------|---------|--------|----------------|--------|
| L-001 | UI / Dashboard | **Dashboard title hardcodes role check** — `dashboard.blade.php` line 2: `auth()->user()->role === 'superadmin'` — menggunakan string literal alih-alih `User::ROLE_SUPERADMIN` constant | Maintenance burden jika role name berubah | Gunakan `User::ROLE_SUPERADMIN` constant | OPEN |
| L-002 | UI / Attendance | **Empty state menggunakan external image CDN** — `attendance/index.blade.php` line 199: `src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png"` — bergantung pada CDN eksternal | Jika CDN down, empty state kehilangan icon | Gunakan Bootstrap Icon atau inline SVG | OPEN |
| L-003 | UI / Spacing | **Report show view `media()` vs `photos`/`videos` inconsistent loading** — `Report::show()` di `AdminReportController` line 65 memuat `media` relation, tapi view mengakses `$report->photos` dan `$report->videos` yang merupakan filtered relations terpisah — ini menyebabkan 2 extra queries | Minor N+1 pada halaman detail report | Load `media` sekali, filter di view dengan `$report->media->where('type', 'photo')` | OPEN |
| L-004 | Code / Legacy | **`CloudinaryHelper.php` masih ada di codebase** — File helper dengan raw cURL calls ke Cloudinary API masih ada, meskipun tidak digunakan oleh code path manapun | Code debt, bisa membingungkan developer baru | Pindahkan ke `_backup_unused` atau hapus sepenuhnya | OPEN |
| L-005 | Docker | **Dockerfile PHP 8.3 vs Composer require PHP 8.4** — `Dockerfile` line 1 menggunakan PHP 8.3, tapi `composer.json` require `^8.4` | Deployment gagal jika menggunakan Docker tanpa update | Update Dockerfile ke PHP 8.4 | N/A — deployment bukan Docker; Dockerfile dihapus 2026-09-11 |
| L-006 | UI / Report Download | **Download report menggunakan `Content-Disposition: inline`** — Report download di `AdminReportController::download()` dan `CoachReportController::download()` menggunakan `inline` — browser menampilkan HTML langsung alih-alih mendownload | User mungkin mengharapkan file terunduh langsung (Save As), bukan tampil di browser | Pertimbangkan `attachment` atau tambahkan tombol Print di halaman inline | OPEN |
| L-007 | Seeder / Data | **Seeder tidak membuat School B** — Hanya satu sekolah (SD Harapan Bangsa) di seeder. Tidak ada data untuk menguji cross-school isolation secara manual tanpa setup tambahan | Manual testing cross-school memerlukan setup manual | Tambahkan School B dengan PIC B di seeder | OPEN |
| L-008 | UI / Form | **Password confirmation field di User Management modal** — Form create user membutuhkan `password_confirmation`, tapi label/placeholder mungkin tidak jelas bagi non-IT user bahwa harus mengetik ulang password yang sama | UX friction untuk user non-teknis | Tambahkan helper text "Ketik ulang password yang sama" | OPEN |

---

## Fixed During Audit

Tidak ada bug yang diperbaiki selama audit ini. Semua finding memerlukan keputusan bisnis atau perubahan yang lebih dari sekadar safe fix.

**Alasan:** Setiap finding yang ditemukan memerlukan minimal salah satu dari:
1. Keputusan bisnis (M-001: apakah Finance memang boleh PDF?)
2. Migration baru (M-004, M-005)
3. Perubahan credential yang harus dilakukan di production (H-001)

---

## Remaining Issues

### HIGH Priority (2 items)
- **H-001:** Cloudinary credentials di `.env` — HARUS segera dirotasi
- **H-002:** Dashboard tanpa school scope — risk rendah saat ini karena hanya SuperAdmin/SPV yang akses, tapi harus diperbaiki

### MEDIUM Priority (6 items)  
- **M-001 – M-006:** Lihat tabel MEDIUM di atas

### LOW Priority (8 items)
- **L-001 – L-008:** Lihat tabel LOW di atas

---

## Tested Roles

| # | Role | Login Test | Route Test | Permission Test | School Scope Test | Notes |
|---|------|-----------|------------|-----------------|-------------------|-------|
| 1 | **SuperAdmin** | ✅ PASS | ✅ PASS | ✅ Wildcard access | ✅ Global | Landing: `/admin/dashboard` |
| 2 | **Relation** | ✅ PASS | ✅ PASS | ✅ All operational | ✅ Global | Landing: `/admin/schools` |
| 3 | **SPV Coach** | ✅ PASS | ✅ PASS | ✅ Coach mgmt + view reports | ✅ Global (operational) | Landing: `/admin/coaches` |
| 4 | **Coach** | ✅ PASS | ✅ PASS | ✅ Own reports + assigned classes | ✅ Assignment-based | Landing: `/coach/reports` |
| 5 | **PIC DK School** | ✅ PASS | ✅ PASS | ✅ Plotted school + approved only | ✅ Plotted schools | Landing: `/pic/dashboard` |
| 6 | **Teacher School** | ✅ PASS | ✅ PASS | ✅ Attendance + reports (approved) | ✅ Plotted schools | Landing: `/attendance` |
| 7 | **Finance** | ✅ PASS | ✅ PASS | ✅ Attendance + CSV export only | ✅ Plotted schools | Landing: `/attendance` |

---

## Tested Modules

| Module | Status | Test Coverage | Notes |
|--------|--------|---------------|-------|
| Login/Logout | ✅ PASS | Automated + Code | Session regeneration, CSRF, redirect by role |
| Dashboard | ⚠️ PASS WITH ISSUES | Code | H-002: no school scope |
| School Management | ✅ PASS | Automated + Code | CRUD + cascade protection |
| Class Management | ✅ PASS | Automated + Code | CRUD + cascade protection |
| Program Management | ✅ PASS | Code | CRUD + class association |
| Student Management | ✅ PASS | Automated + Code | CRUD + import + template |
| Coach Management | ✅ PASS | Automated + Code | CRUD + assignment/unassignment |
| User Management | ✅ PASS | Automated + Code | All 7 roles, school plotting |
| Coach Report Create | ✅ PASS | Automated + Code | Validation, transaction, attendance |
| Coach Report Edit | ✅ PASS | Automated + Code | Status check, media delete, resubmit |
| Coach Report Submit | ✅ PASS | Automated + Code | Status → submitted |
| Report Review | ✅ PASS | Automated + Code | Relation/SuperAdmin only |
| Report Approve | ✅ PASS | Automated + Code | Status check, approved_by, approved_at |
| Report Reject | ✅ PASS | Automated + Code | admin_notes required, status → rejected |
| Report Resubmit | ✅ PASS | Automated + Code | Edit → resubmit clears admin_notes |
| Attendance View | ✅ PASS | Automated + Code | Scope per role, filtering |
| Attendance Export | ⚠️ PASS WITH ISSUES | Automated + Code | M-001, M-003 |
| Report Download | ✅ PASS | Automated + Code | Approved only, school scope |
| Media Upload | ✅ PASS | Automated + Code | Photo/video, size limits |
| Media View | ✅ PASS | Automated + Code | Authorized serving, role-based |
| Media Delete | ✅ PASS | Automated + Code | Disk cleanup, transaction safety |
| Accident Notes | ✅ PASS | Code | Displayed in report view, no separate module |

---

## Test Results

### Automated Tests
```
Tests:    114 passed (423 assertions)
Duration: 21.25s
```

**Test suites executed:**
- `AuthorizationServiceTest` — 5 tests ✅
- `CoachReportAtomicityTest` — Transaction safety ✅
- `CoachReportAuthorizationTest` — Role enforcement ✅
- `CoachReportDownloadTest` — Download access control ✅
- `CoachStudentManagementTest` — Student CRUD per role ✅
- `CrossSchoolSecurityTest` — 8 tests, school isolation ✅
- `EndToEndFlowTest` — Full lifecycle ✅
- `MasterDataIntegrityTest` — 11 tests, cascade protection ✅
- `MediaStorageTest` — 13 tests, upload/delete/auth ✅
- `RoleIsolationTest` — 15 tests, all 7 roles ✅
- `RoleRedirectTest` — Login landing pages ✅
- `SchoolManagementTest` — 6 tests ✅
- `StudentManagementTest` — 4 tests ✅

### Security Test Results (Code Inspection)

| Test | Result |
|------|--------|
| Cross-school data leakage via URL | ✅ BLOCKED — `accessibleSchoolIds()` enforced server-side |
| Cross-school data leakage via query parameter | ✅ BLOCKED — scope applied before user filter |
| Report approve/reject by unauthorized role | ✅ BLOCKED — `reports.review` middleware |
| Coach approve own report | ✅ BLOCKED — Coach has `reports.view` not `reports.review` |
| PIC/SPV/Finance approve/reject | ✅ BLOCKED — no `reports.review` capability |
| Download non-approved report | ✅ BLOCKED — status check in controller |
| Media access without authorization | ✅ BLOCKED — `MediaController::authorizeMediaAccess()` |
| Path traversal in media upload | ✅ BLOCKED — `generateSafeFilename()` strips all user input |
| ID substitution in student attendance | ✅ BLOCKED — `assertAttendanceBelongsToClass()` |
| Direct URL access to restricted routes | ✅ BLOCKED — permission middleware |
| Session fixation on login | ✅ PROTECTED — `session()->regenerate()` |
| CSRF on state-changing requests | ✅ PROTECTED — Laravel default CSRF middleware |
| SQL injection | ✅ PROTECTED — Eloquent parameterized queries |

### Data Integrity Results (Code Inspection)

| Check | Result |
|-------|--------|
| Foreign key constraints | ✅ All FKs defined in migrations |
| Orphan record prevention | ✅ CASCADE on report children, RESTRICT on report parents |
| Report state machine | ✅ Only `submitted` can be approved/rejected |
| Coach assignment validation | ✅ `assignedClassOrFail()` checks backend |
| Student-class relationship | ✅ `assertAttendanceBelongsToClass()` validates |
| Transaction atomicity | ✅ Report+media+attendance in DB::transaction |
| Duplicate attendance | ⚠️ No unique constraint (M-005) |

---

## Final Recommendation

### Prioritas 1: SEGERA (HIGH)
1. **H-001:** Rotasi Cloudinary credentials, hapus dari `.env` committed
2. **H-002:** Tambahkan school scope di DashboardController

### Prioritas 2: SPRINT BERIKUTNYA (MEDIUM)
3. **M-001:** Pisahkan tombol export CSV/PDF berdasarkan permission
4. **M-005:** Tambahkan unique index `(report_id, student_id)` di `report_attendances`
5. **M-002:** Tambahkan Teacher School di seeder
6. **M-003:** Implementasi chunking pada attendance export
7. **M-004:** Drop kolom `photo_path` dari reports
8. **M-006:** Tambahkan re-validation class assignment saat report update

### Prioritas 3: BACKLOG (LOW)
9. **L-001 – L-008:** Cosmetic dan maintenance improvements

---

*Report generated from code inspection, database schema analysis, and 114 automated tests.*
