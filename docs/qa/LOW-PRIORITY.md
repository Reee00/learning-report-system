# LOW PRIORITY ISSUES — LRS QA Audit

**Status diperbarui: 2026-09-28.** Dokumen ini adalah subset dari [QA-AUDIT-REPORT.md](./QA-AUDIT-REPORT.md) — hanya berisi isu dengan severity LOW.

> Isu dan analisis di bawah dipertahankan sebagai catatan audit 2026-09-11. Setiap isu kini punya baris **Status** yang mencerminkan kondisi kode saat ini.

| ID | Judul | Status |
|---|---|---|
| L-001 | Dashboard title hardcode role | **RESOLVED** |
| L-002 | Empty state pakai CDN eksternal | **RESOLVED** — diganti Bootstrap Icons di 8 view (2026-09-28) |
| L-003 | Extra query photos/videos | **RESOLVED** |
| L-004 | `CloudinaryHelper.php` masih ada | **RESOLVED** |
| L-005 | Dockerfile PHP 8.3 vs `^8.4` | **N/A** — Docker dihapus |
| L-006 | `Content-Disposition: inline` | **ACCEPTED / BY DESIGN** — pratinjau laporan, bukan bug |
| L-007 | Seeder tanpa sekolah kedua | **RESOLVED** |
| L-008 | Helper text konfirmasi password | **RESOLVED** |

**Sisa backlog:** tidak ada. L-002 sudah diperbaiki; L-006 diterima sebagai keputusan desain (bukan bug).

---

## L-001: Dashboard Title Hardcodes Role Check

**Status: RESOLVED** — literal `'superadmin'` sudah tidak ada di `resources/views/admin/dashboard.blade.php`.

**Issue:** Dashboard view menggunakan string literal `'superadmin'` alih-alih constant `User::ROLE_SUPERADMIN`.

**Cause:** `dashboard.blade.php` line 2:
```blade
@section('title', auth()->user()->role === 'superadmin' ? 'SuperAdmin Dashboard' : 'Relation Dashboard')
```

**Impact:** Maintenance burden — jika role name berubah, string literal harus di-update manual.

**Expected:** Gunakan `App\Models\User::ROLE_SUPERADMIN` constant.

**Actual:** String literal `'superadmin'`.

**Recommended Fix:**
```blade
@section('title', auth()->user()->role === \App\Models\User::ROLE_SUPERADMIN ? 'SuperAdmin Dashboard' : 'Dashboard')
```

**Verification:** Dashboard title masih menampilkan text yang benar setelah perubahan.

---

## L-002: Empty State Menggunakan External Image CDN

**Status: RESOLVED — 2026-09-28.** Tidak ada lagi referensi gambar eksternal di `resources/views`.

Temuan awal hanya menyebut `attendance/index.blade.php`. Per 2026-09-28 gambar `cdn-icons-png.flaticon.com` dipakai di **8 view**:

`admin/master/classes.blade.php`, `admin/master/coaches.blade.php`, `admin/master/coach_show.blade.php`, `admin/master/programs.blade.php`, `admin/master/schools.blade.php`, `admin/users/index.blade.php`, `coach/reports/index.blade.php`, `school_pic/dashboard.blade.php`.

Sementara `attendance/index.blade.php` — lokasi asli temuan — sudah tidak memakainya lagi.

**Issue:** Attendance empty state menggunakan gambar dari `cdn-icons-png.flaticon.com`.

**Cause:** `attendance/index.blade.php` line 199:
```html
<img src="https://cdn-icons-png.flaticon.com/512/7486/7486744.png" alt="No Data" width="64">
```

**Impact:** Jika CDN Flaticon down atau rate-limited, empty state kehilangan visual icon. Juga menambahkan external dependency yang tidak perlu.

**Expected:** Gunakan asset lokal atau Bootstrap Icons yang sudah di-load.

**Actual:** External CDN dependency.

**Recommended Fix:**
```html
<i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
```

**Resolusi (2026-09-28):** Kedelapan view diganti dengan Bootstrap Icons — ikon dipilih mengikuti ikon identitas halaman masing-masing (`bi-journal-bookmark` untuk kelas, `bi-people` untuk coach/akun, `bi-book-half` untuk program, `bi-building` untuk sekolah, `bi-inbox` untuk daftar laporan). Ukuran 64px dipertahankan persis (`style="font-size: 4rem;"` + `lh-1`), `opacity-50 mb-3`, perataan tengah, dan `d-block` mengikuti pola empty state yang sudah ada di `attendance/index.blade.php` dan `admin/reports/index.blade.php`. Ikon ditandai `aria-hidden="true"` karena bersifat dekoratif — pesannya dibawa `<h6>` di bawahnya.

