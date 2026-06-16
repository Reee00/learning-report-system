# Dokumen 12 - Keamanan, Pemeliharaan, dan Pengembangan ke Depan

Dokumen ini menjelaskan profil keamanan, strategi perawatan, dan rekomendasi peningkatan arsitektur untuk masa depan.

## 1. Catatan Keamanan (Security Notes)

### 1.1. Proteksi Autentikasi dan Sesi
*   **Password Hashing:** Sandi disimpan dalam basis data menggunakan algoritma hashing modern (Bcrypt dengan _rounds_ 12), diatur dalam konfigurasi bawaan Laravel.
*   **Manajemen Sesi:** Dikelola penuh oleh sistem `Illuminate\Session`. Jika aplikasi menargetkan beban tinggi, migrasi _session_ ke `database` atau `redis` disarankan untuk menjaga keutuhan sesi antar replikasi kontainer.
*   **Otorisasi Role-Based:** Akses antar modul disegregasi menggunakan Middleware. Misalnya, modul persetujuan laporan hanya dapat diinisiasi oleh _controller_ di dalam rute yang dilindungi oleh `role:admin`.

### 1.2. Proteksi Kerentanan Umum
*   **CSRF Protection:** Setiap form mutasi data (`POST`, `PUT`, `PATCH`, `DELETE`) dilindungi token `@csrf`.
*   **XSS Protection:** Penggunaan Laravel Blade `{{ $variable }}` secara otomatis melakukan *escaping* terhadap entitas HTML, mencegah *Cross-Site Scripting*.
*   **SQL Injection:** Penggunaan Eloquent ORM memastikan setiap parameter dari input _user_ menggunakan *PDO parameter binding* secara internal.

---

## 2. Panduan Pemeliharaan (Maintenance Guide)

### 2.1. Backup Strategi
Meskipun script _backup_ spesifik belum ditemukan di _codebase_, berikut adalah rekomendasi prosedur pencadangan (_backup_):
*   **Database Backup:** Cadangkan `database/database.sqlite` (jika menggunakan SQLite) secara berkala, atau buat konfigurasi _cron job_ `pg_dump`/`mysqldump` jika menggunakan _Relational Database Management System_ (RDBMS).
*   **Media Backup:** Aset media yang diunggah secara lokal (di `storage/app/public`) wajib di-_backup_ menggunakan rsync atau S3 bucket sinkronisasi. Jika telah terintegrasi penuh menggunakan **Cloudinary**, prosedur pencadangan ini ditangani sepenuhnya oleh pihak ketiga.

### 2.2. Mode Perawatan (Maintenance Mode)
Jika terdapat proses migrasi database masif atau pembaruan kerangka kerja, aplikasi dapat dikunci sementara dengan perintah:
```bash
php artisan down --secret="kode-rahasia"
```
Aplikasi akan menampilkan halaman _Maintenance_. Administrator tetap bisa mengakses dengan menavigasi ke `https://lrs.domain.com/kode-rahasia`.

---

## 3. Peningkatan di Masa Depan (Future Improvements)

Sistem telah memiliki kerangka struktural yang baik, namun ada beberapa titik penyempurnaan potensial:
1.  **Soft Deletes:** Implementasi `SoftDeletes` pada Model (_User_, _School_, _Report_) untuk menghindari kehilangan riwayat rekam jejak apabila data terhapus tanpa sengaja oleh Admin.
2.  **Laporan Berkala / Export Data:** Integrasi fitur `fast-excel` secara mendalam di menu PIC Sekolah agar dapat mengekspor rekap bulanan laporan seluruh kelas di sekolahnya.
3.  **Realtime Notifications:** Memanfaatkan _database queue_ atau pusher (`BROADCAST_CONNECTION`) untuk memberi notifikasi sistem bagi _Coach_ seketika saat Admin menolak (Reject) laporan mereka.
4.  **Logging & Audit Trail:** Mengimplementasi paket _activity-log_ untuk mencatat setiap intervensi Admin (misal: "Admin X menyetujui Laporan Y" atau "Admin Z mengubah password Coach A").
