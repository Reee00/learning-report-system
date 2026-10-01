# Pengujian dan Troubleshooting

Disinkronkan dengan kode: **2026-09-29**.

## Menjalankan Test

```bash
php artisan test          # atau: composer test
npm run test:pwa          # 37 pemeriksaan service worker
```

`phpunit.xml` memakai SQLite `:memory:` + `RefreshDatabase` dan `QUEUE_CONNECTION=sync`. Konsekuensinya:

- Test **tidak** memvalidasi perilaku MySQL produksi (lihat catatan `AttendanceMysqlOnlyFullGroupByTest` di bawah).
- Job berjalan seketika di dalam test, sehingga masalah yang hanya muncul saat job benar-benar mengantre **tidak tertangkap** oleh suite ini.

## Cakupan

48 berkas test: 46 di `tests/Feature/`, 2 di `tests/Unit/` (keduanya termasuk dua berkas scaffolding `ExampleTest`).

| Kelompok | Berkas |
|---|---|
| Otorisasi & scope | `AuthorizationServiceTest`, `RoleIsolationTest`, `CrossSchoolSecurityTest`, `RoleRedirectTest`, `LoginSecurityTest` |
| Akun & autentikasi | `AccountSettingsTest`, `PasswordChangeTest`, `LoginPasswordToggleTest`, `WhatsappLinkTest` |
| Laporan | `CoachReportAuthorizationTest`, `CoachReportAtomicityTest`, `CoachReportDownloadTest`, `CoachReportDetailAndMediaTest`, `ReportArchiveTest`, `ReportGroupingTest`, `ReportDownloadMediaTest`, `SessionReportOwnershipTest`, `MeetingRequirementsTest`, `MeetingProgressTest`, `ReportPrintReadabilityTest` |
| Kehadiran | `AttendanceGroupingTest`, `AttendanceExcelPdfExportTest`, `AttendanceMysqlOnlyFullGroupByTest`, `FinanceAttendanceAccessTest` |
| Jadwal | `ScheduleManagementTest`, `ScheduleBulkTest`, `ScheduleRegenerationTest`, `ScheduleSessionActiveTest`, `SchedulePatternDeletionTest`, `SessionBasedSchedulingTest`, `SemesterScheduleTest`, `RelationCoachAssignmentTest`, `SharedCoachAssignmentTest` |
| Master data | `SchoolManagementTest`, `SchoolWorkspaceTest`, `StudentManagementTest`, `CoachStudentManagementTest`, `MasterDataIntegrityTest`, `DatabaseSeederTest` |
| Media | `MediaStorageTest` |
| Notifikasi & push | `CustomNotificationTest`, `WebPushTest` |
| PWA | `PwaTest`, `tests/JavaScript/service-worker-routing.test.js` |
| Lintas modul | `EndToEndFlowTest`, `ActivityLogTest`, `SharedUiComponentsTest` |

`tests/Support/` menyediakan `FakeWebPush` dan `ImmediatePushProbe` untuk pengujian push tanpa jaringan.

### Satu-satunya Sumber Skip

`AttendanceMysqlOnlyFullGroupByTest` di-skip kecuali variabel `TEST_MYSQL_*` tersedia. Test ini memverifikasi kueri yang hanya valid pada MySQL `ONLY_FULL_GROUP_BY`. **Jalankan dengan MySQL sebelum deploy** bila Anda mengubah kueri kehadiran — pada SQLite, kesalahan itu tidak akan terlihat.

## Suite Spesifik

```bash
php artisan test --filter=SchoolWorkspaceTest
php artisan test --filter=ScheduleBulkTest
php artisan test --filter=SemesterScheduleTest
php artisan test --filter=SharedUiComponentsTest
npm run test:pwa
```