**Verification:** `git grep -n -E 'https?://.*\.(png|jpg|jpeg|gif|webp|svg)' resources/views` → 0 hasil. `php artisan view:cache` sukses (semua Blade terkompilasi). Test suite: 459 passed / 7 skipped — identik dengan baseline sebelum perubahan.

---

## L-003: Report Show View Memiliki Extra Queries untuk Photos/Videos

**Status: RESOLVED** — `show()` memuat `['coach','school','schoolClass','attendances.student','media','attendanceMedia']` dalam satu pemanggilan, dan view memfilter dari koleksi yang sudah dimuat.

**Issue:** `AdminReportController::show()` memuat relation `media`, tapi view mengakses `$report->photos` dan `$report->videos` yang merupakan filtered relations terpisah — menghasilkan 2 extra queries.

**Cause:**  
- Controller line 65: `$report->load(['..., media'])`
- View line 74: `$report->photos->count()` — ini trigger query baru karena `photos()` adalah method yang return filtered relation
- View line 100: `$report->videos->count()` — sama

**Impact:** 2 extra database queries per halaman detail report. Tidak signifikan untuk single request, tapi tidak optimal.

**Expected:** Filter dari collection yang sudah di-load, bukan query baru.

**Actual:** 2 extra queries via relation methods.

**Recommended Fix:**
Di view, gunakan:
```blade
@php
    $photos = $report->media->where('type', 'photo');
    $videos = $report->media->where('type', 'video');
@endphp
{{-- Kemudian gunakan $photos dan $videos alih-alih $report->photos dan $report->videos --}}
```

**Verification:** Monitor query count (debugbar atau `DB::getQueryLog()`). Harus berkurang 2 queries.

---

## L-004: CloudinaryHelper.php Masih Ada di Codebase

**Status: RESOLVED** — tidak ada lagi berkas bernama `*cloudinary*` di bawah `app/`. Integrasi Cloudinary dihapus penuh pada 2026-09-11; media kini dilayani [MediaStorageService](../../app/Services/MediaStorageService.php) pada disk privat lokal.

**Issue:** File `app/Helpers/CloudinaryHelper.php` berisi raw cURL calls ke Cloudinary API masih ada, meskipun tidak digunakan oleh code path aktif manapun.

**Cause:** Legacy code dari sebelum migrasi ke local storage. Tidak pernah dihapus.

**Impact:** 
- Code debt — membingungkan developer baru
- File berisi reference ke Cloudinary API yang sudah tidak relevan
- Potential false positives di security scan

**Expected:** Legacy code yang tidak digunakan harus dihapus atau dipindahkan ke backup.

**Actual:** File masih ada di `app/Helpers/`.

**Recommended Fix:**
- Pindahkan ke `_backup_unused/CloudinaryHelper.php`, atau
- Hapus sepenuhnya jika tidak ada rencana untuk menggunakan Cloudinary lagi

**Verification:** `grep -r "CloudinaryHelper" app/` — tidak boleh ada usage selain import statement.

---

## L-005: Dockerfile PHP 8.3 vs Composer Require PHP 8.4

**Status: N/A — deployment bukan Docker-based; `Dockerfile` dihapus 2026-09-11.** Temuan di bawah ini dipertahankan sebagai catatan historis.

**Issue:** Dockerfile menggunakan PHP 8.3, tapi `composer.json` require `^8.4`.

**Cause:** Dockerfile belum di-update setelah PHP requirement dinaikkan ke 8.4.

**Impact:** Build Docker akan gagal pada `composer install` karena PHP version mismatch.

**Expected:** Dockerfile harus menggunakan PHP 8.4 sesuai `composer.json`.

**Actual:** `Dockerfile` line 1 menggunakan PHP 8.3.

**Recommended Fix:**
```dockerfile
FROM php:8.4-cli
# atau
FROM php:8.4-fpm
```

**Verification:** `docker build -t lrs .` berhasil tanpa error PHP version.

---

## L-006: Report Download Menggunakan Content-Disposition Inline

**Status: ACCEPTED / BY DESIGN — ditutup 2026-09-28. Bukan bug.**

