# Dokumentasi Developer: Learning Report System

## 1. Pendahuluan
Dokumentasi ini ditujukan untuk junior developer yang ingin memahami, mengembangkan, atau memelihara aplikasi Learning Report System.

Aplikasi ini dibangun dengan Laravel dan menyediakan fitur untuk:
- login dan autentikasi pengguna
- manajemen sekolah, kelas, siswa, coach, dan PIC sekolah
- coach membuat laporan pembelajaran
- admin memeriksa, menyetujui, atau menolak laporan
- PIC sekolah melihat laporan untuk sekolahnya

## 2. Teknologi Utama
- Laravel PHP framework
- Blade templates untuk tampilan
- Eloquent ORM untuk model database
- Cloudinary untuk upload foto dan video
- FastExcel untuk import siswa melalui file Excel/CSV

## 3. Struktur Proyek Utama
Berikut folder dan file penting yang harus diketahui:

- `routes/web.php` - definisi rute utama aplikasi
- `app/Http/Controllers/` - semua logic controller
- `app/Models/` - model Eloquent untuk entitas database
- `app/Http/Middleware/RoleMiddleware.php` - middleware untuk kontrol role
- `resources/views/` - tampilan Blade
- `database/migrations/` - struktur tabel database
- `app/Helpers/CloudinaryHelper.php` - helper upload/delete file ke Cloudinary

## 4. Role Pengguna dan Akses
Aplikasi menggunakan 3 role utama:

1. `admin`
   - akses penuh ke dashboard admin
   - manajemen user
   - manajemen sekolah dan kelas
   - melihat, menyetujui, atau menolak laporan
   - menambahkan coach, assign class

2. `coach`
   - membuat laporan pembelajaran
   - mengedit laporan dengan status `draft` atau `rejected`
   - melihat daftar kelas yang di-assign
   - upload foto dan video
   - menyimpan absensi siswa

3. `school_pic`
   - melihat dashboard sekolah sendiri
   - melihat laporan untuk sekolah yang terhubung

## 5. Alur Utama Aplikasi
### 5.1 Login
Rute login ditentukan di `routes/web.php`:
- `GET /login` menampilkan form login
- `POST /login` melakukan autentikasi
- `POST /logout` keluar dari aplikasi

Semua rute penting berada di dalam middleware `auth`.

### 5.2 Rute dan Group Akses
- `Route::middleware(['auth', 'role:coach'])->prefix('coach')->name('coach.')->group(...)`
  - Mengelola laporan coach
- `Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(...)`
  - Dashboard admin, user, schools, classes, coaches, reports
- `Route::middleware(['auth', 'role:school_pic'])->prefix('pic')->name('pic.')->group(...)`
  - Dashboard PIC sekolah
- Rute publik untuk siswa:
  - `GET /classes/{class}/students`
  - `POST /classes/{class}/students`
  - `POST /classes/{class}/students/import`
  - `DELETE /classes/{class}/students/{student}`
  - `GET /students/template`

### 5.3 Middleware Role
`app/Http/Middleware/RoleMiddleware.php`
- Memastikan user sudah login
- Memeriksa role user dari parameter middleware
- Jika tidak sesuai, return `403 Forbidden`

Contoh penggunaan:
- `middleware('role:admin')`
- `middleware('role:coach,admin')`

## 6. Model Utama dan Relasi
### 6.1 `User`
- `name`, `email`, `password`, `role`, `school_id`
- relasi:
  - `school()` untuk PIC sekolah
  - `coachClasses()` untuk coach assignment kelas

### 6.2 `School`
- `name`, `address`, `pic_name`
- relasi:
  - `classes()` daftar kelas di sekolah
  - `users()` daftar pengguna yang terkait

### 6.3 `SchoolClass`
- disimpan di tabel `classes`
- `school_id`, `name`
- relasi:
  - `school()` sekolah
  - `students()` siswa
  - `coachAssignments()` pemetaan coach ke kelas

### 6.4 `Student`
- `name`, `class_id`
- relasi: `class()` ke `SchoolClass`

### 6.5 `Report`
- `coach_id`, `school_id`, `class_id`, `report_date`
- `lesson_material`, `activity_summary`, `notes`
- `status`: `submitted`, `approved`, `rejected`, `draft`
- `admin_notes`, `approved_by`, `approved_at`
- relasi:
  - `coach()`
  - `school()`
  - `schoolClass()`
  - `attendances()`
  - `photos()` dan `videos()`

### 6.6 `ReportAttendance`
- `report_id`, `student_id`, `status`
- status absensi: `present`, `absent`, `sick`, `permission`

