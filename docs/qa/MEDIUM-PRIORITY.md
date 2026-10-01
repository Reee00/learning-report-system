# MEDIUM PRIORITY ISSUES — LRS QA Audit

**Status diperbarui: 2026-10-01.** Dokumen ini adalah subset dari [QA-AUDIT-REPORT.md](./QA-AUDIT-REPORT.md) — hanya berisi isu dengan severity MEDIUM.

> Isu dan analisis di bawah dipertahankan sebagai catatan audit 2026-09-11. Setiap isu kini punya baris **Status** yang mencerminkan kondisi kode saat ini.

| ID | Judul | Status |
|---|---|---|
| M-001 | Finance melihat tombol PDF | **SUPERSEDED** (2026-10-01) |
| M-002 | Seeder tanpa Teacher School | **RESOLVED** |
| M-003 | Export memuat seluruh dataset | **RESOLVED** |
| M-004 | Kolom `photo_path` legacy | **RESOLVED** |
| M-005 | Tanpa unique constraint kehadiran | **RESOLVED** |
| M-006 | Tanpa re-validasi class assignment | **RESOLVED** |

Tidak ada isu MEDIUM yang masih terbuka. M-001 bukan lagi isu terbuka, melainkan aturan yang **digantikan** requirement baru.

---

## M-001: Finance Melihat Tombol PDF Export Padahal Hanya Memiliki CSV Capability

**Status: SUPERSEDED (2026-10-01).**

**Perbaikan terverifikasi (masih berlaku):**
- `resources/views/attendance/class.blade.php` memisahkan `$canExportPdf` (hanya `attendance.export`) dari `$canExportCsv` (`export` **atau** `export_csv`). Tombol Excel dan PDF dibungkus `@if($canExportPdf)`; tombol CSV dibungkus `@if($canExportCsv)`.
- `AttendanceController::export()` menegakkan permission per format: `format=excel|xlsx` dan `format=pdf` menolak 403 tanpa `attendance.export`. Format CSV tetap menerima salah satu capability.

**Catatan:** Perilaku yang berlaku kini adalah **PDF dan Excel setara** — keduanya butuh `attendance.export`. Tombol pada view memang bernama "Unduh Excel", bukan "Unduh PDF" seperti pada judul isu asli.

**Yang digantikan (2026-10-01):** premis isu ini — bahwa Finance hanya boleh CSV — tidak lagi berlaku. Review meeting LRS 2026-10-01 menetapkan Finance memerlukan **CSV, Excel, dan PDF** untuk pelaporan kehadiran lintas sekolah, jadi Finance kini memegang `attendance.export` (capability penuh) dan ketiga tombol dirender untuknya. Pengaman teknisnya tidak dilepas: pemisahan tombol dan penegakan per format di controller tetap ada, sehingga role yang hanya memegang `attendance.export_csv` masih ditolak 403 untuk Excel/PDF. Capability sempit itu kini tidak dipegang role mana pun. Lihat [modules/attendance.md](../modules/attendance.md).

**Issue:** Finance role menampilkan tombol "Unduh PDF" dan "Unduh CSV" keduanya, padahal Finance hanya memiliki capability `attendance.export_csv` (bukan `attendance.export`).

**Cause:**  
1. **View** (`attendance/index.blade.php` line 8-9): `$canExport` mengecek `export OR export_csv` — jika salah satu true, KEDUA tombol ditampilkan:
   ```php
   $canExport = $authorization->allows($currentUser, 'attendance.export')
       || $authorization->allows($currentUser, 'attendance.export_csv');
   ```
2. **Controller** (`AttendanceController::export()` line 38-43): Backend juga mengecek `export OR export_csv` tanpa membedakan format yang diminta:
   ```php
   abort_unless(
       $this->authorization->allows($user, 'attendance.export')
           || $this->authorization->allows($user, 'attendance.export_csv'),
       403
   );
   ```
3. **Permission** (`AuthorizationService`): Finance hanya punya `attendance.export_csv`, bukan `attendance.export`.

**Impact:** Finance dapat mengunduh PDF attendance, yang seharusnya di luar scope capability-nya berdasarkan desain permission.

**Expected:** Finance hanya boleh melihat tombol CSV dan hanya bisa export CSV. PDF hanya untuk role yang memiliki `attendance.export`.

**Actual:** Finance melihat kedua tombol dan bisa download keduanya.