Per 2026-09-28 header `inline` masih dipakai di `app/Http/Controllers/Admin/ReportController.php`, `app/Http/Controllers/Coach/ReportController.php`, dan `app/Http/Controllers/MediaController.php`.

Perilaku ini dipertahankan dengan sadar: laporan disajikan `inline` sebagai **halaman HTML pratinjau** (`Content-Type: text/html`) yang ditujukan untuk **dilihat dan di-review lebih dulu, lalu dicetak** lewat Print browser (termasuk Save as PDF). Mengubahnya menjadi `attachment` akan menghilangkan alur pratinjau/review tersebut. Keputusan akhir 2026-09-28: **dipertahankan apa adanya.** Bila kelak diputuskan berubah, ubah ketiga lokasi sekaligus.

**Issue:** Report download route mengirimkan HTML dengan `Content-Disposition: inline`, yang berarti browser menampilkan halaman HTML langsung alih-alih mendownload file.

**Cause:**  
- `AdminReportController::download()` line 148: `'Content-Disposition' => 'inline; filename="..."'`
- `CoachReportController::download()` line 307: sama

**Impact:** User yang mengharapkan file terdownload langsung mungkin bingung. Namun `inline` memungkinkan user untuk melihat dulu sebelum Print/Save As.

**Expected:** Ini mungkin intentional (print preview). Jika demikian, tambahkan tombol Print/Save di halaman.

**Actual:** HTML ditampilkan inline tanpa tombol print.

**Recommended Fix:**
Salah satu:
1. Ubah ke `attachment` untuk download langsung
2. Pertahankan `inline` tapi tambahkan tombol Print/Save di halaman download:
   ```html
   <button onclick="window.print()">Print</button>
   ```

**Verification:** Klik tombol download → perilaku sesuai expectation (download atau preview+print).

---

## L-007: Seeder Tidak Membuat School B untuk Cross-School Testing

**Status: RESOLVED** — seeder kini membuat 10 sekolah DIGISCHOOL, masing-masing dengan kelas, program, murid, dan plot PIC/Teacher. Cross-school isolation dapat diuji manual langsung setelah `migrate:fresh --seed`.

**Issue:** Seeder hanya membuat 1 sekolah (SD Harapan Bangsa). Tidak ada School B untuk menguji cross-school isolation secara manual.

**Cause:** `DatabaseSeeder.php` hanya membuat satu sekolah.

**Impact:** Manual testing untuk cross-school isolation memerlukan setup manual tambahan.

**Expected:** Minimal 2 sekolah dengan masing-masing PIC untuk testing.

**Actual:** Hanya 1 sekolah.

**Recommended Fix:**
```php
$schoolB = School::updateOrCreate(
    ['name' => 'SMP Nusantara Jaya'],
    ['address' => 'Jl. Asia Afrika No. 5, Bandung', 'pic_name' => 'Siti Rahmawati']
);

$picB = User::updateOrCreate(
    ['email' => 'picb@lrs.com'],
    [
        'name' => 'Siti Rahmawati',
        'password' => bcrypt('password'),
        'role' => User::ROLE_SCHOOL_PIC,
        'school_id' => $schoolB->id,
    ]
);
$picB->schools()->sync([$schoolB->id]);
```

**Verification:** `php artisan db:seed` → login sebagai PIC B → hanya melihat data SMP Nusantara Jaya.

---

## L-008: Password Confirmation Field Kurang Helper Text

**Status: RESOLVED** — field konfirmasi kini memakai placeholder "Ulangi password baru" (form create) dan "Ulangi password" (form edit) di `resources/views/admin/users/`.

**Issue:** Form create user di User Management membutuhkan `password_confirmation`, tapi mungkin tidak jelas bagi user non-IT bahwa harus mengetik ulang password yang sama.

**Cause:** Standard Laravel form pattern tanpa tambahan UX guidance.

**Impact:** UX friction minor — user non-teknis mungkin bingung atau frustasi.

**Expected:** Ada helper text atau placeholder yang menjelaskan.

**Actual:** Field tanpa context guidance.

**Recommended Fix:**
```html
<div class="form-text text-muted">Ketik ulang password yang sama untuk konfirmasi.</div>
```

**Verification:** Buka form create user → helper text terlihat di bawah field konfirmasi password.

---

*File ini adalah subset dari [QA-AUDIT-REPORT.md](./QA-AUDIT-REPORT.md) — hanya berisi issue dengan severity LOW.*
