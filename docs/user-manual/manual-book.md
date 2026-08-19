# USER MANUAL BOOK
## Learning Report System

---

## 1. Identitas Dokumen
**Nama Sistem:** Learning Report System (LRS)
**Fungsi Utama:** Sistem Manajemen dan Pelaporan Hasil Pembelajaran
**Target Pengguna:** SuperAdmin, Relation, SPV Coach, Coach, PIC DK SCHOOL, Teacher School, Finance

---

## 2. Daftar Isi
1. Identitas Dokumen
2. Daftar Isi
3. Tentang Sistem
4. Tujuan Sistem
5. Gambaran Umum Sistem
6. Role & Hak Akses
7. Persiapan Penggunaan
8. Login & Logout
9. Panduan SuperAdmin
10. Panduan Relation
11. Panduan SPV Coach
12. Panduan Coach
13. Panduan PIC DK SCHOOL
14. Panduan TEACHER SCHOOL
15. Panduan Finance
16. Panduan Coach Report
17. Panduan Attendance
18. Panduan Export
19. Panduan Accident Notes
20. Status & Workflow Sistem
21. Error Handling / Troubleshooting
22. FAQ
23. Best Practices Penggunaan
24. Glossary
25. Catatan Implementasi Aktual

---

## 3. Tentang Sistem
**Learning Report System** adalah aplikasi berbasis web yang digunakan untuk mengelola data sekolah, siswa, program kelas, dan aktivitas pembelajaran. Sistem ini memungkinkan para pengajar (Coach) untuk mencatat kehadiran siswa dan mengirimkan laporan kegiatan belajar secara digital lengkap dengan foto/video, yang kemudian akan diperiksa dan disetujui oleh tim manajemen.

---

## 4. Tujuan Sistem
Sistem ini bertujuan untuk:
- Memudahkan Coach dalam membuat laporan pembelajaran (Coach Report).
- Memudahkan pihak manajemen (Relation, SPV Coach, SuperAdmin) memantau aktivitas dan kehadiran (Attendance) di semua lokasi.
- Memberikan transparansi laporan secara *real-time* kepada pihak sekolah (PIC DK SCHOOL, Teacher School).
- Sentralisasi data sekolah, siswa, jadwal, dan laporan agar tersusun rapi.

---

## 5. Gambaran Umum Sistem
Sistem ini membagi pekerjaan menjadi beberapa tahapan utama:
1. **Persiapan Data**: Relation atau SuperAdmin menambahkan data sekolah, program kelas, coach, dan siswa.
2. **Penugasan**: SPV Coach atau Admin menugaskan Coach ke kelas-kelas tertentu.
3. **Pembelajaran**: Coach masuk kelas, mengajar, mengisi absensi (Attendance), dan membuat Laporan (Coach Report).
4. **Pemeriksaan Laporan**: Tim Relation memeriksa laporan dari Coach. Jika bagus akan disetujui (Approve), jika kurang sesuai akan ditolak (Reject) untuk diperbaiki.
5. **Pemantauan Pihak Sekolah**: PIC DK SCHOOL dan Teacher School masuk ke sistem untuk melihat laporan (yang sudah disetujui) dan kehadiran murid-murid di sekolah mereka.

---

## 6. Role & Hak Akses
Sistem ini memiliki 7 hak akses utama (Role) dengan perbedaan sebagai berikut:

| Fitur / Hak | SuperAdmin | Relation | SPV Coach | Coach | PIC DK SCHOOL | TEACHER SCHOOL | Finance |
| --- | :---: | :---: | :---: | :---: | :---: | :---: | :---: |
| Akses Seluruh Sekolah | Ya | Ya | Ya | Tidak (Hanya Kelasnya) | Tidak (Sesuai Plotting) | Tidak (Sesuai Profil) | Ya |
| Kelola Akun/User | Ya | Tidak | Tidak | Tidak | Tidak | Tidak | Tidak |
| Kelola Sekolah & Siswa | Ya | Ya | Tidak | Tidak | Tidak | Tidak | Tidak |
| Kelola/Assign Coach | Ya | Tidak | Ya | Tidak | Tidak | Tidak | Tidak |
| Buat Coach Report | Tidak | Tidak | Tidak | Ya | Tidak | Tidak | Tidak |
| Approve/Reject Report | Ya | Ya | Tidak | Tidak | Tidak | Tidak | Tidak |
| Lihat Attendance | Semua | Semua | Semua | Kelas Sendiri | Sekolahnya | Sekolahnya | Semua |
| Export Attendance CSV | Ya | Ya | Ya | Tidak | Ya (Sekolahnya) | Ya (Sekolahnya) | Ya |

