# Manual Pengguna

Disinkronkan dengan aplikasi: **2026-09-29**.

## Peran

SuperAdmin, Relation, SPV Coach, Coach, PIC DK SCHOOL, TEACHER SCHOOL, dan Finance. Menu yang tampil bergantung pada kewenangan (capability) peran Anda.

## Alur Penggunaan

1. **Relation/SuperAdmin** menyiapkan Sekolah, Program, Kelas, Murid, dan Pengguna.
2. **SPV Coach** membuat dan mengelola penugasan Coach ke kelas.
3. **Jadwal mengajar** disusun sebagai pola berulang per hari dan sekolah, lalu di-*generate* menjadi sesi bertanggal — atau diimpor dari berkas Excel.
4. **Coach** membuka sesi yang ditugaskan, mengisi laporan (materi, tujuan, aktivitas, catatan), mengisi kehadiran murid, melampirkan media, lalu menyimpan draft atau mengirim laporan.
5. **Relation/SuperAdmin** meninjau laporan yang masuk di **antrean review**.
6. Reviewer menyetujui atau menolak. Penolakan selalu disertai alasan.
7. Laporan yang ditolak diperbaiki Coach, lalu dikirim ulang.
8. Pengguna sekolah hanya melihat data sekolah yang diplot untuknya, dan hanya laporan yang sudah **disetujui**.

## Hal Penting yang Sering Ditanyakan

### Satu sesi hanya boleh punya satu laporan

Bila sebuah sesi sudah dilaporkan — oleh coach utama maupun coach pendamping — sesi itu tidak muncul lagi sebagai pilihan saat membuat laporan baru. Aturan ini berlaku agar tidak ada laporan ganda untuk satu pertemuan.

### Sesi yang dinonaktifkan tidak bisa dilaporkan

Sesi yang dimatikan pengelola jadwal tetap terlihat di daftar (redup) sebagai riwayat, tetapi tidak menerima laporan baru. Menonaktifkan sesi **tidak** menghapus laporan yang sudah ada.

### Laporan yang sudah dikirim tidak bisa diedit bebas

Laporan hanya dapat diedit saat berstatus **draft** atau **ditolak**. Setelah dikirim, laporan menunggu keputusan reviewer.

### Materi yang dipelajari tampil di riwayat laporan (Coach)

Daftar "Laporan Saya" menampilkan kolom **Materi yang Dipelajari**. Isinya persis teks yang Anda ketik pada field "Materi Pelajaran" di form laporan, ditambah keterangan `Week N` bila laporan itu tertaut ke pertemuan ke-N sebuah sesi. Nilai yang sama muncul di halaman detail laporan.

### Review dan arsip adalah dua halaman berbeda

- **Antrean review** — daftar kerja reviewer, hanya berisi laporan yang menunggu keputusan atau yang ditolak.
- **Arsip laporan** — riwayat lengkap, dikelompokkan per sekolah lalu kelas, hanya untuk dibaca.

## Kehadiran dan Export

Kehadiran diakses lewat menu `/attendance`, dengan alur bertingkat: daftar tanggal → sekolah → kelas → detail sesi.

**Angka total kehadiran tidak ditampilkan di halaman.** Rekap akumulasi hanya tersedia pada dokumen yang diunduh/dicetak.

Export tersedia dalam CSV, Excel, dan PDF sesuai kewenangan Anda. Finance hanya dapat mengunduh CSV — tombol lain tidak ditampilkan, dan permintaan langsung ke alamatnya tetap ditolak server.

Filter sekolah, kelas, tanggal, dan status hanya mempersempit data yang memang boleh Anda lihat; filter tidak dapat membuka data di luar cakupan Anda.

## Media Laporan

Foto, video, dan bukti kehadiran diunggah ke penyimpanan lokal privat di server. Media hanya dapat dibuka melalui aplikasi, dengan pemeriksaan hak akses per laporan — tautan berkas tidak dapat dibagikan ke pengguna lain.

Setiap foto dan video pada halaman detail laporan punya tombol **Download**. Unduhan melewati pemeriksaan hak akses yang sama dengan pratinjau, jadi berkas hanya terunduh bila Anda memang berhak membukanya.

Pada dokumen cetak/PDF, video tampil sebagai kotak **"Lihat Video"** yang menuju halaman detail laporan — video diputar dan diunduh dari halaman itu, bukan dari dokumen cetak.

Jangan mengakses atau memindahkan berkas langsung dari penyimpanan server.

Batas unggah: 10 foto (masing-masing maksimal 10 MB), 3 video (masing-masing maksimal 100 MB), dan 5 foto bukti kehadiran (masing-masing maksimal 10 MB).

## Memasang Aplikasi di Perangkat (PWA)

Aplikasi ini dapat dipasang seperti aplikasi biasa:

- **Android/Chrome** — buka menu browser, pilih "Tambahkan ke layar utama" / "Install app".
- **iOS/Safari** — tombol Bagikan, lalu "Tambahkan ke Layar Utama".
- **Desktop** — ikon pasang di address bar.

Setelah dipasang, tersedia pintasan langsung ke **Buat Laporan**, **Laporan Saya**, dan **Kehadiran**. Saat perangkat sedang offline, aplikasi menampilkan halaman pemberitahuan offline — halaman berisi data Anda tidak disimpan di perangkat, sehingga tidak akan terbuka tanpa koneksi. Ini disengaja demi keamanan data pada perangkat bersama.

## Notifikasi

### Notifikasi dalam aplikasi

Notifikasi yang ditujukan kepada Anda muncul sebagai penanda di aplikasi. Membukanya akan menandainya sudah dibaca.

### Notifikasi push ke perangkat

Tombol untuk mengaktifkan notifikasi push hanya muncul bila fitur ini diaktifkan oleh administrator. Saat Anda menekan tombol itu, browser akan meminta izin — izin baru diminta pada saat Anda menekan, bukan sebelumnya.

Bila Anda menolak izin, Anda tetap menerima notifikasi di dalam aplikasi. Untuk mengubah pilihan, gunakan pengaturan situs di browser Anda.

Notifikasi push hanya berisi judul dan ringkasannya, bukan isi laporan.

## Catatan

- Alamat `/admin/*` adalah nama teknis, bukan penanda peran. Apa yang bisa Anda buka ditentukan oleh kewenangan peran Anda.
- **Accident Notes** adalah pengingat pribadi Coach dan punya menunya sendiri di sidebar. Catatan kecelakaan tetap diisi sebagai bagian dari laporan, lalu terkumpul di halaman itu beserta tautan ke laporan asalnya. Halaman ini hanya menampilkan catatan milik Anda sendiri, dan tidak mengirim notifikasi apa pun.
- Laporan sesi bersama boleh dibaca coach pendamping, tetapi foto/videonya hanya terbuka bagi coach pembuat laporan.
- Nilai alamat, kredensial, dan konfigurasi deployment mengikuti pengaturan administrator environment.
