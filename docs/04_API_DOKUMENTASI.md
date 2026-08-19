# 📡 API Documentation & Web Routes

**Terakhir diperbarui:** Sesuai dengan status root project LRS terbaru.

## 1. Overview API

Sistem ini tidak diekspos sebagai antarmuka REST API murni, melainkan menggunakan rute *web server-side rendering* (Blade) yang dilengkapi dengan beberapa layanan *endpoint asinkron* (seperti AJAX load student). 

Semua *endpoint* menggunakan sesi Laravel (Cookie `laravel_session`) dengan perlindungan CSRF.

**Base URL**: 
```
Local: http://localhost:8000
Production: https://[domain]
```

---

## 2. Authentication Flow

### A. GET `/login`
**Deskripsi**: Tampilkan form login (atau *redirect* langsung jika sudah login).
**Auth**: None
**Status**: `200 OK` atau `302 Found`

### B. POST `/login`
**Deskripsi**: Proses pengecekan `email` dan `password`.
**Auth**: None
**Response**:
- Sukses (302): *Redirect* ke dashboard yang relevan dengan 7 *role* yang ada (misal: `/admin/schools`, `/attendance`, dll.).
- Gagal (302): Kembali ke `/login` dengan validasi *errors*.

### C. POST `/logout`
**Deskripsi**: Invalidasi sesi dan membuang token cookie.
**Auth**: Wajib (Session)

---

## 3. Web Endpoints Berdasarkan Capability

Sistem tidak mengelompokkan API berdasarkan prefix "/admin" atau "/coach" semata, melainkan berdasarkan kapabilitas (peran) masing-masing *user*. 

### A. Modul Users & Master Data (Manage Users, Schools, Programs, Classes)
**Capability Required:** `users.manage`, `schools.*`, dll. (Utamanya SuperAdmin & Relation)

- **GET /admin/users**: Menampilkan daftar *user* dengan filter dan paginasi.
- **POST /admin/users**: Membuat user baru. Mewajibkan array `school_ids` untuk role berbasis *school-scoped*.
- **PUT /admin/users/{user}**: Memperbarui metadata dan role user.
- **PATCH /admin/users/{user}/reset-password**: Mengembalikan password user ke kondisi *default* (biasanya diinput manual oleh admin).
- **GET /admin/schools**, **POST /admin/schools**, **PUT**, **DELETE**: Manajemen Entitas Sekolah Mitra.
- **GET /admin/programs**, **POST /admin/programs**, **PUT**, **DELETE**: Manajemen Entitas Program Pendidikan.
- **GET /admin/classes**, **POST**, **PUT**, **DELETE**: Manajemen Entitas Kelas beserta *sync* program pendidikannya.

### B. Modul Siswa (Student Management)
**Capability Required:** `students.manage`

- **GET /classes/{class}/students**: Memuat halaman pengelolaan siswa untuk kelas spesifik.
- **POST /classes/{class}/students**: Menambahkan satu murid baru ke dalam kelas.
- **POST /classes/{class}/students/import**: Menerima unggahan file Excel/CSV (Multipart FormData) untuk proses bulk-insert. Sistem mengekstrak kolom `nama_siswa` atau `name`.
- **GET /students/template**: Endpoint untuk mengunduh berkas `.xlsx` kosong (template format struktur bulk-import).
- **DELETE /classes/{class}/students/{student}**: Menghapus baris murid.

### C. Modul Coaches & Assignment
**Capability Required:** `coaches.assign`, `coaches.view`

- **GET /admin/coaches**: Menampilkan daftar akun ber-role *Coach*.
- **GET /admin/coaches/{coach}**: Tampilan detil seorang Coach dan riwayat mengajar.
- **POST /admin/coaches/{coach}/assign**: Memasukkan `class_id` untuk ditugaskan.
- **DELETE /admin/coaches/{coach}/assignments/{assignment}**: Membatalkan penugasan kelas.

### D. Modul Learning Reports (Drafting & Reviewing)
**Bagi Pelapor (Capability: `reports.create` - Coach):**
- **GET /coach/reports**: Daftar seluruh laporan milik coach yang *login*.
- **GET /coach/reports/create**: Tampilan form *wizard* pelaporan.
- **POST /coach/reports**: Proses unggah FormData (termasuk *array file foto/video*). Menulis ke Cloudinary dan mempopulasi ke tabel terkait (termasuk `report_attendances`).
- **GET /coach/reports/{report}/edit**: Menampilkan form dengan *state* sebelumnya, memungkinkan Coach untuk mengunggah ulang revisi jika laporannya di-*reject*.
- **PUT /coach/reports/{report}**: Memperbarui entitas yang sudah disubmit. (Hanya diizinkan bila status saat ini adalah `draft` atau `rejected`).

**Bagi Reviewer (Capability: `reports.review` - Admin/Relation):**
- **GET /admin/reports**: *Dashboard* pengecekan silang bagi seluruh laporan dengan status *submitted* di seluruh sekolah.
- **GET /admin/reports/{report}**: Tampilan penuh dokumen *report* bersama barisan absen.
- **PATCH /admin/reports/{report}/approve**: Mengubah status laporan menjadi `approved`.
- **PATCH /admin/reports/{report}/reject**: Mengubah status ke `rejected`, mewajibkan adanya string di kolom `admin_notes`.

### E. Modul Khusus Dashboard Laporan
- **GET /pic/dashboard**: Tampilan dashboard spesifik `school_pic` atau `teacher_school`. Hanya memuat *Query builder* dengan tambahan kondisi `.where('status', 'approved')` dan *school_scoped()*.

### F. Modul Attendance (Presensi)
**Capability Required:** `attendance.view`

- **GET /attendance**: Menampilkan tabel silang untuk absensi murid dari banyak laporan. Secara otomatis terisolasi berdasarkan array hak milik `school_id` sang pembuka *route*.
- **GET /attendance/export**: Sebuah endpoint khusus yang mengirim `StreamedResponse`. Mengonversi data Eloquent Chunking langsung ke berkas CSV yang diunduh pada sisi klien untuk menghindari *memory exhaustion*.

---

## 4. RESTful AJAX API

Selain metode *rendering* tradisional, ada satu _endpoint_ yang khusus difungsikan untuk injeksi JavaScript asinkron.

### GET `/api/classes/{class}/students`
**Deskripsi**: Mengembalikan format JSON berisi objek siswa (`id` dan `name`) yang tergabung di dalam ID kelas tertentu. Dipakai ketika Coach membuka _dropdown_ Kelas di antarmuka Form Laporan Baru.
**Auth**: Required (Session)
**Response `200 OK`**:
```json
[
  {
    "id": 105,
    "name": "Budi Santoso"
  },
  {
    "id": 106,
    "name": "Arif Sudirman"
  }
]
```

---

## 5. Status Codes Reference

| Code | Arti | Skenario Sering Muncul |
|------|---------|-------|
| **200** | OK | Merender Blade view atau JSON response sukses. |
| **302** | Found | Hasil dari form sumbit (Flash Success/Errors) kembali ke tampilan awal. |
| **401** | Unauthenticated | Membuka route yang dikunci tanpa sesi/token (Redirect login). |
| **403** | Forbidden | Pengecekan Service AuthorizationService `allows()` bernilai `false`. |
| **404** | Not Found | Membuka laporan, kelas, atau URL yang sudah tidak ada. |
| **422** | Unprocessable Entity | Validasi Eloquent meleset (misal: tipe dokumen bukan *image*). |
| **500** | Server Error | Masalah interupsi database atau Service Pihak Ketiga (seperti Cloudinary API Down). |