---

## 7. Persiapan Penggunaan
- Pastikan Anda menggunakan browser web modern (Google Chrome, Mozilla Firefox, Safari, atau Microsoft Edge).
- Pastikan komputer atau HP Anda terhubung dengan internet yang stabil, terutama saat mengunggah foto/video di Coach Report.
- Dapatkan Alamat Web (URL) sistem dan *Username (Email)* serta *Password* dari administrator Anda.

---

## 8. Login & Logout

### Cara Login:
1. Buka halaman utama aplikasi di browser Anda.
2. Anda akan melihat form Login.
3. Masukkan **Email Address** dan **Password** Anda.
4. Klik tombol **Login**.
5. Jika berhasil, Anda akan diarahkan ke halaman Dashboard sesuai jabatan (Role) Anda.

### Cara Logout:
1. Cari nama atau profil Anda di pojok kanan atas layar.
2. Klik nama/ikon tersebut hingga muncul menu tarik-turun (*dropdown*).
3. Klik tombol **Logout**.
4. Anda akan keluar dan kembali ke halaman Login.

---

## 9. Panduan SuperAdmin

### Tujuan Role
SuperAdmin adalah penguasa tertinggi di sistem. SuperAdmin bisa melihat, membuat, mengubah, dan menghapus apa saja. Biasanya digunakan oleh pemilik sistem atau IT Administrator.

### Dashboard
Menampilkan ringkasan total aktivitas sistem (jumlah sekolah, murid, coach, dll).

### Menu yang Tersedia & Workflow
SuperAdmin memiliki semua menu dari semua role. Pekerjaan utamanya biasanya terkait pengaturan pengguna (User Management).

#### 1. User Management (Pengelolaan Pengguna)
- **Tujuan:** Membuat akun untuk tim manajemen atau membuat akun untuk perwakilan sekolah.
- **Langkah:** Masuk menu **Users** -> Klik tombol Tambah -> Isi nama, email, password, dan pilih Role.
- **Khusus untuk PIC DK SCHOOL / TEACHER SCHOOL / FINANCE:** Saat Anda membuat user dengan tipe sekolah (School Scoped), Anda **wajib memilih/plotting** sekolah mana saja yang bisa diakses oleh akun tersebut (School Plotting).
- **Hasil:** Akun baru berhasil dibuat dan bisa langsung digunakan untuk login.

#### 2. Fitur Lainnya
SuperAdmin dapat mengakses semua fitur yang dimiliki oleh Relation dan SPV Coach (seperti menambah data sekolah, kelas, assign coach, hingga memeriksa laporan).

---

## 10. Panduan Relation

### Tujuan Role
Relation bertugas mengelola data utama (master data) pendaftaran sekolah, program kelas, murid, dan yang paling penting: **memeriksa dan menyetujui Laporan Belajar (Coach Report).**

### Workflow Utama Relation
Memasukkan Data -> Memantau Laporan Masuk -> Review (Approve/Reject) -> Export Absensi.

### Detail Fitur
#### 1. School & Program Workspace
- **Tujuan:** Mendaftarkan sekolah rekanan dan program belajarnya.
- **Langkah:** Buka menu **Schools** -> Tambah Sekolah -> Setelah sekolah terbuat, klik detail sekolah tersebut untuk menambahkan **Program Kelas**.