- **`SchoolWorkspaceTest`** — detail sekolah, assign kelas yang sudah ada (pindah), buat kelas baru, assign/lepas program, pencegahan duplikat nama kelas dan duplikat program, penolakan hapus kelas yang masih terpakai, scope PIC/Coach/Finance (Finance all-school), 404 saat kelas sekolah lain ditempel ke URL sekolah ini, dan konsistensi dengan Master Program Kelas.
- **`ScheduleBulkTest`** — form pola per hari (tab Senin–Sabtu, `Tanggal Mulai` + `Jumlah Pertemuan` di tiap blok sekolah, tanpa `week_start`), tiga sekolah pada hari SENIN dengan tanggal mulai berbeda dan nomor pertemuan dihitung per pola, kemandirian tiap hari, banyak kelas & coach per blok, `additional_coaches` per baris, baris kosong dilewati, tanggal mulai yang bukan hari tab-nya ditolak dengan saran tanggal terdekat (tidak digeser diam-diam), blok tanpa tanggal mulai dilaporkan merujuk kolom KET, duplikat blok/baris dan bentrok coach antar baris, atomik saat satu blok gagal, pesan galat menyebut hari + blok + sekolah, idempotensi generate ulang, scope PIC, dan `DELETE` pola yang tetap menyimpan sesi tergenerate.
- **`SemesterScheduleTest`** — siklus hidup pola: atribut baris tersalin ke sesi, default 20 pertemuan, sesi yang bentrok jadwal coach dilewati dan dilaporkan, libur (hapus sesi) + generate ulang hanya mengisi yang kurang, status "Jalan Minggu Ini?" tidak merusak/menambah pola, hapus baris pola tetap menyimpan sesi, scope PIC & larangan peran tanpa akses jadwal, visibility coach utama/tambahan, dan reminder yang mengikuti tanggal sesi tiap sekolah.
- **`SharedUiComponentsTest`** — default paginator Bootstrap 5, view paginasi bersama tanpa key bahasa mentah, nomor baris melanjutkan antar halaman, struktur dua kolom responsif form sekolah, dan aturan CSS anti-pecah-angka.
- **`service-worker-routing.test.js`** — 37 pemeriksaan atas `public/sw.js`: pemilihan strategi per jenis permintaan, penanganan push, dan sanitasi URL. **Jalankan setiap kali `public/sw.js` disentuh.**

> Jangan menyalin angka hasil test dari dokumen ke laporan mana pun tanpa menjalankan ulang suite-nya. Lihat [README](../README.md) untuk status terakhir yang tercatat.

## Pemeriksaan Manual

Uji tujuh peran, penugasan kelas coach, plot sekolah untuk PIC/TEACHER SCHOOL (Finance all-school sehingga tidak diplot), reject/resubmit laporan, tampilan sekolah yang hanya `approved`, export CSV/Excel/PDF, akses `/media/{media}`, dan penolakan akses lintas sekolah/kelas.

Pemeriksaan refactor 2026-09-24:

- Buka `/admin/schools/{id}` sebagai Relation: tampilan tiga bagian (Informasi Sekolah / Kelas di Sekolah Ini / program per kelas), tombol "Tambah / Assign Kelas", hasil assign langsung terlihat tanpa berpindah halaman.
- Buka `/admin/schedules/create`: navigasi hari Senin–Sabtu, `+ Tambah Sekolah`, `+ Tambah Kelas`, `Duplikat baris`, `Duplikat blok sekolah`, `Copy Pola Hari`, badge jumlah sesi per hari; dropdown Kelas mengikuti Sekolah dan Coach mengikuti Kelas.
- Periksa paginasi pada halaman berhalaman banyak di lebar 360 px: label Indonesia, tombol Sebelumnya/Berikutnya tidak terpotong, nomor halaman tidak terpecah antar digit, status disabled jelas.
- Periksa form Tambah Sekolah pada desktop, tablet, dan ponsel: dua kolom → satu kolom, tombol selebar penuh di ponsel, tanpa scroll horizontal; submit dengan nama kosong lalu pastikan modal terbuka kembali dengan isian sebelumnya.

Pemeriksaan alur Coach (2026-09-29):

