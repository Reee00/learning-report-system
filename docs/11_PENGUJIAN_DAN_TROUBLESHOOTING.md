# Dokumen 11 - Panduan Pengujian & Troubleshooting

**Terakhir diperbarui:** Sesuai dengan status root project LRS terbaru.

## 1. Dokumentasi Pengujian (Testing Documentation)

### 1.1. Pengujian Integritas Data Master
Proyek ini mengadopsi standar pengujian integritas struktural yang sangat ketat menggunakan *pest/phpunit*. Audit integritas dijalankan secara berkala pada Master Data:
*   **Perintah untuk menjalankan test:**
    ```bash
    php artisan test
    ```
*   **Fokus Uji Utama (MasterDataIntegrityTest):**
    Sistem mengecek 16 parameter audit (termasuk ketiadaan orphan data di `classes`, `students`, kesesuaian nilai enum `users.role`, kewajiban plotting sekolah untuk role tertentu, dan tidak adanya *submitted report* dengan jumlah presensi 0).

### 1.2. Pengujian Kotak Hitam (Black Box Testing)
Skenario krusial yang diwajibkan untuk diuji manual (UAT):
1.  **Pengujian Autentikasi & Routing:**
    *   Login menggunakan kredensial salah (harus ditolak).
    *   Login dengan tiap role (terdapat 7 role) dan amati lokasi _redirect_ otomatis. Pastikan SuperAdmin menuju `/admin/dashboard`, sedangkan Teacher School menuju `/attendance`.
    *   Uji coba penerobosan akses (seperti Coach mengetik manual rute `/admin/schools`). Harus terblokir HTTP 403 Forbidden berkat `AuthorizationService`.
2.  **Pengujian CRUD & Isolasi Data:**
    *   Buat sekolah baru, tambahkan kelas, masukkan siswa (via form manual dan form import *Fast-Excel*).
    *   Buka dashboard PIC/Teacher School. Pastikan mereka **tidak bisa** melihat data laporan atau presensi dari sekolah lain di tabel mereka (Data Isolation).
3.  **Pengujian Alur Pelaporan:**
    *   Buka akun Coach. Pastikan daftar kelas yang muncul di form laporan hanya kelas yang ditugaskan (*assigned*) kepadanya.
    *   Simpan laporan. Uji fungsionalitas unggah foto Cloudinary. Pastikan baris data masuk ke 3 tabel (`reports`, `report_media`, `report_attendances`).
    *   Buka akun Relation/Admin. Lakukan `Reject` pada laporan.
    *   Kembali ke akun Coach, pastikan Coach kini dapat melakukan *Edit Laporan* (sistem membuka kunci edit saat status = `rejected`).
4.  **Pengujian Presensi & Ekspor:**
    *   Buka rute `/attendance` dengan role Finance. Pastikan tabel berisi murid-murid.
    *   Lakukan *Export CSV*. Perhatikan bahwa unduhan harus mengembalikan file dengan MIME CSV yang berjalan menggunakan *StreamedResponse*.

---

## 2. Panduan Troubleshooting (Troubleshooting Guide)

Apabila administrator mengalami kendala lapangan, berikut adalah mitigasi spesifik sesuai konfigurasi _source code_ terbaru.

### 2.1. Laporan Tidak Muncul di List Setelah Disubmit (BUG-012)
**Gejala:** Coach mengaku sudah mengirim laporan, namun admin tidak melihat laporan tersebut di status "Submitted".
**Analisis:**
*   **Akar Masalah (Data Anomaly):** Dulu, jika Cloudinary gagal mengembalikan URL setelah tabel utama di-`insert`, aplikasi mati sebelum baris presensi dibuat. Hasilnya: Laporan "Submitted" dengan 0 baris presensi.
*   **Solusi:** Sejak pembaruan terbaru, controller telah dibungkus `DB::transaction()`. Namun, jika data anomalinya berasal dari masa lampau, gunakan UI/Database untuk mereset status ke `draft` atau memperbaikinya. Audit test (Aturan #16) akan mendeteksi baris rusak ini.

### 2.2. Error Foreign Key Constraint Saat Hapus Sekolah/Kelas
**Gejala:** Admin menekan hapus sekolah, tapi muncul *SQLSTATE[23000] Integrity constraint violation* (HTTP 500).
**Analisis:**
*   **Kondisi Sistem:** Tabel `reports` menolak *Cascade Delete* secara sengaja (`NO ACTION`). Laporan dianggap sebagai riwayat historis mutlak.
*   **Solusi Normal:** Sistem sudah diperbarui untuk memberikan peringatan HTTP 422 atau pesan kesalahan (*"Sekolah tidak bisa dihapus karena masih memiliki laporan"*). Jika masih menjumpai HTTP 500, pastikan controller (seperti `SchoolController@destroy`) memanggil validasi `if ($school->reports()->exists())`. Solusinya adalah menghapus laporannya terlebih dahulu, atau biarkan sekolah tersebut (*do not delete*).

### 2.3. Gambar Cloudinary Tidak Terhapus (MEDIA-001)
**Gejala:** Saat menghapus sebuah laporan, foto di penyimpanan Cloudinary tidak hilang dan memakan *quota*.
**Analisis:**
*   Tabel `report_media` menyimpan *url*, tetapi tidak memiliki kolom `cloudinary_public_id`. Fungsi hapus tidak memanggil API pemusnahan Cloudinary. Ini memang desain keterbatasan saat ini (sebagai ISSUE-015). Media tersebut terhitung *Orphan*.

### 2.4. Masalah Ekspor CSV Memori Penuh
**Gejala:** Halaman `/attendance/export` menampilkan layar putih atau Error "Allowed memory size of X bytes exhausted".
**Analisis:**
*   Pola export *chunkById* telah dimplementasikan. Jika masalah ini tetap terjadi, pastikan modul *fast-excel* digunakan sesuai instruksi (menggunakan generator/yield) atau metode Eloquent `chunk()` diterapkan benar. (Menaikkan memory_limit bukan solusi jangka panjang).

### 2.5. Kendala Impor Excel (Nama Siswa Tidak Masuk)
**Gejala:** Proses berhasil, namun jumlah terbaca 0 siswa.
**Analisis:**
*   Template Excel menuntut kepala kolom (header baris pertama) bernama eksplisit `nama_siswa` atau `name` atau `Nama Siswa`. Jika penamaannya melenceng (misal: "NAMA LENGKAP ANAK"), library tidak akan membaca baris tersebut. Solusi: Minta pengguna mengunduh template (`/students/template`).