#### 2. Kelola Murid (Students)
- **Tujuan:** Memasukkan nama siswa ke dalam kelas tertentu.
- **Langkah:** Masuk ke data Sekolah -> Buka Kelas -> Klik **Students** -> Anda bisa menambah manual satu per satu, atau menggunakan fitur **Import Excel** (download template, isi nama, upload).

#### 3. Review Coach Report
- **Tujuan:** Menentukan kualitas laporan Coach.
- **Langkah:** Buka menu **Reports** -> Cari laporan dengan status `Submitted` -> Klik lihat detail.
- **Tombol Approve:** Klik jika laporan sudah bagus dan lengkap. Setelah di-Approve, pihak Sekolah bisa melihatnya.
- **Tombol Reject:** Klik jika laporan kurang lengkap atau foto salah. Anda akan diminta mengisi **Alasan Penolakan**. Laporan akan kembali ke Coach untuk diperbaiki.

#### 4. Melihat & Export Attendance (Kehadiran)
- **Tujuan:** Melihat daftar hadir seluruh murid di semua sekolah.
- **Langkah:** Buka menu **Attendance** -> Anda bisa melihat seluruh sekolah -> Klik **Export** untuk mendownload rekap kehadiran dalam bentuk file CSV/Excel.

---

## 11. Panduan SPV Coach

### Tujuan Role
SPV Coach (Supervisor) bertugas mengelola para pengajar (Coach). Mulai dari pembuatan akun Coach hingga pembagian jadwal kelas.

### Workflow Utama
Input Data Coach -> Assign (Plotting) Coach ke Kelas -> Pantau Kehadiran.

### Detail Fitur
#### 1. Coach Management & Assignment
- **Tujuan:** Memasukkan data Coach baru dan menentukan kelas mana yang harus mereka ajar.
- **Langkah:** Buka menu **Coaches** -> Tambah Coach. Setelah jadi, klik profil Coach tersebut -> Klik fitur **Assign to Class** -> Pilih Sekolah dan Kelas.
- **Catatan:** Pastikan Anda memilih kelas yang tepat, karena Coach hanya bisa membuat laporan di kelas yang ditugaskan kepadanya.

#### 2. Pemantauan & Export Attendance
- **Langkah:** Anda dapat melihat laporan absensi dan laporan kerja Coach. Anda dapat melakukan Export Attendance, namun Anda **tidak memiliki hak** untuk tombol Approve/Reject laporan.

---

## 12. Panduan Coach

### Tujuan Role
Coach adalah ujung tombak lapangan. Tugas Anda adalah mengajar ke sekolah, mencatat kehadiran murid, dan mengirimkan laporan kegiatan (foto, materi, rangkuman) setelah kelas selesai.

### Dashboard
Saat login, Anda akan melihat daftar laporan Anda dan jadwal atau kelas yang ditugaskan kepada Anda.

### Workflow Utama
Mengajar -> Buat Report & Absen -> Submit -> Cek Status (Approved/Rejected) -> Perbaiki jika Rejected.

### Detail Fitur
#### 1. Membuat Coach Report & Attendance
- **Kapan Digunakan:** Segera setelah Anda selesai mengajar sebuah kelas.
- **Langkah:**
  1. Klik menu **Reports** -> **Create New Report**.
  2. Pilih Sekolah dan Kelas Anda (hanya muncul kelas yang ditugaskan ke Anda).
  3. Tentukan Tanggal.
  4. Isi **Lesson Material** (Materi) dan **Activity Summary** (Rangkuman kegiatan).
  5. **Attendance (Absensi):** Di layar yang sama, akan muncul daftar nama siswa. Centang kehadiran mereka (Present, Absent, Sick, Permission).
  6. **Upload Media:** Masukkan foto atau video dokumentasi kegiatan kelas.
- **Hasil:** Jika Anda klik `Submit`, status laporan menjadi **Submitted** dan akan diperiksa oleh Relation. Anda juga bisa menyimpannya sebagai `Draft` jika belum selesai diketik.

