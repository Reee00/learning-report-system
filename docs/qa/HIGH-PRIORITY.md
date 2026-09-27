# HIGH PRIORITY ISSUES — LRS QA Audit

Semua issue di bawah ini bersifat HIGH priority dan harus ditangani **segera** sebelum deployment atau dalam sprint yang sedang berjalan.

---

## H-001: Cloudinary Credentials Committed to `.env`

**Issue:** API key dan secret Cloudinary terekspos dalam file `.env` yang ter-commit ke repository.

**Cause:** File `.env` berisi credentials sensitif (line 66-68):
```
CLOUDINARY_CLOUD_NAME=mediaflows_2f82a1be-b564-4a21-b7aa-25a2bbbd41fe
CLOUDINARY_API_KEY=824718461724624
CLOUDINARY_API_SECRET=q9QJvwn5W--WctGuRb7VB8ja1ck
```

**Impact:** 
- Siapa pun dengan akses read ke repository dapat membaca dan menyalahgunakan Cloudinary account
- Risiko resource abuse (upload file berbahaya, bandwidth drain)
- Compliance violation (credential management best practice)

**Expected:** Credentials tidak boleh ter-commit ke version control. Harus ada di environment variable runtime atau secret manager.

**Actual:** Credentials plaintext ada di `.env` yang ada di repository.

**Recommended Fix:**
1. **Segera rotasi** API key dan secret di Cloudinary Dashboard
2. Hapus credential values dari `.env` committed:
   ```env
   CLOUDINARY_CLOUD_NAME=
   CLOUDINARY_API_KEY=
   CLOUDINARY_API_SECRET=
   ```
3. Tambahkan `.env` ke `.gitignore` (jika belum)
4. Gunakan `.env.example` untuk template tanpa nilai:
   ```env
   CLOUDINARY_CLOUD_NAME=your_cloud_name_here
   CLOUDINARY_API_KEY=your_api_key_here
   CLOUDINARY_API_SECRET=your_api_secret_here
   ```
5. Jika Cloudinary tidak lagi aktif digunakan, pertimbangkan untuk menghapus config sepenuhnya

**Verification:**
- Cek `.env` tidak lagi mengandung credential values
- Cek `.gitignore` mencakup `.env`
- Konfirmasi Cloudinary API key lama sudah tidak valid
- Run `git log --all -p -- .env` untuk memastikan history juga di-audit

---

## H-002: DashboardController Tidak Menerapkan School Scope

**Issue:** Dashboard statistics (`total_reports`, `submitted_reports`, `approved_reports`, `rejected_reports`) menghitung data dari **semua sekolah** tanpa memfilter berdasarkan school scope user yang sedang login.

**Cause:** `DashboardController::index()` (file: `app/Http/Controllers/Admin/DashboardController.php` line 13-27) menggunakan query global:
```php
'total_reports'     => Report::count(),
'submitted_reports' => Report::where('status', 'submitted')->count(),
'approved_reports'  => Report::where('status', 'approved')->count(),
'rejected_reports'  => Report::where('status', 'rejected')->count(),
'total_schools'     => School::count(),
'total_coaches'     => User::where('role', 'coach')->count(),
```

Tidak ada `whereIn('school_id', $accessibleSchoolIds)` yang diterapkan.

**Impact:**
- **Saat ini:** Risk rendah karena hanya `superadmin` dan `spv_coach` yang memiliki `dashboard.view` capability, dan keduanya memiliki global scope
- **Potensial:** Jika di masa depan role lain (seperti PIC atau Teacher) diberi akses dashboard, mereka akan melihat statistik dari SEMUA sekolah
- `pendingReports` query (line 23-27) juga tidak di-scope, sehingga bisa menampilkan report dari sekolah yang bukan scope user

**Expected:** Dashboard statistics harus di-filter berdasarkan `AuthorizationService::accessibleSchoolIds()` sehingga setiap role hanya melihat data yang berada dalam scope-nya.

**Actual:** Semua query mengembalikan data global tanpa filtering.

**Recommended Fix:**
```php
public function index()
{
    $user = auth()->user();
    $schoolIds = app(AuthorizationService::class)->accessibleSchoolIds($user);
    
    $baseQuery = Report::query();
    $schoolQuery = School::query();
    
    if ($schoolIds !== null) {
        $baseQuery->whereIn('school_id', $schoolIds);
        $schoolQuery->whereIn('id', $schoolIds);
    }
    
    $stats = [
        'total_reports'     => (clone $baseQuery)->count(),
        'submitted_reports' => (clone $baseQuery)->where('status', 'submitted')->count(),
        'approved_reports'  => (clone $baseQuery)->where('status', 'approved')->count(),
        'rejected_reports'  => (clone $baseQuery)->where('status', 'rejected')->count(),
        'total_schools'     => $schoolQuery->count(),
        'total_coaches'     => User::where('role', 'coach')->count(),
    ];

    $pendingReports = Report::with(['coach', 'school', 'schoolClass'])
        ->where('status', 'submitted')
        ->when($schoolIds !== null, fn($q) => $q->whereIn('school_id', $schoolIds))
        ->latest()
        ->take(5)
        ->get();

    return view('admin.dashboard', compact('stats', 'pendingReports'));
}
```

**Verification:**
- Login sebagai user dengan school scope terbatas
- Verifikasi dashboard hanya menampilkan data dari sekolah yang di-plot
- Run `php artisan test` untuk regresi
- Tambahkan unit test baru untuk memverifikasi scope dashboard

---

*File ini adalah subset dari [QA-AUDIT-REPORT.md](./QA-AUDIT-REPORT.md) — hanya berisi issue dengan severity HIGH.*
