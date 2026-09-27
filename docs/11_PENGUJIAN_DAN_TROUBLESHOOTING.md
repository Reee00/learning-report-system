# Pengujian dan Troubleshooting

## Test
Jalankan:
```text
php artisan test
```

`phpunit.xml` memakai SQLite `:memory:` dan `RefreshDatabase`; test tidak memvalidasi database development MySQL. Test yang tersedia mencakup authorization/school isolation, report authorization dan atomicity, master data, end-to-end flow, media storage, students, schools, dan login redirect. Angka hasil lama tidak dianggap hasil terkini sampai test dijalankan ulang.

Suite terkait refactor 2026-09-24:

```text
php artisan test --filter=SchoolWorkspaceTest
php artisan test --filter=ScheduleBulkTest
php artisan test --filter=SharedUiComponentsTest
```

- `SchoolWorkspaceTest` — detail sekolah, assign kelas yang sudah ada (pindah), buat kelas baru, assign/lepas program, pencegahan duplikat nama kelas dan duplikat program, penolakan hapus kelas yang masih terpakai, scope PIC/Coach/Finance (Finance all-school), 404 saat kelas sekolah lain ditempel ke URL sekolah ini, dan konsistensi dengan Master Program Kelas.
- `ScheduleBulkTest` — form pola per hari (tab Senin–Sabtu, `Tanggal Mulai` + `Jumlah Pertemuan` di tiap blok sekolah, tidak ada `week_start`), tiga sekolah pada hari SENIN dengan tanggal mulai berbeda masing-masing 20 pertemuan (nomor pertemuan dan tanggal dihitung per pola, bukan dari satu tanggal global), kemandirian tiap hari, banyak kelas & coach per blok, `additional_coaches` per baris, baris kosong dilewati, tanggal mulai yang bukan hari tab-nya ditolak dengan saran tanggal terdekat (tidak digeser diam-diam), blok tanpa tanggal mulai dilaporkan merujuk kolom KET, duplikat blok/baris dan bentrok coach antar baris, atomik saat satu blok gagal, pesan error menyebut hari + blok + sekolah, idempotensi generate ulang, scope PIC, dan `DELETE` pola yang tetap menyimpan sesi tergenerate.
- `SemesterScheduleTest` — siklus hidup pola: atribut baris tersalin ke sesi, default 20 pertemuan, sesi yang bentrok jadwal coach dilewati dan dilaporkan, libur (hapus sesi) + generate ulang hanya mengisi yang kurang, **status "Jalan Minggu Ini?" tidak merusak/menambah pola**, hapus baris pola tetap menyimpan sesi, scope PIC & larangan role tanpa akses jadwal, visibility coach utama/tambahan, dan reminder yang mengikuti tanggal sesi tiap sekolah (dua sekolah satu hari dengan tanggal mulai berbeda dinilai terpisah).
- `SharedUiComponentsTest` — default paginator Bootstrap 5, view paginasi bersama tanpa key bahasa mentah, nomor baris melanjutkan antar halaman, struktur dua kolom responsif form sekolah, dan aturan CSS anti-pecah-angka.

## Pemeriksaan Manual
Uji tujuh role, class assignment Coach, school plotting untuk PIC/TEACHER SCHOOL (Finance all-school sehingga tidak diplot), report reject/resubmit, approved-only school view, export CSV/PDF, akses `/media/{media}`, dan penolakan akses lintas school/class.

Pemeriksaan manual refactor 2026-09-24:
- Buka `/admin/schools/{id}` sebagai Relation: tampilan tiga bagian (Informasi Sekolah / Kelas di Sekolah Ini / program per kelas), tombol "Tambah / Assign Kelas", hasil assign langsung terlihat tanpa berpindah halaman.
- Buka `/admin/schedules/create`: navigasi hari Senin–Sabtu, `+ Tambah Sekolah`, `+ Tambah Kelas`, `Duplikat baris`, `Duplikat blok sekolah`, `Copy Pola Hari`, badge jumlah sesi per hari; dropdown Kelas mengikuti Sekolah dan Coach mengikuti Kelas.
- Periksa paginasi di halaman dengan >1 halaman data pada lebar 360 px: label Indonesia, tombol Sebelumnya/Berikutnya tidak terpotong, nomor halaman tidak terpecah antar digit, status disabled jelas.
- Periksa form Tambah Sekolah pada lebar desktop, tablet, dan ponsel: dua kolom → satu kolom, tombol selebar penuh di ponsel, tidak ada scroll horizontal; submit dengan nama kosong lalu pastikan modal terbuka kembali dengan isian sebelumnya.

## Masalah yang Perlu Diverifikasi
- `.env` aktif dan koneksi MySQL.
- Permission/writable untuk `storage/app/report-media`.
- PHP Docker 8.3 versus requirement Composer 8.4.
- Legacy Cloudinary URL yang belum dimigrasikan.
- Export besar memakai `get()` dan dapat membutuhkan memory besar.
