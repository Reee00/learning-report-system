# Pengujian dan Troubleshooting

## Test
Jalankan:
```text
php artisan test
```

`phpunit.xml` memakai SQLite `:memory:` dan `RefreshDatabase`; test tidak memvalidasi database development MySQL. Test yang tersedia mencakup authorization/school isolation, report authorization dan atomicity, master data, end-to-end flow, media storage, students, schools, dan login redirect. Angka hasil lama tidak dianggap hasil terkini sampai test dijalankan ulang.

## Pemeriksaan Manual
Uji tujuh role, class assignment Coach, school plotting untuk PIC/TEACHER SCHOOL/Finance, report reject/resubmit, approved-only school view, export CSV/PDF, akses `/media/{media}`, dan penolakan akses lintas school/class.

## Masalah yang Perlu Diverifikasi
- `.env` aktif dan koneksi MySQL.
- Permission/writable untuk `storage/app/report-media`.
- PHP Docker 8.3 versus requirement Composer 8.4.
- Legacy Cloudinary URL yang belum dimigrasikan.
- Export besar memakai `get()` dan dapat membutuhkan memory besar.