**Recommended Fix:**
1. **View** — pisahkan tombol:
   ```blade
   @php
       $canExportFull = $authorization->allows($currentUser, 'attendance.export');
       $canExportCsv = $canExportFull || $authorization->allows($currentUser, 'attendance.export_csv');
   @endphp
   @if($canExportCsv)
       <a href="...csv">Unduh CSV</a>
   @endif
   @if($canExportFull)
       <a href="...pdf">Unduh PDF</a>
   @endif
   ```
2. **Controller** — enforce format-specific permission:
   ```php
   if ($request->query('format') === 'pdf') {
       abort_unless($this->authorization->allows($user, 'attendance.export'), 403);
       return $this->attendanceExport->downloadPdf(...);
   }
   // CSV: attendance.export OR attendance.export_csv
   ```

**Verification:**
- Login sebagai Finance → hanya tombol CSV yang terlihat
- Akses URL PDF langsung → 403
- Login sebagai Relation → kedua tombol terlihat
- Run existing CrossSchoolSecurityTest

---

## M-002: DatabaseSeeder Tidak Menyertakan Teacher School Role

**Status: RESOLVED.**

**Perbaikan terverifikasi:** seeder kini membuat akun `teacher@lrs.com` (Dewi Larasati) dengan role `teacher_school` beserta plot sekolahnya. Seeder juga membuat 10 sekolah DIGISCHOOL, bukan satu. Lihat [development/seeder.md](../development/seeder.md).

**Issue:** Seeder hanya membuat 6 dari 7 role. Teacher School tidak ada.

**Cause:** `DatabaseSeeder.php` membuat: SuperAdmin, Relation, SPV Coach, Coach, School PIC, Finance. Teacher School terlewat.

**Impact:** Developer/QA tidak bisa menguji Teacher School role tanpa manual insert. Role ini juga tidak diuji saat `php artisan migrate:fresh --seed`.

**Expected:** Semua 7 canonical roles harus ada di seeder.

**Actual:** Teacher School absent.

**Recommended Fix:**
```php
// Tambahkan setelah Finance block di DatabaseSeeder.php
$teacher = User::updateOrCreate(
    ['email' => 'teacher@lrs.com'],
    [
        'name'      => 'Dewi Teacher',
        'password'  => bcrypt('password'),
        'role'      => User::ROLE_TEACHER_SCHOOL,
        'school_id' => $school->id,
    ]
);
$teacher->schools()->sync([$school->id]);
```

**Verification:**
- `php artisan migrate:fresh --seed`
- Login sebagai `teacher@lrs.com` / `password`
- Verifikasi landing page `/attendance`
- Verifikasi sidebar hanya menampilkan Laporan Coach + Kehadiran

---

## M-003: Attendance Export Memuat Seluruh Dataset ke Memory

**Status: RESOLVED** — matriks dibangun dengan `chunk(1000)`.

**Issue:** `AttendanceExportService::getMatrixData()` menggunakan `$query->get()` yang memuat semua attendance records ke memory sekaligus.

**Cause:** `AttendanceExportService.php` line 12:
```php
$records = $query->with(['report.school', 'report.schoolClass', 'student'])->get();
```

**Impact:** Untuk dataset besar (mis. 50 sekolah × 20 kelas × 30 siswa × 200 hari = 6 juta records), PHP akan kehabisan memory (`Allowed memory size exhausted`).

**Expected:** Export harus menggunakan streaming/chunking sehingga memory usage konstan.

**Actual:** Seluruh dataset dimuat ke array PHP sekaligus.

**Recommended Fix:**
Opsi 1 — Batasi rentang tanggal (quick fix):
```php
// Di AttendanceController::export(), validasi max date range
$validated = $request->validate([
    ...
    'date_from' => ['required', 'date'],  // mandatory untuk export
    'date_to'   => ['required', 'date', 'after_or_equal:date_from'],
]);
// Enforce max 90 hari
$daysDiff = Carbon::parse($validated['date_from'])->diffInDays($validated['date_to']);
abort_if($daysDiff > 90, 422, 'Rentang tanggal maksimal 90 hari untuk export.');
```

Opsi 2 — Chunking (proper fix):
```php
$query->with([...])->chunk(1000, function ($records) use (&$matrix, &$dates) {
    // Build matrix incrementally
});
```

**Verification:**
- Export dengan filter tanggal 1 tahun penuh
- Monitor memory usage via `memory_get_peak_usage()`
- Verifikasi CSV output masih correct

---

## M-004: Kolom `photo_path` Legacy Masih Ada di Reports

