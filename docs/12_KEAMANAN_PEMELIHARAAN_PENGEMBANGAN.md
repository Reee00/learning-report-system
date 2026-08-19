# Dokumen 12 - Keamanan, Pemeliharaan, dan Pengembangan ke Depan

**Terakhir diperbarui:** Sesuai dengan status root project LRS terbaru.

Dokumen ini memaparkan postur keamanan yang aktif diimplementasikan, strategi operasional database, serta target penyempurnaan di siklus pengembangan (Phase) berikutnya.

---

## 1. Catatan Keamanan (Security Notes)

### 1.1. Proteksi Autentikasi dan Otorisasi Terpusat
*   **Password Hashing:** Sandi pengguna diregistrasikan ke database murni menggunakan enkripsi kriptografis satu arah (Bcrypt, default Laravel).
*   **Capability-Based Authorization (RBAC):** Tidak seperti sistem lama yang menggunakan *hardcoded string checking* (`if role == admin`), aplikasi mutakhir berjalan pada perizinan *capability* (seperti `schools.view`, `attendance.export`) yang dipetakan ketat pada 7 layer hak akses di dalam `AuthorizationService.php`.
*   **Data Scoping (Multi-Tenant-like):** Otorisasi tidak hanya membatasi halaman, tetapi menjangkau query. Data laporan dan absensi direduksi berdasarkan `accessibleSchoolIds()`. Jika user berstatus *school-scoped* (contoh: Finance, PIC), injeksi filter akan secara dinamis menyaring agar tidak terjadi kebocoran (cross-data leak) dengan institusi sekolah lain.

### 1.2. Proteksi Kerentanan Fundamental (OWASP)
*   **CSRF Protection:** Segala rute mutasi (`POST`, `PUT`, `DELETE`) secara bawaan dikunci oleh verifikator `@csrf` Laravel Middleware.
*   **Mass Assignment Protection:** Seluruh operasi *Eloquent Model* mendikte atribut mana saja yang boleh dimanipulasi massal melalui `$fillable`, guna mencegah paramater-injecting.
*   **Transaction Lock:** Pembuatan laporan, baris anak absensi, dan *upload media* diikat serentak dalam blok `DB::transaction()`. Jika interupsi terjadi (misal koneksi server mati di tengah upload Cloudinary), basis data secara absolut akan me-`rollback` baris laporan yang nanggung, demi merawat ketiadaan tabel-tabel terpisah yang malfungsi (Mencegah BUG-012 berulang).

---

## 2. Panduan Pemeliharaan (Maintenance Guide)

### 2.1. Integritas Relasi (Foreign Key Guard)
*   **Tidak Adanya Soft Deletes:** Arsitektur saat ini tidak mengusung soft deletes. Model relasional dibangun di atas `CASCADE` untuk anak tabel (misal: Hapus Kelas akan menghapus semua Siswa di kelas tersebut).
*   **RESTRICT Policy (NO ACTION):** Khusus pada entitas Laporan, database dilarang secara kaku (`NO ACTION`) untuk ikut hancur apabila entitas pengampu (sekolah, kelas, coach) dihapus. Hal ini karena esensi rekam belajar historis tidak boleh hilang. Seluruh upaya *destroy* sekolah yang memiliki rapor harus ditangani mandiri terlebih dulu oleh Admin (memindahkannya, atau membatalkan niat penghapusan).

### 2.2. Strategi Pencadangan (Backup)
*   **Database:** Penggunaan SQLite memudahkan transfer, backup, dan duplikasi hanya dengan menyalin satu *file* fisik `database.sqlite`. Gunakan rotasi cron (misal: `cp database.sqlite database.sqlite.bak-$(date)`).
*   **Media Terpusat Cloudinary:** Media berharga (foto, video bukti belajar) bertempat di infrastruktur mandiri milik Cloudinary. Pencadangan atau reduksi memori dilakukan pada dashboard pihak ketiga, yang melepaskan beban *hosting* dari server aplikasi web.

---

## 3. Peta Pengembangan (Roadmap & Peningkatan Masa Depan)

Berdasarkan status terkini (*Codebase Phase 11-12*), terdapat sejumlah *Tech Debt* dan wacana penambahan modul kritis:

1.  **Pembersihan Orfan Media (MEDIA-001):**
    Menambahkan migrasi kolom `cloudinary_public_id` ke dalam tabel `report_media`. Tujuannya agar saat baris `report_media` dihapus pada MySQL/SQLite, sistem Laravel dapat mengirimkan REST request ke Cloudinary API untuk men-*destroy* file aslinya, sehingga storage eksternal tidak digerogoti oleh file 'hantu' yang sudah tak terpakai.

2.  **Notifikasi WhatsApp Otomatis (WaHa / Phase 13):**
    Mengintegrasikan modul asinkron pengirim chat berbasis WA. Modul ini bertujuan menembakkan pesan singkat secara *real-time* kepada *Coach* ketika laporannya ditolak (*Rejected*) agar pelatih menyadari perlunya revisi secepat mungkin.

3.  **Lupa Password Mandiri (User Self-Reset):**
    Saat ini flow reset token masih ditangani via kapabilitas admin (Console Reset). Modul email standar Laravel (SMTP) akan dieksplorasi di fase lanjutan agar para Coach bisa meminta token reset mandiri ke surel (email) masing-masing.

4.  **Logging Histori Audit Lengkap:**
    Memanfaatkan library seperti *spatie/laravel-activitylog* guna merekam aktivitas non-operasional (Siapa yang *login*, jam berapa; Admin mana yang telah menghapus sekolah X, pada hari apa). Dipercaya dapat meningkatkan akuntabilitas internal tim SuperAdmin.
