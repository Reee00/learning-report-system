# Dokumen 09 - Dokumentasi Fitur dan Modul

Dokumen ini memuat analisis fitur dan modul yang ditemukan berdasarkan *source code* (terutama dari struktur _routing_ di `routes/web.php` dan _controllers_).

## 1. Fitur Berdasarkan Role (Pengguna)

### 1.1. Modul Admin (Role: `admin`)
Admin memiliki akses penuh terhadap data master dan manajemen laporan di dalam sistem.
*   **Dashboard Admin:** Menampilkan ringkasan sistem.
*   **Manajemen Akun (User Management):**
    *   Melihat daftar pengguna (`users.index`).
    *   Menambah pengguna baru (`users.store`).
    *   Mengubah data pengguna (`users.update`).
    *   Mereset password pengguna (`users.reset-password`).
    *   Menghapus pengguna (`users.destroy`).
*   **Manajemen Master Data:**
    *   Manajemen Sekolah (`schools`).
    *   Manajemen Kelas (`classes`).
    *   Manajemen Siswa (dalam kelas) termasuk _import_ dan _download template_.
*   **Manajemen Pelatih (Coach):**
    *   Melihat daftar pelatih (`coaches.index`).
    *   Melihat detail pelatih (`coaches.show`).
    *   Menugaskan pelatih ke kelas tertentu (`coaches.assign`).
    *   Membatalkan penugasan pelatih (`coaches.unassign`).
*   **Manajemen Laporan LRP (Learning Report):**
    *   Melihat semua laporan (`reports.index`, `reports.show`).
    *   Menyetujui laporan (`reports.approve`).
    *   Menolak laporan (`reports.reject`).

### 1.2. Modul Pelatih / Coach (Role: `coach`)
Pelatih bertanggung jawab atas kegiatan mengajar dan pelaporan.
*   **Manajemen Laporan Pembelajaran:**
    *   Melihat daftar laporannya sendiri (`reports.index`).
    *   Membuat laporan baru (`reports.create`, `reports.store`).
    *   Mengubah laporan yang belum disetujui (`reports.edit`, `reports.update`).
*   **Akses Data Siswa:**
    *   Menarik data siswa berdasarkan kelas via API (`/api/classes/{class}/students`) untuk absensi/penilaian.

### 1.3. Modul PIC Sekolah (Role: `school_pic`)
PIC Sekolah adalah perwakilan dari pihak sekolah yang memonitor progres siswa.
*   **Dashboard PIC:** Menampilkan ringkasan untuk sekolah yang bersangkutan (`dashboard`).
*   **Akses Laporan:** Hanya dapat melihat detail laporan yang terkait dengan sekolah mereka (`reports.show`).

## 2. Fitur Tambahan & Tersembunyi (Hidden Features)
*   **Import Excel Siswa:** Terdapat fitur untuk melakukan _bulk import_ data siswa menggunakan template excel (`students.import` dan `students.template`).
*   **API Load Students:** Terdapat _endpoint_ API (`/api/classes/{class}/students`) yang digunakan secara internal (AJAX) untuk mengambil daftar siswa secara asinkron tanpa *reload* halaman saat Coach memilih kelas di form laporan.

## 3. Fitur yang Belum Selesai (Unfinished Features)
*   **NOT FOUND IN CODEBASE:** Berdasarkan *source code*, tidak terlihat fitur _draft_ (menyimpan sementara) laporan bagi _Coach_ (hanya _store_ dan _update_ yang tersedia). Selain itu, tidak ditemukan manajemen reset password secara mandiri oleh user (semua harus melalui admin).
