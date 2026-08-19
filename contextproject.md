# Learning Report System - Context Project

**Root Path:** `C:\Users\Nale\Documents\Digikidz\LRS\learning-report-system`

Dokumen ini merepresentasikan struktur dan kondisi (*state*) terkini dari repositori proyek Learning Report System (LRS). LRS adalah aplikasi berbasis web (PHP 8.4 & Laravel 12) yang berfungsi sebagai sistem manajemen dan pelaporan hasil pembelajaran.

---

## 1. Direktori & Root Files Utama
Berdasarkan struktur fisik *folder* proyek saat ini:

* **`app/`** - Inti logika aplikasi backend (Controllers, Models, Middleware, Services).
* **`bootstrap/`** - Script inisialisasi framework dan caching.
* **`config/`** - Pengaturan konfigurasi aplikasi (termasuk cloudinary, session, database).
* **`database/`** - Struktur dan *seeder* database (termasuk file SQLite bawaan).
* **`public/`** - Direktori *root* web server (berisi `index.php` dan hasil *build* aset).
* **`resources/`** - Menyimpan *Blade templates* (`views/`) serta file CSS/JS.
* **`routes/`** - Titik masuk (*entry point*) URL web (`web.php`) dan API (`api.php`).
* **`storage/`** - Direktori penyimpanan log (`logs/`), cache, dan file unggahan sementara.
* **`tests/`** - Skrip pengujian otomatis dengan PHPUnit (`Feature/` & `Unit/`).
* **File Root Penting:**
  * `.env` - Variabel lingkungan (*environment variables*).
  * `composer.json` - Daftar dependensi backend PHP (Fast-Excel, Cloudinary, dll) dan script otomatisasi seperti `composer setup` & `composer dev`.
  * `package.json` & `vite.config.js` - Pengaturan modul NPM & *bundler* *frontend*.
  * `Dockerfile` - Konfigurasi kontainer.
  * Berbagai file dokumentasi (`DEVELOPER_GUIDE.md`, `Product Requirements Document...md`, `NoteTambahan.md`).

---

## 2. Pemetaan Kode Inti (`app/`)

### A. Controllers (`app/Http/Controllers/`)
Menangani *request* HTTP dan logika modul sistem:
* **`Admin/`**: 
  * `ClassController.php`, `ProgramController.php`, `SchoolController.php` (Manajemen Master Data).
  * `CoachController.php`, `UserController.php` (Manajemen akun dan *assignment* kelas).
  * `DashboardController.php` (Ringkasan statistik admin).
  * `ReportController.php` (Fungsi tinjauan (*review*), approve/reject laporan dari *coach*).
* **`Coach/`**:
  * `ReportController.php` (Fungsi untuk *coach* dalam membuat, mengedit, dan melihat daftar laporannya sendiri).
* **`SchoolPic/`**:
  * `DashboardController.php` (Menampilkan statistik, kehadiran, dan laporan *Approved* untuk perwakilan sekolah).
* **`Auth/`**:
  * Mengurus alur otentikasi/Login.
* **Controller Global**: `AttendanceController.php` (Untuk rekap/ekspor presensi), `StudentController.php` (Manajemen murid & import Excel).

### B. Models (`app/Models/`)
Representasi entitas database:
* **Entitas Pengguna:** `User.php` (Termasuk konstanta *Role* dan logika relasi multi-sekolah/`school_user`).
* **Master Data:** `School.php`, `SchoolClass.php`, `Program.php`, `ProgramClass.php`, `Student.php`, `CoachClass.php`.
* **Laporan:** `Report.php`, `ReportAttendance.php`, `ReportMedia.php`.

### C. Services & Middleware
Logika pendukung dan filter akses:
* **`app/Services/`**: 
  * `AuthorizationService.php` (Sistem penentu hak akses multi-sekolah/kelas/laporan yang canggih di luar *role* standar).
  * `AttendanceScopeService.php` & `AttendanceExportService.php` (Layanan logika presensi).
* **`app/Http/Middleware/`**: 
  * `PermissionMiddleware.php`, `PermissionAnyMiddleware.php`, `RoleMiddleware.php` (Sistem *Role-Based Access Control* alias RBAC).

---

## 3. Struktur Tampilan (`resources/views/`)
Mengelompokkan *User Interface* sesuai dengan hak akses:
* **`admin/`**: Tampilan untuk modul pengelolaan *user*, *coach*, sekolah, kelas, program, dan *console* tinjauan (*review*) laporan.
* **`coach/`**: *Form* pengisian laporan (termasuk integrasi *upload* media & tabel kehadiran).
* **`school_pic/`**: *Dashboard* statistik performa & daftar absensi/laporan yang bisa dilihat sekolah rekanan.
* **`attendance/`** & **`students/`**: Tabel data, *import* massal, dan *export*.
* **`layouts/`** & **`partials/`**: Struktur tata letak utama (Sidebar, Navbar, Footer, struktur HTML dasar).

---

## 4. Stack & Teknologi (*Current Status*)
* **Bahasa:** PHP 8.4, JavaScript
* **Framework:** Laravel 12.0
* **Database:** SQLite (lokal, di `database/database.sqlite`), support MySQL/PostgreSQL
* **Aset & Antarmuka:** Vite Bundler, Blade Templating
* **Ekstensi Terpasang:** 
  * `cloudinary/cloudinary_php` (Menangani penyimpanan foto laporan di *cloud*).
  * `rap2hpoutre/fast-excel` (Fungsi impor data murid dan ekspor data presensi ke Excel).

---

## 5. Aktor & Hak Akses (RBAC System)
Sistem memiliki pengkategorian *user* dengan wewenang (Roles) sebagai berikut:
1. **SuperAdmin**: Akses mutlak (*root*), manajemen *users*.
2. **Relation (Admin)**: Konfigurasi data master & bertugas menyetujui (Approve/Reject) laporan yang diunggah *coach*.
3. **Coach**: Mengakses data kelas yang secara spesifik ditugaskan (assigned) kepadanya, mengisi absen, dan melengkapi Laporan Belajar siswa.
4. **SPV Coach**: Memantau operasional *coach*.
5. **School PIC / Teacher School / Finance**: Pengguna terikat dengan sekolah (*school-scoped*). Hanya bisa melihat laporan berstatus *Approved* dan laporan kehadiran sesuai dengan sekolah-sekolah yang di-*plot* ke akun mereka.
