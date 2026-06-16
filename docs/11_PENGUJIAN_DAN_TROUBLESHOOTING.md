# Dokumen 11 - Panduan Pengujian & Troubleshooting

## 1. Dokumentasi Pengujian (Testing Documentation)

### 1.1. Pengujian Otomatis (Automated Testing)
Proyek ini mengadopsi standar pengujian dari kerangka kerja Laravel (PHPUnit/Pest). Konfigurasi tes terdapat pada file `phpunit.xml` dan skrip yang dapat dijalankan berada di folder `tests/`.
*   **Perintah untuk menjalankan test:**
    ```bash
    php artisan test
    ```
    atau menggunakan spesifik filter:
    ```bash
    php artisan test --filter ReportControllerTest
    ```

### 1.2. Pengujian Kotak Hitam (Black Box Testing)
Skenario penting yang harus diuji secara manual melalui UI:
1.  **Pengujian Autentikasi:**
    *   Login menggunakan kredensial salah (harus gagal).
    *   Login sebagai `coach` dan mencoba mengakses URL `/admin/dashboard` (harus tertolak oleh middleware `role:admin`).
2.  **Pengujian CRUD Master Data:**
    *   Menambah Sekolah dan memastikan PIC terkait dapat melihat data sekolah tersebut.
    *   Melakukan import Excel daftar siswa (uji format benar dan format salah/ekstensi salah).
3.  **Pengujian Alur Laporan:**
    *   _Coach_ membuat laporan baru dan mengunggah gambar/video. Pastikan aset masuk ke Cloudinary jika dikonfigurasi, atau tersimpan di `storage/app/public` secara lokal.
    *   Pastikan status awal laporan adalah `pending`.
    *   _Admin_ membuka laporan dan menekan `Reject`. Pastikan status berubah dan _Coach_ menerima notifikasi / melihat status ditolak.
4.  **Pengujian API Ajax:**
    *   Akses `/api/classes/{id}/students` menggunakan REST Client (seperti Postman) dengan menyertakan *cookie session*. Pastikan JSON berisi id dan nama siswa.

---

## 2. Panduan Troubleshooting (Troubleshooting Guide)

Apabila developer atau administrator mengalami kendala, berikut panduan mitigasi masalah berdasarkan konfigurasi _source code_.

### 2.1. Kendala Autentikasi & Sesi
**Gejala:** User sering _logout_ secara tiba-tiba atau tidak bisa login.
**Analisis:**
*   Aplikasi menggunakan driver sesi file (dari `.env`: `SESSION_DRIVER=file`). Pastikan *permissions* pada folder `storage/framework/sessions` dapat ditulis (775).
*   Jika di *deployment* menggunakan *load balancer* tanpa *sticky sessions*, ubah `SESSION_DRIVER` ke `database` atau `redis`. (Sistem sudah memiliki tabel `sessions`).

### 2.2. Error Upload File / Gambar Tidak Muncul
**Gejala:** Laporan gagal disimpan saat ada lampiran gambar, atau gambar rusak (_broken image_).
**Analisis:**
*   Jika menggunakan sistem lokal, jalankan perintah `php artisan storage:link`.
*   Jika menggunakan **Cloudinary**, pastikan kredensial di `.env` sudah benar:
    *   `CLOUDINARY_CLOUD_NAME`
    *   `CLOUDINARY_API_KEY`
    *   `CLOUDINARY_API_SECRET`
*   Pastikan batas ukuran _upload_ di konfigurasi PHP (`upload_max_filesize` dan `post_max_size`) di `php.ini` atau `Dockerfile` sudah memadai.

### 2.3. Masalah Import Excel
**Gejala:** Class `FastExcel` tidak ditemukan atau import gagal dengan pesan *Allowed memory size exhausted*.
**Analisis:**
*   Pastikan dependensi telah di-install menggunakan `composer install`.
*   Jika file excel berukuran terlalu masif, *Fast-Excel* membutuhkan PHP dengan *memory_limit* yang memadai.

### 2.4. Database SQL Error (Constraint Violation)
**Gejala:** Terjadi "Foreign key constraint failed" saat menghapus kelas atau sekolah.
**Analisis:**
*   Sistem menerapkan _foreign keys_ (contoh: laporan tertaut ke kelas, siswa tertaut ke kelas). Pastikan _soft deletes_ diaktifkan atau data anak telah ditangani (misal: di-set `onDelete('cascade')` pada migration).