#### 2. Memperbaiki Laporan yang Ditolak (Rejected)
- **Tujuan:** Memperbaiki laporan yang kurang tepat.
- **Langkah:** Jika Relation menolak laporan Anda, statusnya menjadi **Rejected**. Buka laporan tersebut, baca pesan **Catatan Admin/Alasan Penolakan**. Edit teks atau ganti foto sesuai permintaan, lalu tekan **Submit** kembali.

#### 3. Accident Notes
- **Tujuan:** Melaporkan jika terjadi kejadian luar biasa (kecelakaan, insiden, barang rusak) di kelas.
- **Langkah:** Saat mengisi Report, terdapat kotak khusus "Accident Notes". Jika ada kejadian, ketik di kotak tersebut.
- *Catatan: Jika ada isian Accident Notes, sistem akan menampilkannya dengan warna teks/blok mencolok (merah) agar pihak Relation atau Sekolah segera menyadarinya.*

---

## 13. Panduan PIC DK SCHOOL

### Tujuan Role
PIC DK SCHOOL adalah perwakilan dari pihak yayasan sekolah yang bekerja sama dengan sistem. Role ini hanya untuk pemantauan secara pasif.

### Hak Istimewa dan Batasan
- Anda **TIDAK BISA** mengubah data, menyetujui, atau menolak laporan.
- Anda **HANYA BISA MELIHAT** data dari sekolah yang sudah di-plotting/disambungkan ke akun Anda oleh SuperAdmin. Sekolah lain tidak akan terlihat.

### Detail Fitur
#### 1. Pemantauan Dashboard
Saat login, Anda akan melihat angka ringkasan dan statistik khusus untuk murid dan kelas yang ada di sekolah Anda saja.

#### 2. Melihat Coach Report
- **Tujuan:** Melihat bukti kegiatan mengajar dari para Coach.
- **Langkah:** Masuk menu **Reports**. Anda hanya akan melihat Laporan Belajar yang statusnya sudah **Approved** (disetujui Relation). Laporan yang masih draft atau ditolak tidak akan tampil.

#### 3. Export Attendance
- **Tujuan:** Mendapatkan rekapan Excel kehadiran murid Anda.
- **Langkah:** Buka menu **Attendance** -> Anda bisa melihat absen murid Anda -> Klik **Export** untuk mendownload file CSV (Excel).

---

## 14. Panduan TEACHER SCHOOL

### Tujuan Role
Mirip dengan PIC DK SCHOOL, Teacher School adalah guru internal sekolah. Hak aksesnya identik dengan PIC DK SCHOOL, bersifat pemantauan pasif sesuai wilayah profil sekolahnya saja.

- **Fitur Tersedia:** Login, melihat Attendance murid di sekolahnya, melihat laporan (Approved), dan mendownload Export Attendance sekolahnya.
- **Batasan:** Tidak bisa menyetujui/menolak laporan Coach, tidak bisa mengubah data murid.

---

## 15. Panduan Finance

### Tujuan Role
Bagian keuangan internal. Membutuhkan data kehadiran (Attendance) seluruh sekolah secara lengkap dan detail guna menghitung rekap tagihan atau upah.

### Detail Fitur
- **Akses Tidak Terbatas ke Absensi:** Berbeda dengan PIC Sekolah, Anda bisa melihat kehadiran murid dari **seluruh sekolah**.
- **Filter & Export CSV:**
  1. Buka menu **Attendance**.
  2. Gunakan kolom pencarian atau filter untuk memilih nama sekolah atau rentang waktu tertentu.
  3. Klik tombol **Export CSV**.
  4. File hasil export akan otomatis terdownload ke komputer. Buka menggunakan Microsoft Excel atau Google Sheets.
- **Catatan:** Role Finance tidak mengurusi laporan mengajar (Coach Report). Anda bebas dari kewajiban Approve/Reject.

---

## 16. Panduan Lifecycle Coach Report

Proses sebuah "Coach Report" berjalan adalah sebagai berikut:

1. **DRAFT**
   Coach sedang mengetik laporan tapi belum selesai, sehingga disimpan sementara. Orang lain belum bisa melihat ini.