- **Riwayat Laporan** — kolom "Materi yang Dipelajari" berisi teks yang persis sama dengan field "Materi Pelajaran" pada form laporan, dengan awalan `Week N` hanya bila laporan tertaut ke sesi dan materinya belum diawali "Week". Berlaku untuk status `submitted`, `rejected`, dan `approved`.
- **Detail laporan** — buka dari tombol Detail: nilai materi identik dengan yang di daftar, pemutar video dan tombol Download Video/Foto berfungsi. Buka laporan coach lain yang bukan sesi Anda → **403**.
- **Unduh laporan** — sebagai coach, panggil `GET /admin/reports/{report}/download` untuk laporan coach lain → **403**; untuk laporan sendiri/sesi bersama yang sudah `approved` → **200** dan tautan "Lihat Video" di dokumen mengarah ke halaman detail **coach**, bukan `/admin/reports/{id}`.
- **Accident Notes** — muncul sebagai menu sidebar khusus coach; halaman hanya memuat catatan milik coach yang login, tiap catatan tertaut ke laporan asalnya, dan **tidak** bertambah sebagai item notification center (`notifications` tetap 0). Halaman ini tidak dapat dibuka peran selain coach.

Pemeriksaan PWA dan push:

- DevTools → Application → Manifest terbaca tanpa galat; Service Worker berstatus activated.
- Matikan jaringan, buka halaman: yang muncul adalah `offline.html`, bukan halaman terautentikasi dari cache.
- Aktifkan notifikasi push, lalu pastikan baris `push_subscriptions` bertambah dan tidak ada duplikat endpoint untuk pengguna yang sama.
- Kirim notifikasi manual, lalu pastikan baris `notifications` **dan** pemrosesan worker terjadi.

## Masalah yang Perlu Diverifikasi

Item berikut bergantung pada environment dan **tidak** dapat diverifikasi dari repositori:

| Item | Status |
|---|---|
| Nilai `.env` aktif dan koneksi MySQL | `NEED VERIFICATION` |
| Izin tulis `storage/app/report-media` | `NEED VERIFICATION` |
| Keputusan atas `CACHE_STORE=database` tanpa migrasi tabel `cache` | **Temuan terbuka** — lihat [02_DATABASE_DOKUMENTASI.md](02_DATABASE_DOKUMENTASI.md) |
| Queue worker benar-benar berjalan di server | `NEED VERIFICATION` |
| Kunci VAPID produksi terpasang | `NEED VERIFICATION` |
| Berkas media lama yang masih berada di Cloudinary | Di luar jangkauan aplikasi |

## Item Lama yang Sudah Tidak Relevan

| Item | Alasan |
|---|---|
| "Docker PHP 8.3 vs requirement 8.4" | `Dockerfile` sudah dihapus (2026-09-11); deployment bukan berbasis Docker |
| "Legacy Cloudinary URL yang belum dimigrasikan" | Cloudinary dihapus penuh dari kode; tidak ada jalur migrasi otomatis |
| "Export besar memakai `get()`" | Matriks kehadiran sekarang dibangun dengan `chunk(1000)` |
| "Belum ada PWA / service worker" | PWA sudah terpasang |

## Troubleshooting

| Gejala | Penyebab paling sering |
|---|---|
| Notifikasi/push tidak sampai | Queue worker tidak berjalan |
| Push terkirim dua kali | `--timeout` worker melebihi `retry_after` koneksi queue |
| Perubahan kode tidak terpakai worker | `php artisan queue:restart` belum dijalankan |
| Unggahan besar gagal 413 | Batas `upload_max_filesize`/`post_max_size` PHP lebih kecil dari batas aplikasi |
| Media tidak tampil | Berkas tidak ada di disk (404 dari `/media/{media}`) atau pengguna di luar scope laporan |
| Halaman tampil versi lama | `SW_VERSION` di `public/sw.js` belum dinaikkan |
| Operasi cache gagal | `CACHE_STORE=database` sementara tabel `cache` tidak ada |
| Laporan tidak bisa dibuat | Sesi nonaktif, atau sesi itu sudah punya laporan (satu sesi = satu laporan) |
