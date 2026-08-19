# Dokumen 09 - Dokumentasi Fitur dan Modul

**Terakhir diperbarui:** Sesuai dengan status root project LRS terbaru.

Dokumen ini memuat analisis fitur dan modul yang dirancang berdasarkan fungsionalitas aktual yang berjalan (merujuk pada `routes/web.php` dan logika Controller/Service). Sistem berjalan menggunakan otorisasi berbasis *capability* (kemampuan/izin akses spesifik) yang dikelompokkan ke dalam 7 role.

## 1. Fitur Utama Sistem Berdasarkan Modul

### 1.1. Modul Manajemen Pengguna & Sistem
*Hanya dapat diakses oleh pemegang capability: `users.manage` (SuperAdmin)*
- **User CRUD**: Membuat akun, mengubah data akun, dan menghapus akun.
- **Role Assignment**: Menetapkan role pada akun (terdapat 7 role yang valid).
- **School Plotting**: Memetakan (men-*assign*) satu akun atau lebih ke satu atau banyak sekolah (`school_user` pivot). Wajib bagi role *school-scoped*.
- **Password Reset**: Mengubah ulang password user lain dari sisi admin (tanpa konfirmasi email).

### 1.2. Modul Master Data
*Dapat diakses oleh pemegang capability: `schools.*`, `program_classes.*`, `programs.*`, `students.*` (Utamanya SuperAdmin & Relation)*
- **Manajemen Sekolah**: Data sekolah mitra (Nama, Alamat, Nama PIC).
- **Manajemen Program**: Data program umum (Coding, Robotics, dll.).
- **Manajemen Kelas**: Pembuatan Kelas Spesifik untuk Sekolah, dan menghubungkannya dengan Program.
- **Manajemen Siswa**: Menambah siswa, menghapus, atau melakukan **Import Massal via Excel**.

### 1.3. Modul Manajemen Pelatih (Coach Assignment)
*Dapat diakses oleh pemegang capability: `coaches.*` (Utamanya SuperAdmin, Relation, SPV Coach)*
- **Daftar & Detail Coach**: Melihat statistik dan beban mengajar.
- **Penugasan Kelas (Assign)**: Menyambungkan Coach dengan Kelas tertentu agar Coach tersebut bisa mengisi laporan.
- **Pembatalan Tugas (Unassign)**: Melepaskan tanggung jawab kelas dari seorang Coach.

### 1.4. Modul Learning Report (Laporan Belajar)
*Terbagi atas dua sisi: Sisi Pelapor (Coach) dan Sisi Reviewer (Admin/Relation)*
- **Bagi Coach (`reports.create`, `reports.update`):**
  - Hanya dapat memilih kelas yang ditugaskan kepadanya.
  - Mengisi form Laporan (Materi pelajaran, aktivitas, catatan/Accident Notes).
  - Melampirkan Dokumentasi Media (Foto/Video) via Cloudinary.
  - Menandai **Absensi** murid per sesi laporan (`present`, `absent`, `sick`, `permission`).
  - Mengedit laporan (hanya jika laporan belum berstatus `approved` atau sedang berstatus `rejected`).
- **Bagi Reviewer (`reports.review`):**
  - Membaca semua laporan berstatus `submitted`.
  - Melakukan `Approve` (Setuju) atau `Reject` (Tolak dengan catatan alasan penolakan).
  - Melihat blok "Accident Notes" jika ada catatan kritis dari Coach.

### 1.5. Modul Absensi & Ekspor (Attendance)
*Dapat diakses secara proporsional oleh Relation, SPV Coach, PIC, Teacher School, dan Finance*
- **Tabel Absensi**: Daftar rekap absensi komprehensif.
- **Data Isolation**: User dengan scope terbatas (seperti PIC/Finance) hanya akan melihat baris absensi dari sekolahnya masing-masing.
- **Ekspor CSV**: Mengunduh rekaman absensi ke CSV yang diproses via *Streaming Download* agar mampu menangani data skala besar.

### 1.6. Modul Dashboard
- **Admin Dashboard**: Menampilkan ringkasan sistem global (jumlah sekolah, coach, laporan perlu di-review).
- **PIC Dashboard**: Menampilkan laporan berstatus *Approved* khusus untuk sekolah yang terafiliasi dengan akun yang sedang login.

---

## 2. Fitur Tambahan & Tersembunyi (Hidden Features)

- **Import/Export Excel**: Menggunakan pustaka pihak ketiga (`rap2hpoutre/fast-excel`) untuk memasukkan ratusan siswa dengan cepat. Ekspor kehadiran langsung digenerate secara *streaming*.
- **AJAX Dynamic Loading (`/api/classes/{class}/students`)**: Digunakan secara *asinkron* ketika Coach memilih sebuah Kelas di form laporan. Sistem backend akan memeriksa hak akses kelas, dan secara instan merespon daftar nama siswa yang akan diisi absennya (tanpa perlu me-reload halaman utama form).
- **Accident Notes**: Catatan kecelakaan/insiden tidak memerlukan tabel terpisah. Jika ada `notes` dalam `reports`, *view* sistem akan otomatis merendernya dengan UI berwarna merah menyolok sebagai peringatan.

---

## 3. Fitur Tertunda / Perlu Keputusan Lebih Lanjut

- **Manajemen Reset Password Mandiri**: Hingga kini *user* tidak dapat mereset password sendiri melalui fitur "Lupa Password" di halaman login; semua harus melewati admin.
- **Cloudinary Media Hard Delete**: Ketika ada laporan/media yang dihapus, koneksi *database* akan hilang (CASCADE), namun foto aslinya di Cloudinary tidak otomatis terhapus (orphan file) karena belum ada penyimpanan `public_id`.
- **WaHa (WhatsApp Notifications) / Phase 13**: Modul notifikasi WA belum diimplementasikan, menunggu penyelesaian isu lingkungan infrastruktur.
