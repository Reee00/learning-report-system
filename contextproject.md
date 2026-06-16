# Learning Report System

## 1. Nama & Deskripsi Proyek
**Learning Report System** adalah aplikasi berbasis web yang berfungsi sebagai sistem manajemen dan pelaporan hasil pembelajaran. Aplikasi ini ditujukan untuk mempermudah pencatatan, pengelolaan, dan pelaporan progres belajar peserta didik.

## 2. Tech Stack
* **Bahasa Pemrograman:** PHP (versi 8.2 / 8.3) dan JavaScript.
* **Framework Backend:** Laravel 12.0.
* **Database:** SQLite (default pada *local environment*), mendukung konfigurasi MySQL atau PostgreSQL (terdapat dependensi ekstensi pada Dockerfile).
* **Tools & Integrasi Tambahan:**
  * **Cloudinary:** Digunakan untuk penyimpanan aset media/gambar (*cloud storage*).
  * **Fast-Excel:** Digunakan untuk fitur ekspor dan impor data laporan menggunakan format Excel.
  * **Vite:** Sebagai *module bundler* untuk aset antarmuka (*frontend*).
  * **Docker:** Terdapat konfigurasi kontainer untuk mendukung kemudahan dalam *deployment* aplikasi.

## 3. Arsitektur & Struktur Folder
Proyek ini mengadopsi arsitektur standar **MVC (Model-View-Controller)** bawaan framework Laravel. Berikut adalah direktori-direktori penting di dalam proyek ini:
* `app/`: Inti dan *logic* utama aplikasi. Disinilah *Controllers*, *Models*, dan *Middleware* disimpan.
* `routes/`: Tempat mendefinisikan seluruh jalur URL (seperti `web.php` dan `api.php`).
* `database/`: Mengelola struktur dan pengisian data, berisi file *migrations*, *seeders*, dan *factories*. Secara default, file database SQLite (`database.sqlite`) akan dibuat di folder ini.
* `resources/`: Menyimpan file tampilan berbasis *Blade templates* (`resources/views`), serta aset *raw* *CSS* dan *JavaScript*.
* `public/`: Direktori *root* untuk web server (seperti Apache/Nginx), menyimpan *entry point* utama `index.php` beserta aset (gambar/JS/CSS) yang dapat diakses publik.
* `config/`: Seluruh konfigurasi aplikasi (*database*, integrasi Cloudinary, *cache*, sistem log, dll).
* `tests/`: Tempat untuk menyimpan skrip otomatisasi pengujian (menggunakan PHPUnit).

## 4. Cara Menjalankan (Setup)
Berikut adalah langkah-langkah singkat untuk menjalankan proyek ini pada *local environment*:

**Prasyarat:** Pastikan **PHP (>= 8.2)**, **Composer**, **Node.js**, dan **NPM** telah terpasang di sistem Anda.

1. **Buka Terminal & Masuk ke Direktori Proyek:**
   ```bash
   cd learning-report-system
   ```
2. **Jalankan Setup Otomatis (opsional namun direkomendasikan):**
   Proyek ini telah menyediakan sebuah skrip setup komprehensif di dalam `composer.json`. Anda cukup menjalankan perintah:
   ```bash
   composer setup
   ```
   *(Perintah ini secara otomatis akan menjalankan `composer install`, menduplikasi file `.env`, *generate app key*, *migrate database*, serta meng-install dan *build* NPM).*

**Atau Langkah Manual (jika tidak menggunakan `composer setup`):**
1. **Install dependensi Composer & Node Modules:**
   ```bash
   composer install
   npm install
   ```
2. **Konfigurasi Environment Variable:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3. **Konfigurasi Database SQLite & Migrasi:**
   Buat file database kosong dan lakukan migrasi tabel:
   ```bash
   touch database/database.sqlite
   php artisan migrate
   ```
4. **Jalankan Server Lokal & Vite:**
   Proyek ini juga menyediakan *command* gabungan untuk menjalankan server Laravel, *queue*, *logs*, dan *Vite* secara bersamaan:
   ```bash
   composer dev
   ```

Aplikasi sekarang sudah dapat diakses melalui browser di **`http://localhost:8000`**.