2. **SUBMITTED**
   Coach selesai membuat laporan dan mengirimkannya. Menunggu Relation untuk diperiksa.
3. **REVIEW (Pemeriksaan)**
   Relation membaca laporan. Di tahap ini Relation membuat keputusan.
4. **APPROVED** (Disetujui)
   Jika disetujui Relation. Pada tahap inilah laporan akhirnya **bisa dilihat** oleh pihak sekolah (PIC dan Teacher). Laporan dianggap selesai.
5. **REJECTED** (Ditolak)
   Jika ada foto yang kurang, salah nama, atau teks tidak rapi, Relation menolak. Laporan kembali kepada Coach dengan menyertakan "Alasan Penolakan".
6. **REVISI**
   Coach mengedit laporan yang ditolak tersebut, memperbaiki kesalahan, dan menekan Submit lagi (kembali ke tahap 2).

---

## 17. Panduan Attendance (Kehadiran)

### Status Kehadiran yang Tersedia:
- **Present:** Hadir
- **Absent:** Alpa / Tanpa Keterangan
- **Sick:** Sakit
- **Permission:** Izin

### Perbedaan Tampilan:
- **SuperAdmin, Relation, SPV Coach, Finance:** Bisa melihat data absen semua sekolah tanpa kecuali.
- **Coach:** Hanya bisa melihat dan mengabsen murid di kelas yang diajarkannya saja.
- **PIC DK SCHOOL & Teacher School:** Hanya bisa melihat absen siswa di sekolah mereka sendiri.

---

## 18. Panduan Export
- **Siapa yang bisa export?** Semua role kecuali Coach.
- **Data apa yang diexport?** Rekap presensi murid (Attendance).
- **Format:** CSV (bisa dibuka dengan Excel).
- **Langkah:** Di halaman Attendance, klik tombol "Export". Sistem akan mencocokkan data berdasarkan apa yang sedang Anda lihat di layar (termasuk filter sekolah yang Anda pilih) dan mengubahnya menjadi file download.

---

## 19. Panduan Accident Notes
- **Apa itu?** Catatan Insiden/Kecelakaan. Adalah kolom teks khusus di dalam Coach Report.
- **Kapan digunakan?** Jika ada murid cedera, properti kelas rusak parah, atau kejadian darurat lainnya, Coach wajib menulisnya di sini.
- **Siapa yang bisa melihat?** Relation, SuperAdmin, dan pihak Sekolah.
- **Tampilan Khusus:** Sistem akan menyorot teks ini dengan blok warna merah atau peringatan khusus di layar agar langsung menarik perhatian pembaca. (Berdasarkan panduan sistem, status/visual urgent akan ditonjolkan).

---

## 20. Error Handling / Troubleshooting

| Masalah | Kemungkinan Penyebab | Solusi |
| :--- | :--- | :--- |
| **Gagal Login (Error: Kredensial tidak cocok)** | Salah ketik email atau password. | Periksa huruf besar/kecil password Anda. Pastikan email tidak ada spasi di belakangnya. |
| **Halaman "403 Forbidden / Unauthorized"** | Anda mencoba membuka menu yang tidak menjadi hak (Role) Anda. | Tekan tombol kembali (Back) di browser. Gunakan hanya menu yang muncul di layar Anda. |
| **Coach tidak bisa menemukan kelasnya saat membuat Report** | SPV Coach belum menugaskan (Assign) Coach tersebut ke kelas. | Minta SPV Coach untuk mengecek halaman Coach Assignment Anda. |
| **Gagal Upload Foto di Report** | Ukuran file foto terlalu besar atau koneksi internet terputus. | Kompres ukuran foto, pastikan formatnya umum (.jpg / .png), dan cek koneksi internet. |
| **Siswa tidak muncul di daftar absensi** | Data siswa belum dimasukkan oleh Relation. | Hubungi Relation untuk menambah data siswa ke kelas Anda. |
| **Laporan tertulis Rejected** | Relation menilai laporan kurang standar. | Buka laporan, baca catatan dari Relation di bagian bawah layar, perbaiki data, dan submit ulang. |

---

