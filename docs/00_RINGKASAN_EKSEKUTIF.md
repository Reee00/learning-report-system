# 📊 Ringkasan Eksekutif — Learning Report System

**Terakhir diperbarui:** 17 Agustus 2026
**Status:** Phase 0–12 selesai & stabil · Phase 13 (WaHa) belum diimplementasikan

---

## 1. Gambaran Umum Sistem

**Learning Report System (LRS)** adalah aplikasi web berbasis Laravel untuk mengelola laporan
pembelajaran di lingkungan Digikidz. Sistem menghubungkan enam peran operasional — dari SuperAdmin
sampai Finance — dalam satu alur: master data disiapkan, Coach mengajar dan melaporkan, laporan
direview, lalu absensi dibaca dan diekspor oleh pihak yang berkepentingan.

Perbedaan mendasar dari versi awal aplikasi: **otorisasi tidak lagi berbasis string role**, tetapi
berbasis **capability** (permission) terpusat. Satu peran adalah kumpulan capability; route dijaga oleh
capability, bukan oleh nama role.

## 2. Tujuan Sistem

| Tujuan | Bentuk konkret di aplikasi |
|---|---|
| Sentralisasi laporan pembelajaran | Coach input laporan + absensi per kelas per tanggal |
| Pemisahan tanggung jawab yang tegas | 6 role dengan capability berbeda; SuperAdmin satu-satunya pemegang user management |
| Isolasi data antar sekolah | School PIC & Finance hanya melihat sekolah yang di-plot untuknya |
| Kualitas laporan terkendali | Workflow review: `submitted` → `approved` / `rejected` dengan catatan |
| Data absensi dapat dipakai lintas fungsi | Halaman Attendance + export CSV streaming untuk PIC, Relation, SPV, Finance |
| Visibilitas insiden | Accident Notes ditampilkan sebagai blok merah urgent di detail laporan |

## 3. Enam Role dan Kewenangannya

| Role | Key | Landing setelah login | Kewenangan inti |
|---|---|---|---|
| SuperAdmin | `superadmin` | `admin.dashboard` | Wildcard — semua capability, termasuk User Management & review laporan |
| Relation | `relation` | `admin.schools.index` | Input master data: School, Student, Program Kelas, Program + lihat/ekspor absensi |
| SPV Coach | `spv_coach` | `admin.coaches.index` | Kelola akun Coach, assign/reassign kelas + lihat/ekspor absensi |
| Coach | `coach` | `coach.reports.index` | Input & edit laporan + absensi untuk kelas yang di-assign; lihat Accident Notes |
| School PIC | `school_pic` | `pic.dashboard` | Baca laporan **approved** dan absensi sekolah yang di-plot + export CSV |
| Finance | `finance` | `attendance.index` | Lihat absensi & export CSV sekolah yang di-plot |

Role `admin` versi lama **sudah tidak ada**. Seluruh akun `admin` dimigrasikan menjadi `relation`, dan
`superadmin` ditambahkan sebagai role akses penuh yang terpisah. URL dan nama route `admin.*` sengaja
dipertahankan sebagai *compatibility layer* — `admin` di situ adalah prefix URL, bukan nama role.

## 4. Modul Utama

| Modul | Pemilik capability | Route utama |
|---|---|---|
| Dashboard | `dashboard.view` | `admin.dashboard` |
| User Management & School Plotting | `users.manage` (SuperAdmin) | `admin.users.*` |
| School | `schools.view/create/update/delete` | `admin.schools.*` |
| Program Kelas (kelas sekolah) | `program_classes.view/create/delete` | `admin.classes.*` |
| Program (entity reusable) | `programs.view/create` | `admin.programs.*` |
| Student | `students.view/create/delete` | `students.*`, `/api/classes/{class}/students` |
| Coach & Assignment | `coaches.view/create/update/assign/reassign` | `admin.coaches.*` |
| Laporan (input) | `reports.view/create/update` | `coach.reports.*` |
| Laporan (review) | `reports.review` | `admin.reports.*` |
| Attendance & Export | `attendance.view`, `attendance.export`, `attendance.export_csv` | `attendance.index`, `attendance.export` |
| Accident Notes | `accident_notes.view` + tampil pada detail laporan | partial `partials/accident-notes` |
| Portal School PIC | `role:school_pic` + `attendance.view` | `pic.dashboard`, `pic.reports.show` |

## 5. Teknologi (terverifikasi 17 Agustus 2026)

| Komponen | Nilai aktual | Catatan |
|---|---|---|
| Framework | Laravel **12.66.0** | `php artisan about` |
| Bahasa | PHP **8.4.24** | `composer.json` mensyaratkan `^8.4` |
| Composer | 2.10.1 | — |
| Database | SQLite (lokal) | 13 migration, 13 tabel |
| Session driver | `file` | Tabel `sessions` ada tetapi driver default-nya file |
| Cache store | `database` | — |
| Queue | `sync` | Harus diubah sebelum Phase 13 |
| View | Blade | 20 file view |
| CSS/JS | **Bootstrap 5.3 via CDN** | **Tidak ada Vite pipeline**; `npm run build` = `echo 'No build step required'` |
| Media storage | Cloudinary via `CloudinaryHelper` | Non-fungsional saat ini — ISSUE-014 |
| Import/Export | `rap2hpoutre/fast-excel` (import siswa), CSV streaming manual (export absensi) | — |
| Testing | PHPUnit 11.5, SQLite `:memory:` + `RefreshDatabase` | 62 test / 301 assertion |

