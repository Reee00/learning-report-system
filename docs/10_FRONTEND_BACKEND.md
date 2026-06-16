# Dokumen 10 - Dokumentasi Frontend dan Backend

Dokumen ini memuat analisis arsitektur dan komponen aplikasi secara teknis baik dari sisi _Frontend_ maupun _Backend_ yang ditemukan dalam *source code*.

## 1. Dokumentasi Backend

### 1.1. Teknologi Utama
*   **Bahasa:** PHP 8.2 / 8.3
*   **Framework:** Laravel 12.0
*   **ORM:** Eloquent ORM

### 1.2. Daftar Controller Utama
Berdasarkan `routes/web.php`, berikut adalah _controllers_ yang menangani *business logic*:
*   `LoginController`: Menangani autentikasi (_login_ / _logout_).
*   `Admin\DashboardController`: Menangani tampilan ringkasan untuk Admin.
*   `Admin\UserController`: Menangani CRUD data pengguna dan pengaturan ulang kata sandi.
*   `Admin\ReportController`: Menangani _review_ (Approve/Reject) laporan oleh Admin.
*   `Admin\SchoolController` & `ClassController`: Manajemen data entitas sekolah dan kelas.
*   `Admin\CoachController`: Manajemen *assign/unassign* pelatih terhadap kelas.
*   `Coach\ReportController`: Pengisian formulir laporan, *upload* media, dan manajemen absensi oleh Pelatih.
*   `SchoolPic\DashboardController`: Tampilan ringkasan statistik untuk PIC Sekolah.
*   `StudentController`: CRUD dan fitur _import_ daftar siswa dalam suatu kelas menggunakan `fast-excel`.

### 1.3. Middleware dan Proteksi
*   `auth`: Memastikan akses hanya untuk pengguna yang telah login.
*   `role:admin`, `role:coach`, `role:school_pic`: Custom middleware untuk otorisasi akses spesifik berdasarkan kolom `role` di tabel `users`.

## 2. Dokumentasi Frontend

### 2.1. Teknologi UI
*   **Template Engine:** Laravel Blade (`resources/views`).
*   **Asset Bundler:** Vite (`vite.config.js`).
*   **CSS/JS Framework:** Tidak secara eksplisit disebutkan dalam `package.json`, tetapi *stack* standar Laravel biasanya menggunakan TailwindCSS atau Bootstrap.

### 2.2. Struktur Halaman (Views)
*Berdasarkan arsitektur controller, sistem memiliki halaman-halaman berikut:*
*   **Halaman Autentikasi:** Form Login.
*   **Dashboard:** Terpisah untuk `Admin`, `PIC Sekolah`, dan `Coach` (via daftar laporan).
*   **Form Laporan:** Antarmuka _Coach_ untuk memilih kelas, mengisi ringkasan pelajaran (`lesson_material`, `activity_summary`), memilih absen (`attendances`), dan mengunggah media (foto/video).
*   **Master Data:** Halaman *table view* untuk mengelola _Schools_, _Classes_, _Students_, _Coaches_, dan _Users_.

### 2.3. Alur Kerja Navigasi (Workflows)
1.  **Workflow Coach:** `Login` -> `Dashboard (Daftar Laporan)` -> `Klik Buat Laporan` -> `Pilih Kelas` (AJAX akan me-load daftar siswa dari `/api/classes/{class}/students`) -> `Isi Materi & Upload Foto` -> `Simpan`.
2.  **Workflow Admin Review:** `Login` -> `Dashboard` -> `Menu Laporan` -> `Pilih Laporan Pending` -> `Klik Approve / Reject` dengan memberikan `admin_notes` -> `Selesai`.
3.  **Workflow Assign Coach:** `Admin Login` -> `Menu Coaches` -> `Pilih Coach` -> `Assign ke Kelas` -> `Tersimpan (Coach dapat melihat kelas tersebut saat melapor)`.