**Status: RESOLVED** — kolom di-drop oleh migrasi `2026_09_11_000005_drop_photo_path_from_reports` dan sudah tidak ada di `Report::$fillable`.

**Issue:** Kolom `photo_path` di tabel `reports` dan di `Report::$fillable` masih ada, padahal media sudah menggunakan `report_media` table.

**Cause:**
- Migration `create_reports_table.php` line 18: `$table->string('photo_path', 255)->nullable()`
- Model `Report.php` line 11: `$fillable` mencantumkan `photo_path`

**Impact:** 
- Developer baru mungkin bingung apakah harus menggunakan `photo_path` atau `report_media`
- Potensi old code path yang masih menulis ke kolom ini

**Expected:** Kolom legacy yang tidak digunakan harus dihapus.

**Actual:** Kolom dan fillable masih ada.

**Recommended Fix:**
1. Buat migration baru:
   ```php
   Schema::table('reports', function (Blueprint $table) {
       $table->dropColumn('photo_path');
   });
   ```
2. Hapus `photo_path` dari `Report::$fillable`

**Verification:**
- `php artisan migrate`
- Semua test masih pass
- Coach report create/edit masih bekerja

---

## M-005: Tidak Ada Unique Constraint pada `report_attendances (report_id, student_id)`

**Status: RESOLVED** — migrasi `2026_09_11_000006_add_unique_report_student_to_report_attendances`, diterapkan setelah deduplikasi dan aman untuk rollback MySQL.

**Issue:** Tabel `report_attendances` tidak memiliki unique constraint pada kombinasi `(report_id, student_id)`, sehingga secara teknis bisa terjadi duplikat.

**Cause:** Migration `create_report_attendances_table.php` hanya mendefinisikan kolom dan FK, tanpa unique index.

**Impact:** 
- `syncAttendance()` melakukan `delete()` + `insert()` yang aman secara normal
- Namun race condition (2 request concurrent) bisa menyebabkan duplikat
- Data analysis/export bisa menghasilkan angka yang salah

**Expected:** Database harus memiliki unique constraint `(report_id, student_id)`.

**Actual:** Tidak ada unique constraint.

**Recommended Fix:**
```php
// Migration baru
Schema::table('report_attendances', function (Blueprint $table) {
    $table->unique(['report_id', 'student_id']);
});
```

**Verification:**
- `php artisan migrate`
- Coba insert duplikat manual → harus error
- Semua test masih pass

---

## M-006: Tidak Ada Re-validation Class Assignment Saat Report Update

**Status: RESOLVED.**

**Perbaikan terverifikasi** di `app/Http/Controllers/Coach/ReportController.php`:

```php
// QA M-006: assignment bisa berubah sejak laporan dibuat — pastikan coach
// masih ditugaskan ke kelas laporan ini sebelum resubmit.
$this->assignedClassOrFail((int) $report->class_id, $report->report_date->toDateString());
```

Perhatikan parameter kedua: validasi memakai **tanggal sesi laporan**, sehingga penugasan sementara yang terikat rentang tanggal ikut diperhitungkan dengan benar.

**Issue:** Saat Coach mengedit dan resubmit report yang di-reject, controller tidak mengecek apakah Coach masih assigned ke class tersebut.

**Cause:** `Coach\ReportController::update()` line 192-210 hanya mengecek `coach_id` dan `status`, tidak mengecek ulang assignment ke `class_id`.

**Impact:** 
- Jika SPV Coach sudah menghapus assignment Coach dari kelas tersebut antara saat reject dan resubmit, Coach masih bisa resubmit
- Edge case yang jarang terjadi tapi merupakan authorization gap

**Expected:** Setiap report submission harus memverifikasi bahwa Coach masih assigned ke kelas tersebut.

**Actual:** Assignment tidak divalidasi ulang saat update/resubmit.

**Recommended Fix:**
```php
public function update(Request $request, Report $report)
{
    abort_if($report->coach_id !== Auth::id(), 403);
    abort_if(!in_array($report->status, ['draft', 'rejected']), 403);
    
    // Tambahkan: validasi assignment masih aktif
    $this->assignedClassOrFail($report->class_id);
    
    // ... rest of update logic
}
```

**Verification:**
- Buat report, submit, reject
- Hapus assignment Coach dari kelas
- Coba resubmit → harus 403
- Kembalikan assignment → resubmit berhasil

---

*File ini adalah subset dari [QA-AUDIT-REPORT.md](./QA-AUDIT-REPORT.md) — hanya berisi issue dengan severity MEDIUM.*