## 6. Struktur Otorisasi Singkat

```text
Request
  ↓  middleware auth
  ↓  middleware permission:<capability>            (PermissionMiddleware)
     atau permission_any:<a>,<b>                   (PermissionAnyMiddleware)
     atau role:<x>,<y>                             (RoleMiddleware)
  ↓  Controller — ensurePermission() / abort_unless() diulang di boundary
  ↓  AuthorizationService::accessibleSchoolIds()
        null  = operasional global (superadmin, relation, spv_coach)
        array = dibatasi           (school_pic, finance)
  ↓  Query Eloquent yang sudah ter-scope
  ↓  Response / CSV
```

Tiga service menopang ini:

| Service | Tanggung jawab |
|---|---|
| `AuthorizationService` | Satu-satunya peta role → permission; `allows()`, `accessibleSchoolIds()`, `canAccessClass()`, `canAccessSchool()` |
| `AttendanceScopeService` | Membangun query absensi yang sudah ter-scope; filter user diterapkan **setelah** scope |
| `AttendanceExportService` | CSV streaming (`streamDownload` + `chunkById(500)`) dari query yang sama |

Aturan yang tidak boleh dilanggar: **scope diterapkan sebelum filter dari request**, sehingga
`?school_id=` hanya bisa mempersempit hasil, tidak pernah memperluas.

## 7. Status Phase

| Phase | Modul | Status |
|---|---|---|
| 2 | Admin → Relation | PASS |
| 3 | Authorization / capability | PASS |
| 4 | School | PASS |
| 5 | Student | PASS |
| 6 | Program Kelas | PASS |
| 7 | Coach & Assignment | PASS |
| 8 | Program | PASS — update/delete sengaja tidak ada |
| 9 | School Plotting & PIC | PASS |
| 10 | Attendance Scope | PASS |
| 10.A | Attendance Export | PASS |
| 11 | Finance CSV | PASS — scope Finance (PRD Q6) masih terbuka |
| 12 | Accident Notes | PASS |
| 13 | WaHa / WhatsApp | **NOT IMPLEMENTED** |

Verifikasi terakhir:

```text
php artisan test           62 passed (301 assertions), 0 failure, 0 error
php artisan migrate:status  13 migrations, all Ran, 0 Pending
php artisan route:list      Showing [44] routes
integrity check 01–16       0 dari 16 gagal
```

## 8. Kekuatan Sistem Saat Ini

1. **Otorisasi terpusat.** Satu peta `ROLE_PERMISSIONS`; menambah role tidak berarti menyebar `if` ke
   seluruh controller.
2. **Fail-closed by design.** `accessibleSchoolIds()` mengembalikan `[]` (bukan `null`) untuk akun
   ter-scope tanpa plotting → melihat nol baris, bukan semua baris.
3. **Backend selalu diperiksa ulang.** Dropdown yang difilter di Blade tidak dipercaya; `class_id` dan
   key array `attendance` diverifikasi ulang di server.
4. **Penulisan laporan atomik.** `store()`/`update()` dibungkus `DB::transaction()`; kegagalan upload
   Cloudinary menghasilkan pesan validasi, bukan laporan setengah tersimpan.
5. **Master data historis terlindungi.** FK `reports` bersifat RESTRICT — hapus sekolah/kelas/coach yang
   masih punya laporan ditolak dengan pesan yang bisa dibaca, bukan error 500.
6. **Export tidak bisa membocorkan sekolah lain.** Export memakai builder yang sama dengan tabel, dan
   di-stream per 500 baris.

## 9. Keterbatasan yang Diketahui

| Hal | Kondisi | Referensi |
|---|---|---|
| Upload media laporan | Non-fungsional di environment ini — Cloudinary menolak `401 cloud_name mismatch`. Laporan tanpa media normal. | ISSUE-014 |
| Hapus media di Cloudinary | Tidak pernah terjadi: `report_media` tanpa kolom `cloudinary_public_id` | ISSUE-015 / MEDIA-001 |
| Program | Tidak ada update/delete — sesuai desain di `NoteTambahan.md` | P3-2 |
| Filter `report_status` | Divalidasi & didukung service, tetapi belum ada kontrolnya di UI | P3-3 |
| Dashboard SPV Coach | Punya `dashboard.view` tapi navbar tidak menampilkan link Dashboard | P3-1 |
| 4 pertanyaan requirement | Q-A…Q-D sengaja belum diimplementasikan | [03 §8](03_AUTENTIKASI_OTORISASI.md) |
| Notifikasi WhatsApp | Belum ada satu baris kode pun | Phase 13 |

## 10. Kesimpulan

Sistem berada pada kondisi **stabil dan terverifikasi**: seluruh defect P0/P1 dari audit stabilisasi
sudah diperbaiki dan ditutup dengan test regresi, 16 dari 16 integrity check bersih, dan tujuh kategori
audit keamanan lulus. Yang tersisa adalah satu item environment (ISSUE-014), sejumlah item kosmetik P3,
dan empat pertanyaan requirement yang menunggu keputusan bisnis — **bukan** menunggu kode.

Dengan demikian sistem **siap untuk memulai Phase 13 (WaHa)**, dengan catatan tiga prasyarat pada
[README](README.md#sebelum-memulai-phase-13-waha) diselesaikan lebih dulu.