### 6.7 `ReportMedia`
- `report_id`, `type`, `path`, `original_name`
- type: `photo` atau `video`
- method `url()` mengembalikan URL publik

### 6.8 `CoachClass`
- `coach_id`, `class_id`
- pivot table untuk assignment coach ke kelas

## 7. Controller Penting
### 7.1 `App\Http\Controllers\Coach\ReportController`
- `index()` menampilkan daftar laporan coach
- `create()` menampilkan form pembuatan laporan
- `store()` menyimpan laporan baru, upload foto/video, menyimpan absensi
- `edit()` mengedit laporan dengan batasan role dan status
- `update()` memperbarui laporan, menghapus media, upload media baru

### 7.2 `App\Http\Controllers\Admin\DashboardController`
- `index()` menampilkan statistik laporan dan daftar laporan `submitted`

### 7.3 `App\Http\Controllers\Admin\UserController`
- manajemen pengguna: `index`, `store`, `update`, `resetPassword`, `destroy`

### 7.4 `App\Http\Controllers\Admin\SchoolController`
- manajemen sekolah: `index`, `store`, `update`, `destroy`

### 7.5 `App\Http\Controllers\Admin\ClassController`
- manajemen kelas: `index`, `store`, `destroy`

### 7.6 `App\Http\Controllers\Admin\CoachController`
- `index()` daftar coach
- `show()` detail coach dan assignment kelas
- `assign()` assign coach ke kelas
- `unassign()` hapus assignment

### 7.7 `App\Http\Controllers\Admin\ReportController`
- `index()` daftar laporan admin dengan filter
- `show()` detail laporan
- `approve()` setujui laporan
- `reject()` tolak laporan dengan catatan admin

### 7.8 `App\Http\Controllers\StudentController`
- `show()` halaman daftar siswa per kelas
- `store()` tambah siswa manual
- `import()` upload siswa dari file Excel/CSV
- `destroy()` hapus siswa
- `template()` download template CSV
- `authorizeAccess()` validasi akses berdasarkan role

## 8. Upload Media dan Cloudinary
File media (foto/video) diupload menggunakan `app/Helpers/CloudinaryHelper.php`.
- `CloudinaryHelper::upload($filePath, $folder)` untuk upload
- `CloudinaryHelper::delete($publicId)` untuk hapus jika diperlukan

Konfigurasi Cloudinary disimpan di `config/services.php` dan environment file: `cloudinary.cloud_name`, `cloudinary.api_key`, `cloudinary.api_secret`.

## 9. Import Siswa
Fitur import siswa ada di `StudentController@import` menggunakan package `Rap2hpoutre\FastExcel`.
- Format file: `xlsx`, `xls`, `csv`
- Kolom utama: `nama_siswa` atau `name`
- Sistem akan melewati siswa yang sudah ada di kelas tersebut

## 10. Database dan Migrasi
Tabel penting:
- `users`
- `schools`
- `classes`
- `students`
- `coach_classes`
- `reports`
- `report_attendances`
- `report_media`
- `sessions`

Untuk membuat migrasi dan menjalankan seed, gunakan:
```bash
php artisan migrate
php artisan db:seed
```

## 11. Setup Lokal
1. Salin `.env.example` menjadi `.env`
2. Atur koneksi database di `.env`
3. Set `APP_KEY` dengan:
```bash
php artisan key:generate
```
4. Jalankan migrasi:
```bash
php artisan migrate
```
5. Jalankan server lokal:
```bash
php artisan serve
```

## 12. Tips Bekerja di Proyek Ini
- Pelajari `routes/web.php` untuk memahami URL utama dan namespace controller.
- Baca controller sesuai role: `Admin`, `Coach`, `Student`, `Pic`.
- Pastikan `RoleMiddleware` digunakan di semua rute yang butuh proteksi role.
- Jika menambahkan field baru pada `Report`, update model, migrasi, controller, dan view.
- Untuk debug upload Cloudinary, cek `config/services.php` dan environment variable.

## 13. Cara Menambahkan Fitur Baru
1. Tambahkan field di migration bila perlu.
2. Tambahkan properti fillable di model.
3. Perbarui controller validation dan logic.
4. Tambahkan route baru di `routes/web.php`.
5. Buat atau perbarui view Blade di `resources/views/`.
6. Uji alur dengan user yang sesuai role.

## 14. Penutup
Dokumentasi ini memberikan gambaran arsitektur dan alur kerja utama aplikasi.
Jika ingin belajar lebih dalam, fokus pada:
- Eloquent relations
- Request validation
- Middleware dan auth
- File upload
- Blade templates

Selamat belajar dan semoga membantu kamu memahami Learning Report System!
