# Learning Report System (LRS)

## Deskripsi Proyek
**Learning Report System** adalah aplikasi berbasis web yang dirancang untuk menjadi sistem manajemen dan pelaporan hasil pembelajaran. Aplikasi ini memfasilitasi pembagian peran (Admin, Pelatih/Coach, dan PIC Sekolah) untuk mencatat, mengelola, serta memonitor progres belajar peserta didik secara terpusat dan efisien.

## Fitur Utama
* **Manajemen Multi-Role:** Akses terpisah dengan antarmuka spesifik untuk Admin (kendali penuh), Coach (pelapor), dan PIC Sekolah (pemantau).
* **Pelaporan Progres Belajar:** Pencatatan materi pembelajaran, ringkasan aktivitas, absensi kehadiran siswa, hingga unggahan bukti foto/video.
* **Manajemen Master Data:** Pengelolaan data terpusat untuk entitas Sekolah, Kelas, Siswa, dan Pelatih.
* **Import/Export Data:** Dukungan impor data daftar siswa secara massal menggunakan format Excel (`fast-excel`).
* **Cloud Media Storage:** Terintegrasi penuh dengan ekosistem Cloudinary untuk penyimpanan berkas gambar dan video yang dinamis.
* **Review Laporan:** Alur persetujuan (_Approve_ / _Reject_) laporan oleh tim Admin sebelum laporan diteruskan ke PIC Sekolah terkait.

## Tech Stack
* **Bahasa Pemrograman:** PHP (8.2 / 8.3), JavaScript
* **Framework:** Laravel 12.0
* **Database:** SQLite (default lokal), terstruktur siap menggunakan MySQL / PostgreSQL.
* **Frontend Assets:** Vite + Laravel Blade
* **Tools Tambahan:** Docker (Containerization)

## Panduan Instalasi Lokal

### Prasyarat Sistem
Pastikan perangkat lunak berikut telah terpasang:
* PHP >= 8.2
* Composer
* Node.js & NPM
* Git

### Langkah-langkah Instalasi
1. **Clone repositori**
   ```bash
   git clone https://github.com/Reee00/learning-report-system.git
   cd learning-report-system
   ```

2. **Jalankan Setup Otomatis (Direkomendasikan)**
   Aplikasi ini telah memuat skrip otomatisasi *setup* di dalam konfigurasi Composer. Anda hanya perlu menjalankan:
   ```bash
   composer setup
   ```
   *(Skrip ini secara otomatis akan mengeksekusi `composer install`, menyalin file `.env.example` ke `.env`, membuat *app key*, menjalankan migrasi database SQLite, serta mengunduh dependensi NPM beserta proses `build`-nya).*

3. **Konfigurasi Tambahan (Cloudinary)**
   Agar fitur _upload_ gambar berjalan sempurna, buka file `.env` dan masukkan API keys dari akun Cloudinary Anda:
   ```env
   CLOUDINARY_CLOUD_NAME=your_cloud_name
   CLOUDINARY_API_KEY=your_api_key
   CLOUDINARY_API_SECRET=your_api_secret
   ```

4. **Jalankan Server Lokal**
   Gunakan perintah _concurrent_ bawaan untuk menjalankan server web dan vite:
   ```bash
   composer dev
   ```
   Aplikasi dapat diakses melalui web browser di URL: `http://localhost:8000`

## Dokumentasi Lengkap & Arsitektur
Informasi terperinci mengenai struktur arsitektur sistem, ERD database, proses otentikasi, serta panduan pengujian (_testing_) dan perilisan (_deployment_) telah kami dokumentasikan secara lengkap.

Anda dapat meninjau referensi _developer_ pada direktori **[`docs/`](/docs)** yang terdapat pada _root_ proyek ini.

---
*Dibangun dengan menggunakan framework [Laravel](https://laravel.com/).*
