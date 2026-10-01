# LRS QA AUDIT REPORT

**Audit Date:** 2026-09-11
**Auditor:** Senior QA Engineer (Code Inspection + Database Inspection + Automated Test + Browser Audit)
**System:** Learning Report System v1.0
**Codebase Commit:** Active development branch

> ## Status diperbarui: 2026-09-28
>
> **Sinkronisasi 2026-09-29:** perubahan kode 2026-09-29 (authorization coach pada unduhan laporan, kolom materi di riwayat laporan, halaman detail coach, tautan "Lihat Video" di PDF, unduhan foto/video terotorisasi, Accident Notes sebagai menu pribadi coach) tercatat pada baris **Status** di tabel modul dan pada [Test Results](#test-results). Tidak ada migrasi baru.
>
> Laporan ini adalah **hasil audit pada 2026-09-11**. Isi temuan dan skornya dipertahankan sebagai catatan sejarah; kolom **Status** di setiap tabel sudah diperbarui agar mencerminkan kondisi kode saat ini.
>
> Ringkasan perubahan status: **2 HIGH selesai**, **5 dari 6 MEDIUM selesai**, **5 dari 8 LOW selesai**. Satu temuan LOW (L-005) sudah tidak relevan.
>
> **Redaksi keamanan (2026-09-28):** nilai kredensial Cloudinary yang sebelumnya tertulis apa adanya di dokumen ini sudah diganti placeholder. **CLOSED 2026-09-28** — kredensial lama sudah dihapus/diinvalidasi di sisi penyedia, sehingga nilai yang tersisa di riwayat git tidak lagi dapat dipakai. Lihat [12_KEAMANAN_PEMELIHARAAN_PENGEMBANGAN.md](../12_KEAMANAN_PEMELIHARAAN_PENGEMBANGAN.md).

---

## Overall Status (per 2026-09-11)

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

**Kelemahan (kondisi 2026-09-11; seluruhnya sudah ditutup per 2026-09-28 — lihat [Remaining Issues](#remaining-issues-per-2026-09-28)):**
- Credentials Cloudinary tertulis plain text di dokumentasi QA yang terlacak git (`.env` sendiri tidak terlacak) → **CLOSED 2026-09-28** (nilai di-redaksi; kredensial lama dihapus/diinvalidasi di penyedia)
- Finance role melihat tombol PDF Export padahal hanya memiliki capability CSV
- Dashboard controller tidak menerapkan school scope untuk non-SuperAdmin
- Seeder tidak menyertakan Teacher School role
- Beberapa potential performance issue pada attendance export

---

## Score (per 2026-09-11)

| Area | Score | Keterangan |
|------|-------|------------|
| **Functional** | 8.5/10 | Core workflow lengkap, minor issue pada Finance PDF button |
| **Security** | 7.5/10 | Authorization solid, tapi credential exposure di dokumentasi QA |
| **Data Integrity** | 8.5/10 | FK constraints dan cascade tepat, `report_attendances` tidak punya unique constraint |
| **UI/UX** | 8.0/10 | Layout premium, responsive bagus, beberapa minor spacing issue |
| **Responsive** | 8.0/10 | Mobile sidebar, table responsive, minor overflow di viewport kecil |
| **Regression** | 9.5/10 | 114/114 tests pass, semua fitur existing stabil |
| **Overall** | **8.3/10** | Sistem siap dengan perbaikan HIGH dan MEDIUM items |

> Skor di atas menggambarkan kondisi **2026-09-11** dan tidak dihitung ulang pada sinkronisasi 2026-09-28. Angka test (114) juga angka historis — lihat bagian [Test Results](#test-results) untuk kondisi terkini.

---

## HIGH

| ID | Module | Finding | Impact | Recommendation | Status |
|----|--------|---------|--------|----------------|--------|
| H-001 | Security / Config | **Cloudinary credentials terekspos di dokumentasi** — API key dan secret Cloudinary tertulis plain text di `docs/qa/HIGH-PRIORITY.md`. Nilai asli di-redaksi dari dokumen ini. (Verifikasi 2026-09-28: `.env` sendiri **tidak pernah** terlacak git.) | Credential leak; siapa pun dengan akses repo dapat menyalahgunakan akun Cloudinary | Hapus credential, pakai placeholder di `.env.example`, **rotasi key/secret di dashboard penyedia** | **CLOSED (2026-09-28)** — integrasi Cloudinary dihapus penuh dari kode (2026-09-11); kredensial lama sudah **dihapus/diinvalidasi** di sisi penyedia, sehingga nilai di riwayat git tidak lagi dapat dipakai. |
| H-002 | Dashboard / Authorization | **DashboardController tidak menerapkan school scope** — semua query statistik mengambil data SEMUA sekolah tanpa memfilter `accessibleSchoolIds()` | Data leakage risk bila peran non-global diberi akses dashboard; statistik tidak akurat per scope | Tambahkan school scope filter memakai `AuthorizationService::accessibleSchoolIds()` | **RESOLVED** — `DashboardController::index()` kini membatasi `reportQuery`, `schoolQuery`, dan `pendingReports`. Sisa: `total_coaches` masih global. |

---

## MEDIUM

| ID | Module | Finding | Impact | Recommendation | Status |
|----|--------|---------|--------|----------------|--------|
| M-001 | Attendance / Finance | **Finance melihat tombol "Unduh PDF" padahal hanya punya `attendance.export_csv`** — UI menampilkan tombol PDF dan CSV sekaligus | Finance bisa mengunduh PDF di luar desain permission | Pisahkan tombol; di controller enforce `attendance.export` untuk PDF/Excel | **SUPERSEDED (2026-10-01)** — pemisahan tombol tetap berlaku dan `AttendanceController::export()` tetap menolak 403 untuk `format=excel/xlsx` dan `format=pdf` tanpa `attendance.export`. Yang berubah: review meeting LRS 2026-10-01 menetapkan Finance **memang** memerlukan CSV, Excel, dan PDF untuk pelaporannya, sehingga Finance kini memegang `attendance.export` (capability penuh) dan tombol PDF/Excel dirender untuknya. `attendance.export_csv` tidak lagi dipegang role mana pun. Lihat [modules/attendance.md](../modules/attendance.md). |
| M-002 | Seeder / Testing | **Seeder tidak menyertakan Teacher School role** | Coverage testing tidak lengkap untuk peran ini | Tambahkan user Teacher School dengan plotting sekolah | **RESOLVED** — seeder kini membuat `teacher@lrs.com` (Dewi Larasati) beserta plot sekolahnya. Lihat [development/seeder.md](../development/seeder.md). |
| M-003 | Attendance Export | **Export `get()` memuat seluruh dataset ke memory** | Server crash pada export data besar | Gunakan chunking atau batasi rentang tanggal | **RESOLVED** — matriks dibangun dengan `chunk(1000)`. |
| M-004 | Report / State | **Kolom `photo_path` masih ada di migration dan `$fillable`** padahal media sudah memakai `report_media` | Kebingungan developer; potensi inkonsistensi data | Buat migrasi untuk drop kolom; hapus dari `$fillable` | **RESOLVED** — migrasi `2026_09_11_000005_drop_photo_path_from_reports`. |
| M-005 | Attendance / Data | **`report_attendances` tanpa unique constraint `(report_id, student_id)`** | Potensi duplikat lewat manipulasi DB langsung atau request bersamaan | Tambahkan unique index | **RESOLVED** — migrasi `2026_09_11_000006_add_unique_report_student_to_report_attendances`. |
| M-006 | Coach Report / Validation | **Tidak ada re-validasi `class_id` saat update laporan** | Potensi inkonsistensi bila data sudah rusak lebih dulu | Pastikan `class_id` masih valid dan coach masih ditugaskan | **RESOLVED** — `assignedClassOrFail($report->class_id, $report->report_date)` dipanggil sebelum update, termasuk memeriksa penugasan sementara yang terikat tanggal sesi. |

---

## LOW

| ID | Module | Finding | Impact | Recommendation | Status |
|----|--------|---------|--------|----------------|--------|
| L-001 | UI / Dashboard | **Judul dashboard memakai string literal** `'superadmin'` alih-alih konstanta | Beban pemeliharaan bila nama peran berubah | Gunakan `User::ROLE_SUPERADMIN` | **RESOLVED** — literal sudah tidak ada di `admin/dashboard.blade.php`. |
| L-002 | UI / Attendance | **Empty state memakai gambar CDN eksternal** (`cdn-icons-png.flaticon.com`) | Bila CDN mati, ikon empty state hilang | Gunakan Bootstrap Icon atau inline SVG | **RESOLVED (2026-09-28)** — gambar eksternal diganti Bootstrap Icons di **8 view**: `admin/master/classes`, `coaches`, `coach_show`, `programs`, `schools`, `admin/users/index`, `coach/reports/index`, `school_pic/dashboard`. Ukuran 64px, opacity, spacing, dan perataan dipertahankan. `git grep` untuk URL gambar eksternal di `resources/views` → 0 hasil. |
| L-003 | UI / Report Show | **`media()` vs `photos`/`videos` tidak konsisten** — memuat relasi terpisah sehingga menambah query | N+1 minor pada halaman detail | Muat `media` sekali lalu filter di view | **RESOLVED** — `show()` memuat `['coach','school','schoolClass','attendances.student','media','attendanceMedia']` dalam satu pemanggilan. |
| L-004 | Code / Legacy | **`CloudinaryHelper.php` masih ada** meski tidak dipakai | Code debt | Hapus atau pindahkan | **RESOLVED** — berkas tidak lagi ada di `app/`. |
| L-005 | Docker | **Dockerfile PHP 8.3 vs Composer `^8.4`** | Deployment gagal bila memakai Docker | Update Dockerfile | **N/A** — `Dockerfile` dihapus (2026-09-11); deployment bukan berbasis Docker. |
| L-006 | UI / Report Download | **Download laporan memakai `Content-Disposition: inline`** | Tidak ada — ini alur pratinjau/review yang disengaja | — | **ACCEPTED / BY DESIGN (2026-09-28)** — laporan disajikan sebagai halaman HTML pratinjau (`Content-Type: text/html`) di `Admin\ReportController`, `Coach\ReportController`, dan `MediaController`; pengguna melihat & me-review lebih dulu lalu mencetak (Print / Save as PDF). Mengubah ke `attachment` akan menghilangkan alur itu. **Bukan bug; dipertahankan.** |
| L-007 | Seeder / Data | **Seeder hanya membuat satu sekolah** sehingga cross-school isolation sulit diuji manual | Testing manual butuh setup tambahan | Tambahkan sekolah kedua dengan PIC-nya | **RESOLVED** — seeder kini membuat 10 sekolah beserta kelas, program, murid, dan plot PIC/Teacher. |
| L-008 | UI / Form | **Field konfirmasi password kurang jelas bagi pengguna non-teknis** | UX friction | Tambahkan helper text | **RESOLVED** — placeholder kini berbunyi "Ulangi password baru" / "Ulangi password". |

---

## Fixed During Audit (2026-09-11)

Tidak ada bug yang diperbaiki selama audit tersebut. Semua finding memerlukan keputusan bisnis atau perubahan yang lebih dari sekadar safe fix.

---

## Remaining Issues (per 2026-09-28)

### HIGH
Tidak ada. Keduanya selesai.

**Tidak ada lagi tindakan yang menggantung di luar repositori.** Rotasi kredensial Cloudinary sudah ditutup: kredensial lama sudah dihapus/diinvalidasi di sisi penyedia (2026-09-28). Audit dan integrasi Cloudinary **CLOSED** — lihat [HIGH-PRIORITY.md](./HIGH-PRIORITY.md#h-001-cloudinary-credentials-committed-to-env).

### MEDIUM
Tidak ada.

### LOW
Tidak ada.

Semua isu LOW sudah tertutup: L-001, L-003, L-004, L-007, L-008 **RESOLVED**; L-002 **RESOLVED (2026-09-28)**; L-005 **N/A** (Docker dihapus); L-006 **ACCEPTED / BY DESIGN** (pratinjau laporan, bukan bug).

### Sisa kecil dari H-002
`total_coaches` pada dashboard masih dihitung global. Dampak nihil saat ini (hanya peran global yang punya `dashboard.view`), tetapi belum ada test yang menutupinya.

---

## Tested Roles (2026-09-11)

| # | Role | Login Test | Route Test | Permission Test | School Scope Test | Notes |
|---|------|-----------|------------|-----------------|-------------------|-------|
| 1 | **SuperAdmin** | ✅ PASS | ✅ PASS | ✅ Wildcard access | ✅ Global | Landing: `/admin/dashboard` |
| 2 | **Relation** | ✅ PASS | ✅ PASS | ✅ All operational | ✅ Global | Landing: `/admin/schools` |
| 3 | **SPV Coach** | ✅ PASS | ✅ PASS | ✅ Coach mgmt + view reports | ✅ Global (operational) | Landing: `/admin/coaches` |
| 4 | **Coach** | ✅ PASS | ✅ PASS | ✅ Own reports + assigned classes | ✅ Assignment-based | Landing: `/coach/reports` |
| 5 | **PIC DK School** | ✅ PASS | ✅ PASS | ✅ Plotted school + approved only | ✅ Plotted schools | Landing: `/pic/dashboard` |
| 6 | **Teacher School** | ✅ PASS | ✅ PASS | ✅ Attendance + reports (approved) | ✅ Plotted schools | Landing: `/attendance` |
| 7 | **Finance** | ✅ PASS | ✅ PASS | ✅ Attendance + CSV export only | ✅ All-school (dari role) | Landing: `/attendance` |

> Catatan koreksi: baris Finance pada laporan asli menyebut scope "Plotted schools". Itu **tidak tepat** — Finance ber-scope **all-school** yang berasal dari perannya, bukan dari plot sekolah, dan tidak diplot di seeder. Lihat [reference/permissions.md](../reference/permissions.md).

---

## Tested Modules (2026-09-11)

| Module | Status | Notes |
|--------|--------|-------|
| Login/Logout | ✅ PASS | Session regeneration, CSRF, redirect by role |
| Dashboard | ⚠️ → ✅ | H-002 sudah diperbaiki 2026-09-28 |
| School Management | ✅ PASS | CRUD + cascade protection |
| Class Management | ✅ PASS | CRUD + cascade protection |
| Program Management | ✅ PASS | CRUD + class association |
| Student Management | ✅ PASS | CRUD + import + template |
| Coach Management | ✅ PASS | CRUD + assignment/unassignment |
| User Management | ✅ PASS | All 7 roles, school plotting |
| Coach Report Create | ✅ PASS | Validation, transaction, attendance |
| Coach Report Edit | ✅ PASS | Status check, media delete, resubmit |
| Coach Report Submit | ✅ PASS | Status → submitted |
| Report Review | ✅ PASS | Relation/SuperAdmin only |
| Report Approve | ✅ PASS | Status check, approved_by, approved_at |
| Report Reject | ✅ PASS | admin_notes required, status → rejected |
| Report Resubmit | ✅ PASS | Edit → resubmit clears admin_notes |
| Attendance View | ✅ PASS | Scope per role, filtering |
| Attendance Export | ⚠️ → ✅ | M-001 dan M-003 sudah diperbaiki |
| Report Download | ✅ PASS | Approved only, school scope; cabang coach memakai `canAccessReport()` (per sesi) sejak 2026-09-29 — unauthorized coach → 403 |
| Media Upload | ✅ PASS | Photo/video, size limits |
| Media View | ✅ PASS | Authorized serving, role-based; unduhan lewat `?download=1` pada rute yang sama |
| Media Delete | ✅ PASS | Disk cleanup, transaction safety |
| Coach Report Detail | ✅ PASS | `coach.reports.show`, materi dari `reports.lesson_material`, batas per sesi |
| Accident Notes | ✅ PASS | Menu pribadi coach (`coach.accident-notes.index`), bukan notification center; catatan milik coach sendiri + tautan laporan sumber |

**Modul yang belum ada pada saat audit dan kini tersedia:** Jadwal Mengajar (pola + sesi), PWA, Web Push, Activity Log, School Workspace. Lihat [docs/README.md](../README.md).

---

## Test Results

### Kondisi saat audit (2026-09-11)
```
Tests:    114 passed (423 assertions)
Duration: 21.25s
```

### Kondisi terkini (dijalankan ulang 2026-09-29)
```
Tests:    7 skipped, 503 passed (2538 assertions)
Duration: 66.42s
```

Perubahan 2026-09-29 yang diverifikasi suite ini: penutupan blocker authorization coach di `admin.reports.download`, kolom "Materi yang Dipelajari" di riwayat laporan coach, halaman detail laporan coach, tautan "Lihat Video" di PDF, unduhan foto/video terotorisasi, dan pemindahan Accident Notes ke menu pribadi coach. **Tidak ada migrasi baru.** Tidak ada test yang gagal.

Satu-satunya sumber skip adalah `AttendanceMysqlOnlyFullGroupByTest`, yang memerlukan variabel `TEST_MYSQL_*`. Suite memakai SQLite `:memory:` + `QUEUE_CONNECTION=sync`.

Test JavaScript terpisah: `npm run test:pwa` → 37 pemeriksaan service worker.

### Security Test Results (Code Inspection, 2026-09-11)

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

### Data Integrity Results (Code Inspection, 2026-09-11)

| Check | Result |
|-------|--------|
| Foreign key constraints | ✅ All FKs defined in migrations |
| Orphan record prevention | ✅ CASCADE on report children, RESTRICT on report parents |
| Report state machine | ✅ Only `submitted` can be approved/rejected |
| Coach assignment validation | ✅ `assignedClassOrFail()` checks backend |
| Student-class relationship | ✅ `assertAttendanceBelongsToClass()` validates |
| Transaction atomicity | ✅ Report+media+attendance in DB::transaction |
| Duplicate attendance | ✅ Fixed — unique index `(report_id, student_id)` |

---

## Final Recommendation

### Prioritas 1: SEGERA (HIGH)
1. ~~H-001: Rotasi Cloudinary credentials~~ → **Selesai 2026-09-28** — kredensial lama sudah dihapus/diinvalidasi di sisi penyedia; integrasi Cloudinary CLOSED.
2. ~~H-002: Tambahkan school scope di DashboardController~~ → **Selesai**.

### Prioritas 2: SPRINT BERIKUTNYA (MEDIUM)
3. ~~M-001: Pisahkan tombol export CSV/PDF~~ → **Selesai**
4. ~~M-005: Unique index `(report_id, student_id)`~~ → **Selesai**
5. ~~M-002: Teacher School di seeder~~ → **Selesai**
6. ~~M-003: Chunking pada attendance export~~ → **Selesai**
7. ~~M-004: Drop kolom `photo_path`~~ → **Selesai**
8. ~~M-006: Re-validasi class assignment saat update~~ → **Selesai**

### Prioritas 3: BACKLOG (LOW)
9. ~~L-002: ganti gambar CDN eksternal pada empty state dengan ikon lokal/inline~~ → **Selesai 2026-09-28** (8 view, Bootstrap Icons)
10. ~~L-006: putuskan apakah unduhan laporan sebaiknya `attachment`~~ → **Diputuskan 2026-09-28: `inline` DIPERTAHANKAN** (pratinjau/review laporan, bukan bug). Status: ACCEPTED / BY DESIGN.
11. **Sisa H-002** — scope-kan `total_coaches` dan tambahkan test dashboard ber-scope.

---

*Laporan asli dihasilkan dari code inspection, analisis schema database, dan 114 automated test (2026-09-11). Status diperbarui 2026-09-28 terhadap kode terkini.*
