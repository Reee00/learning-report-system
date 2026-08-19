# Dokumen 10 - Dokumentasi Frontend dan Backend

**Terakhir diperbarui:** Sesuai dengan status root project LRS terbaru.

Dokumen ini memuat analisis arsitektur dan komponen aplikasi secara teknis baik dari sisi _Frontend_ maupun _Backend_ yang berjalan saat ini.

## 1. Dokumentasi Backend

### 1.1. Teknologi Utama
*   **Bahasa:** PHP 8.4
*   **Framework:** Laravel 12.0
*   **Database:** SQLite Lokal (Tersedia integrasi Cloudinary untuk Object Storage)

### 1.2. Daftar Controller & Services Utama
Berdasarkan `routes/web.php` dan `app/Http/Controllers`, sistem menggunakan *business logic* berikut:
*   `Auth\LoginController`: Menangani autentikasi (_login_ / _logout_) serta *redirect* halaman pendaratan (landing page) berdasarkan ke-7 *role*.
*   `Admin\DashboardController`: Tampilan ringkasan statistik (hanya untuk SuperAdmin/Relation).
*   `Admin\UserController`: CRUD data pengguna, reset kata sandi, serta men-*assign* banyak sekolah (Plotting).
*   `Admin\ReportController`: Fasilitas *Review* (Approve/Reject) untuk Relation/Admin.
*   `Admin\SchoolController`, `ClassController`, `ProgramController`: Pengelolaan data master.
*   `Admin\CoachController`: Manajemen *assign/unassign* pelatih terhadap kelas.
*   `Coach\ReportController`: Pengisian formulir laporan, unggah foto Cloudinary, dan input tabel absensi oleh Coach.
*   `SchoolPic\DashboardController`: Tampilan ringkasan statistik dan rekap untuk PIC Sekolah.
*   `AttendanceController`: Pemrosesan tabel absen dan *streaming export CSV* terpusat.
*   `StudentController`: CRUD dan fitur _import_ daftar siswa menggunakan `fast-excel`.

**Logika Otorisasi (Services):**
Backend sangat bergantung pada `app/Services/AuthorizationService.php` sebagai pusat perizinan (*capability*). Sistem tidak memvalidasi string nama role mentah, melainkan memeriksa hak melalui fungsi seperti `$authorization->allows()`.

### 1.3. Middleware dan Proteksi
*   `auth`: Memastikan request sudah diautentikasi.
*   `permission:<capability>`: Mengecek satu *capability* spesifik.
*   `permission_any:<capA>,<capB>`: Mengizinkan *sharing* akses.
*   `role:<roleA>,<roleB>`: Gerbang akses kasar.

## 2. Dokumentasi Frontend

### 2.1. Teknologi UI
*   **Template Engine:** Laravel Blade (`resources/views`).
*   **CSS/JS Framework:** Memanfaatkan **Bootstrap 5.3** (via CDN).
*   **Asset Bundler:** Proyek memiliki *setup* Vite (`vite.config.js`), namun perintah *build* saat ini masih diatur pass-through (`"build": "echo 'No build step required'"`), sehingga mayoritas *styling* murni memanfaatkan Bootstrap secara langsung tanpa kompilasi internal.

### 2.2. Struktur Halaman (Views)
*   `auth/`: Form Login.
*   `admin/`: Dashboard & Manajemen Master Data, Users, dan Console Review Laporan.
*   `coach/`: Daftar Laporan dan form multi-step untuk Coach (termasuk *Accident Notes* UI).
*   `school_pic/`: Dashboard statistik PIC.
*   `attendance/`: Halaman tabel presensi & tombol ekspor.
*   `students/`: Tabel siswa dan form impor.
*   `partials/` & `layouts/`: Komponen pembungkus seperti Navbar, Sidebar, Footer, dan rendering `accident-notes`.

### 2.3. Integrasi Khusus (AJAX & Export)
1. **Pemuatan Murid Dinamis (AJAX)**: Saat *Coach* membuat laporan dan memilih kelas di form, sebuah AJAX HTTP GET dikirim ke `/api/classes/{class}/students`. Backend (melalui *Closure* di web.php) mengembalikan JSON berisi daftar siswa yang terdaftar di kelas tersebut secara instan tanpa perlu memuat ulang seluruh form.
2. **Streaming CSV Download**: Fitur ekspor presensi tidak menghasilkan *file buffer* fisik. Fitur ini secara *on-the-fly* men-chunk (membagi) kueri Eloquent per 500 baris ke dalam `StreamedResponse`, mencegah *crash* memori (OOM) meskipun datanya besar.
