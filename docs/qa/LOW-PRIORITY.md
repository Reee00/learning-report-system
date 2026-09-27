# LOW PRIORITY ISSUES — LRS QA Audit

Semua issue di bawah ini bersifat LOW priority dan dapat ditangani sebagai backlog improvement.

---

## L-001: Dashboard Title Hardcodes Role Check

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

**Verification:** Verifikasi empty state masih terlihat baik tanpa koneksi internet.

---

## L-003: Report Show View Memiliki Extra Queries untuk Photos/Videos

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
