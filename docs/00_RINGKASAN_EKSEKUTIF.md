# 📊 Ringkasan Eksekutif: Learning Report System

## Deskripsi Singkat Sistem
**Learning Report System** adalah aplikasi web berbasis Laravel yang dirancang untuk mengelola sistem pelaporan pembelajaran di institusi pendidikan. Sistem ini memungkinkan coach (pengajar) untuk membuat laporan pembelajaran, admin untuk mengsetujui laporan, dan PIC sekolah untuk melihat laporan yang telah disetujui.

---

## 🎯 Tujuan Bisnis
1. **Sentralisasi Pelaporan**: Menyediakan platform terpusat untuk pencatatan laporan pembelajaran
2. **Workflow Approval**: Memastikan setiap laporan melalui proses persetujuan dari admin
3. **Transparansi Sekolah**: Memberikan akses kepada PIC sekolah untuk melihat laporan pembelajaran
4. **Manajemen Media**: Memungkinkan upload foto dan video sebagai bukti pembelajaran
5. **Tracking Siswa**: Mencatat kehadiran siswa dalam setiap laporan pembelajaran

---

## 👥 Pengguna Sistem

| Role | Deskripsi | Akses |
|------|-----------|-------|
| **Admin** | Pengelola sistem | Dashboard, Manajemen pengguna, Sekolah, Kelas, Coach, Persetujuan laporan |
| **Coach** | Pengajar/Instruktur | Membuat laporan, Upload media, Submit laporan, Edit draft |
| **School PIC** | Penanggung Jawab Sekolah | Melihat laporan sekolah yang sudah disetujui |

---

## 🛠️ Stack Teknologi
- **Backend**: Laravel 12.0 (PHP 8.3)
- **Database**: MySQL / PostgreSQL (SQLite untuk local)
- **Frontend**: Blade Templates + Vite
- **Storage Media**: Cloudinary (cloud storage)
- **Import/Export**: FastExcel (Excel/CSV)
- **Deployment**: Docker, Railway

---

## 📊 Data Utama Sistem
- **Schools**: Sekolah-sekolah yang menggunakan sistem
- **Classes**: Kelas-kelas di sekolah
- **Students**: Daftar siswa di setiap kelas
- **Users**: Admin, Coach, School PIC
- **Reports**: Laporan pembelajaran dari coach
- **Report Attendances**: Kehadiran siswa dalam laporan
- **Report Media**: Foto dan video dalam laporan

---

## 🔄 Alur Utama Aplikasi

### Alur Coach Membuat Laporan
1. Coach login → Dashboard Coach
2. Buat laporan baru → Pilih kelas
3. Isi detail: Tanggal, Materi, Ringkasan Aktivitas, Catatan
4. Catat kehadiran siswa
5. Upload foto dan video (max 10 foto, 3 video)
6. Submit laporan → Status: "submitted"

### Alur Admin Review Laporan
1. Admin login → Dashboard Admin
2. Lihat daftar laporan yang perlu di-review
3. Lihat detail laporan + foto/video
4. **Approve** → Laporan siap dilihat oleh School PIC
5. **Reject** → Kembali ke draft, Coach bisa edit ulang

### Alur School PIC Melihat Laporan
1. School PIC login → Dashboard PIC
2. Lihat laporan yang sudah disetujui admin
3. Filter berdasarkan kelas dan tanggal
4. Lihat detail laporan + bukti foto/video

---

## 🔐 Keamanan
- **Authentication**: Session-based dengan model User
- **Authorization**: RoleMiddleware untuk kontrol akses berbasis role
- **Password Hashing**: BCrypt
- **CSRF Protection**: Built-in Laravel
- **Media Security**: Cloudinary untuk storage aman

---

## 📈 Fitur Utama

### ✅ Fitur Implementasi Penuh
- ✓ Manajemen user (Admin, Coach, School PIC)
- ✓ Master data (Sekolah, Kelas, Siswa)
- ✓ Pembuatan laporan pembelajaran oleh coach
- ✓ Workflow approval (Submit → Review → Approve/Reject)
- ✓ Upload media (foto dan video)
- ✓ Catat kehadiran siswa
- ✓ Filter dan pencarian laporan
- ✓ Dashboard untuk setiap role
- ✓ Import siswa dari Excel

### ⏳ Fitur Dalam Progress atau Future
- NOT FOUND IN CODEBASE: Sistem notifikasi real-time
- NOT FOUND IN CODEBASE: Export laporan ke PDF
- NOT FOUND IN CODEBASE: Statistik pembelajaran per siswa
- NOT FOUND IN CODEBASE: Analytics dashboard

---

## 📱 Antarmuka Utama

### Halaman untuk Admin
- Dashboard (Overview statistik)
- Manajemen User (CRUD)
- Manajemen Sekolah (CRUD)
- Manajemen Kelas (CRUD)
- Manajemen Coach + Assignment Kelas
- Review Laporan (Approve/Reject)

### Halaman untuk Coach
- Dashboard (Daftar laporan)
- Buat Laporan Baru
- Edit Laporan Draft
- Upload Media

### Halaman untuk School PIC
- Dashboard (Laporan tersetujui)
- Lihat Detail Laporan
- Filter Laporan

---

## 🚀 Deployment
- **Hosting**: Railway / VPS
- **Container**: Docker
- **Database**: Managed database (MySQL/PostgreSQL)
- **Media**: Cloudinary
- **Environment**: Production (APP_ENV=production)

---

## 📚 Dokumentasi Lengkap
Lihat folder `/docs` untuk dokumentasi detail mengenai:
- Folder Structure
- Database Schema & ERD
- API Endpoints
- Authentication & Authorization
- Business Processes
- Installation & Setup
- Deployment Guide
- Dan banyak lagi...

---

## 📝 Catatan Penting
- Sistem menggunakan session berbasis database
- Media disimpan di Cloudinary (HTTPS URLs)
- Laporan memiliki status workflow: draft → submitted → approved/rejected
- Admin dapat me-reset password user
- Coach hanya bisa edit laporan dengan status "draft" atau "rejected"
- School PIC hanya melihat laporan dengan status "approved"