## 21. FAQ (Pertanyaan yang Sering Diajukan)

- **Bagaimana cara login?**
  Masuk ke web aplikasi, ketik email dan password, tekan Login.
- **Bagaimana Relation menambahkan sekolah?**
  Login sebagai Relation, masuk menu "Schools", klik Tambah Sekolah, isi data dan simpan.
- **Bagaimana Coach membuat laporan?**
  Login sebagai Coach, ke menu "Reports", klik "Create", isi form, centang absen murid, upload foto, klik "Submit".
- **Apa yang terjadi jika report ditolak?**
  Status akan berubah menjadi "Rejected". Coach harus masuk lagi ke menu Report, klik Edit, memperbaiki kesalahan berdasarkan catatan admin, dan menekan Submit lagi.
- **Bagaimana Finance export CSV?**
  Login sebagai Finance, masuk ke menu "Attendance", filter jika perlu, lalu klik tombol "Export".
- **Mengapa PIC Sekolah tidak bisa melihat laporan bulan ini?**
  Laporan kemungkinan masih berstatus "Submitted" (belum diperiksa) atau "Rejected" (ditolak). PIC hanya bisa melihat yang sudah "Approved".
- **Mengapa saya tidak bisa melihat sekolah tertentu (sebagai PIC)?**
  Akun Anda belum di-plotting ke sekolah tersebut oleh SuperAdmin. Hubungi SuperAdmin.

---

## 22. Best Practices Penggunaan

- **Jangan bagikan akun Anda.** Setiap akun mencatat siapa yang melakukan approval atau pengeditan (jejak digital).
- **Cek ulang sebelum klik Submit/Approve.** Untuk Coach, pastikan foto yang diupload benar-benar suasana kelas tersebut. Untuk Relation, baca Accident Notes sebelum approve laporan.
- **Gunakan WiFi stabil.** Karena Coach harus mengupload foto/video dokumentasi, koneksi yang kurang baik bisa menyebabkan upload terputus (gagal).
- **Isi Nama Siswa yang Jelas.** Saat Relation melakukan Import Excel, hindari penggunaan simbol aneh agar sistem Attendance rapi.

---

## 23. Glossary (Daftar Istilah)

- **School (Sekolah):** Entitas cabang atau instansi klien tempat kelas berlangsung.
- **Program Kelas:** Pengelompokan siswa dalam sebuah sekolah (contoh: Kelas Coding Dasar SD).
- **Coach:** Guru/Pengajar yang turun langsung ke lapangan.
- **Coach Report:** Bukti lapor kegiatan mengajar digital.
- **Attendance:** Kehadiran atau daftar absen siswa.
- **Relation:** Staf internal yang mengurus administrasi dan mengawasi/menilai kualitas laporan Coach.
- **SPV Coach:** Supervisor yang mengatur pembagian jadwal/kelas para Coach.
- **PIC DK SCHOOL / TEACHER SCHOOL:** Perwakilan klien (Sekolah) yang mengakses sistem untuk mengecek progres muridnya.
- **Finance:** Tim keuangan internal yang membutuhkan data rekapan absensi murid.
- **Approve:** Menyetujui laporan.
- **Reject:** Menolak laporan dan meminta perbaikan.
- **School Plotting:** Proses SuperAdmin mengikatkan akun PIC ke data satu sekolah tertentu saja.

---

## 24. Catatan Implementasi Aktual
Sistem ini menggunakan fitur akses yang sangat cerdas di balik layar (*AuthorizationService*).
Oleh karena itu, penyebutan menu dan rute di atas berjalan sangat lancar karena satu halaman `Attendance` atau `Reports` bisa menyesuaikan tampilannya secara otomatis (memfilter datanya) tergantung dari *Role* siapa yang sedang melihatnya.
Fitur notifikasi ke aplikasi chat eksternal (seperti WaHa) **tidak** dibahas dalam manual ini karena sistem berfokus pada ekosistem manajemen portal laporan itu sendiri. Data absensi dan foto-foto sudah disimpan secara aman di *cloud* (Cloudinary).
