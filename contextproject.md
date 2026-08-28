# Learning Report System - Context Project

**Root Path:** `C:\Users\Nale\Documents\Digikidz\LRS\learning-report-system`

Dokumen ini merepresentasikan struktur dan kondisi (*state*) 100% aktual dari repositori proyek Learning Report System (LRS) saat ini, berdasarkan implementasi pada codebase. LRS adalah aplikasi berbasis web yang berfungsi sebagai sistem manajemen dan pelaporan hasil pembelajaran.

---

## 1. Project Overview
Aplikasi ini dikembangkan untuk memfasilitasi pelaporan hasil belajar siswa secara digital. Terdapat berbagai entitas pengguna mulai dari pihak manajemen (SuperAdmin, Relation, SPV), instruktur lapangan (Coach), hingga pihak mitra sekolah (School PIC, Teacher, Finance). Sistem mengakomodasi pengelolaan master data (Sekolah, Program, Kelas, Siswa), absensi, serta pelaporan belajar siswa yang disertai dengan media dan review.

## 2. Current Tech Stack
* **Bahasa:** PHP 8.4, JavaScript
* **Framework:** Laravel 12.0
* **Database:** MySQL (sebagai database utama yang diaktifkan di `.env`). SQLite masih tersedia sebagai opsi *fallback* bawaan Laravel.
* **Aset & Antarmuka:** Vite Bundler, Blade Templating, Vanilla CSS/JS dengan utilitas modern.
* **Ekstensi Terpasang:** 
  * `cloudinary/cloudinary_php`: Menangani penyimpanan foto/media laporan di *cloud*.
  * `rap2hpoutre/fast-excel`: Fungsi impor data murid dan ekspor data presensi ke Excel.

## 3. Architecture
Menggunakan arsitektur standar MVC (Model-View-Controller) khas Laravel, dengan tambahan *Service Layer* dan ekstensifikasi *Middleware* untuk Role-Based Access Control (RBAC).

## 4. Database
Telah bermigrasi secara operasional dari SQLite ke **MySQL** (terlihat pada konfigurasi `DB_CONNECTION=mysql` di `.env`).
* **Relasi Penting:**
  * Pengguna sekolah (*school-scoped*) terhubung ke banyak entitas `School` melalui pivot table `school_user` (Multi-school assignment).
  * `CoachClass` memetakan penugasan antara `User` (Coach) dan `SchoolClass`.
  * `ProgramClass` memetakan relasi reusable `Program` ke `SchoolClass`.
  * `Report` berelasi dengan `User` (Coach), `SchoolClass`, `ReportMedia`, dan `ReportAttendance`.

## 5. Authentication & Authorization
Sistem otentikasi login *default* Laravel dengan modifikasi proteksi berlapis.
* **Authorization Layer:** `app/Services/AuthorizationService.php` menangani logika kompleks di luar Role standar, seperti verifikasi apakah seorang *Coach* berhak mengakses *Class* tertentu, atau apakah *School PIC* berhak mengakses laporan spesifik berdasarkan relasi multi-sekolahnya.
* **Middleware:** Menggunakan `RoleMiddleware.php`, `PermissionMiddleware.php`, dan `PermissionAnyMiddleware.php` untuk membatasi akses URL berdasarkan *Role* dan kumpulan hak *Permission* yang melekat pada user bersangkutan.

## 6. Roles & Permissions
Sistem memiliki 7 peran aktor (diatur pada konstan `User.php` dan `AuthorizationService`), dengan wewenang yang sudah diimplementasikan:
1. **SuperAdmin**: Akses mutlak (*root*), manajemen *users*.
2. **Relation**: Admin operasional pusat. Mengelola Master Data, *assign* Coach ke kelas, dan bertugas memeriksa (*Review: Approve/Reject*) laporan yang dibuat oleh Coach.
3. **SPV Coach**: Memantau operasional para *Coach*. Memiliki hak baca lintas sekolah/kelas.
4. **Coach**: Instruktur yang mengajar. Mengakses kelas yang ditugaskan kepadanya secara spesifik, menambah siswa ke kelas, mengisi absen, dan melengkapi Laporan Belajar (Coach Report).
5. **School PIC (PIC DK SCHOOL)**: Perwakilan sekolah mitra. Pengguna terikat dengan sekolah (*school-scoped*). Dapat melihat laporan berstatus *Approved* serta rekap kehadiran pada sekolah yang diplot ke akunnya.
6. **Teacher School**: Guru internal sekolah mitra. Bersifat *school-scoped* seperti PIC.
7. **Finance**: Bagian keuangan sekolah mitra. Bersifat *school-scoped*.

## 7. Business Logic (Implemented)
* **Master Data Management**: CRUD penuh untuk entitas `School`, `SchoolClass`, `Program` (serta relasinya), dan Manajemen akun *Coach*. Dipegang oleh fungsi-fungsi dalam direktori `app/Http/Controllers/Admin/`.
* **Coach Student Management**: *Coach* dapat menambah/import dan melihat data *Student* pada kelas yang sudah ditugaskan kepadanya (`app/Http/Controllers/StudentController.php`).
* **Coach Report Workflow**: 
  1. *Coach* membuat laporan, melampirkan media (lewat Cloudinary), dan mengisi absen (`CoachReportController`).
  2. Laporan masuk ke dalam daftar *review* Admin/Relation.
  3. *Relation* atau *SuperAdmin* menyetujui (Approve) atau menolak (Reject) laporan (`AdminReportController`).
  4. Laporan yang sudah di-*approve* dapat dilihat oleh mitra sekolah (*School PIC* / *Teacher* / *Finance*).
* **Attendance Scope**: *Attendance* siswa dicatat saat *Coach* membuat laporan. Pihak sekolah dan internal dapat melihat presensi tersaring berdasarkan sekolah/kelas (`app/Services/AttendanceScopeService.php`).

## 8. Export & Media
* **Export**: Fitur mengekspor data absensi ke format Excel yang di-*handle* oleh `AttendanceController@export`.
* **Media/Cloudinary**: Pengunggahan foto kegiatan di dalam laporan tidak disimpan di *storage* lokal, melainkan sudah terintegrasi *full* ke **Cloudinary** (di-*handle* via env dan `ReportMedia` model).

## 9. UI/UX & Responsive Architecture
* **Layout**: Telah diimplementasikan menggunakan arsitektur tampilan modern dengan pola **Left Sidebar Application Layout**.
* **Responsiveness**: Pembaharuan desain antarmuka (*UI/UX improvements*) sudah diimplementasikan penuh untuk menjamin tampilan yang responsif terhadap resolusi *mobile* maupun *desktop*.

## 10. Routes/Modules
Terdapat pengelompokan rute (`routes/web.php`) sesuai modul aktor:
* `admin/*` - Untuk SuperAdmin dan Relation (Master Data, User Management, Report Review, Dashboard).
* `coach/*` - Modul milik Coach (Daftar Laporan pribadi, Input Laporan, Manajemen Siswa di kelasnya).
* `pic/*` - Modul Dashboard mitra sekolah.
* `/attendance` & `/classes/{class}/students` - Rute general yang diakses berdasarkan kapabilitas izin masing-masing *role*.

## 11. Important Relationships
* `User` (School Scoped) <-> `School` : Many-to-Many via `school_user`
* `User` (Coach) <-> `SchoolClass` : One-to-Many via `CoachClass`
* `SchoolClass` <-> `Program` : Many-to-Many via `ProgramClass`
* `SchoolClass` <-> `Student` : One-to-Many
* `Report` <-> `ReportAttendance` : One-to-Many
* `Report` <-> `ReportMedia` : One-to-Many

## 12. Current Environment
* Sistem berjalan pada lingkungan **lokal** / pengembangan (PHP 8.4, `APP_ENV=local`). 

## 13. Implemented Features
* Autentikasi Login/Logout.
* RBAC (SuperAdmin, Relation, SPV Coach, Coach, School PIC, Teacher, Finance).
* Master Data CRUD (Schools, Classes, Programs, Users, Coaches).
* Multi-school user plotting untuk *school-scoped roles*.
* Coach Assignment (Menugaskan *Coach* ke kelas tertentu).
* Student Management & Import Excel di dalam kelas (Coach & Admin).
* Coach Report Creation, Edit, Submission.
* Review Laporan (Approve/Reject) oleh Admin/Relation.
* Integrasi Cloudinary untuk foto/media.
* Attendance tracking and export to Excel.
* Left Sidebar Responsive UI/UX Application Layout.
* AJAX Endpoints (untuk *dropdown* pilihan murid secara dinamis).

## 14. Known Limitations
* (Kosong)

## 15. Planned / Not Implemented Features
* Telegram Bot Integration (Notifikasi dan pelaporan via Bot Telegram) - **PLANNED / FUTURE PLAN**.
* Modul Catatan Kecelakaan (Accident Notes) belum tersedia.

## 16. Development Rules / Constraints
* Master Data harus diatur oleh *Relation* / *SuperAdmin*.
* Hak akses aksesabilitas data (seperti kelas dan report) bergantung penuh pada `AuthorizationService`, pastikan setiap fitur baru memanggil *service* ini jika menyangkut proteksi rute atau *model policy*.
* File `.env` tidak boleh di-*commit* ke repositori; pastikan semua rahasia Cloudinary & Database dikelola dengan benar.
* Integrasi UI harus tetap mematuhi pola responsif Sidebar yang telah dibangun.
