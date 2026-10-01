# Dokumentasi Developer: Learning Report System

Disinkronkan dengan kode: **2026-09-28**.

Dokumen ini ditujukan untuk developer yang baru masuk ke proyek ini. Isinya menjelaskan struktur, konvensi, dan alur kerja yang berlaku **saat ini**. Bila dokumen ini berbeda dengan kode, **kode yang benar** — perbarui dokumennya.

Referensi yang lebih dalam ada di [`docs/`](docs/); peta lengkapnya di [`docs/README.md`](docs/README.md).

---

## 1. Gambaran Aplikasi

LRS mengelola:

- data master sekolah, kelas, program, murid, coach, dan pengguna;
- jadwal mengajar (pola berulang → sesi bertanggal);
- laporan pembelajaran dari coach, lengkap dengan kehadiran murid dan lampiran media;
- alur review laporan (setujui / tolak / kirim ulang);
- kehadiran dan export-nya;
- notifikasi dalam aplikasi dan Web Push.

Tidak ada aplikasi API terpisah — tidak ada `routes/api.php`. Semua route ada di [`routes/web.php`](routes/web.php).

## 2. Teknologi

| Komponen | Keterangan |
|---|---|
| PHP `^8.4`, Laravel `^12.0` | Framework utama |
| Blade + Bootstrap 5.3 | Tampilan; Bootstrap Icons lewat CDN |
| Eloquent | Akses database |
| `rap2hpoutre/fast-excel` | Impor murid dan template sesi jadwal |
| OpenSpout | Membaca workbook induk DIGISchool |
| Dompdf | Export PDF kehadiran |
| `minishlink/web-push` | Web Push (VAPID) |
| Service worker `public/sw.js` | PWA, ditulis sendiri tanpa pustaka |

**Penyimpanan media: lokal dan privat.** Integrasi Cloudinary sudah **dihapus seluruhnya** pada 2026-09-11 — paket, helper, konfigurasi, perintah `media:migrate-cloudinary`, dan seluruh cabang fallback URL eksternal. Jangan menambahkan kembali referensi Cloudinary.

## 3. Struktur Proyek

```
app/
├── Console/Commands/         PurgeActivityLogs.php
├── Http/
│   ├── Controllers/          20 controller (lihat §7)
│   └── Middleware/           RoleMiddleware, PermissionMiddleware, PermissionAnyMiddleware
├── Jobs/                     SendWebPush
├── Models/                   14 model (lihat §6)
├── Notifications/            CustomNotification, ReportReminderNotification
├── Providers/                AppServiceProvider, WebPushServiceProvider
└── Services/                 9 service (lihat §8)
bootstrap/app.php             Middleware alias, penanganan PostTooLargeException
config/                       filesystems.php (disk report_media), queue.php, webpush.php
database/migrations/          28 migrasi
database/seeders/             DatabaseSeeder.php
public/
├── manifest.json             Manifest PWA
├── sw.js                     Service worker
└── .user.ini                 Batas unggah untuk deployment FPM
resources/views/              Blade (layouts, admin, coach, pic, attendance, media)
routes/web.php                Seluruh route aplikasi (89 route non-vendor)
tests/Feature/ tests/Unit/    Suite PHPUnit
tests/JavaScript/             Suite service worker
```

## 4. Middleware

Didaftarkan sebagai alias di [`bootstrap/app.php`](bootstrap/app.php):

| Alias | Kelas | Fungsi |
|---|---|---|
| `role` | `RoleMiddleware` | Memeriksa `users.role` ada di daftar parameter |
| `permission` | `PermissionMiddleware` | Memeriksa satu capability |
| `permission_any` | `PermissionAnyMiddleware` | Lolos bila salah satu capability terpenuhi |
| `auth` | `Authenticate` | Bawaan Laravel |

Contoh pemakaian: `middleware(['auth', 'permission:attendance.export'])`.

`trustProxies(at: '*')` diaktifkan. `PostTooLargeException` ditangani agar unggahan melebihi batas menghasilkan pesan flash yang ramah, bukan halaman 500 kosong.

**Aturan:** middleware adalah gerbang pertama, bukan satu-satunya. Setiap controller yang menyentuh data ber-scope **wajib** memfilter ulang lewat service scope — visibilitas di UI bukan batas keamanan.

## 5. Peran dan Kewenangan

Tujuh peran runtime:

`superadmin`, `relation`, `spv_coach`, `coach`, `school_pic`, `teacher_school`, `finance`.

Kewenangan didefinisikan **di kode**, bukan di tabel: konstanta `AuthorizationService::ROLE_PERMISSIONS`. SuperAdmin adalah wildcard. `users.manage` sengaja tidak diberikan ke peran mana pun sehingga hanya SuperAdmin yang lolos.

`admin/*` adalah namespace URL untuk kompatibilitas, **bukan nama peran**. Relation dan pengguna lain memakai namespace itu sesuai capability-nya.

Untuk matriks lengkap capability × peran, lihat [`docs/reference/permissions.md`](docs/reference/permissions.md).

Untuk menambah capability baru:

1. Tambahkan namanya ke peran terkait di `ROLE_PERMISSIONS`.
2. Pasang middleware `permission:<nama>` pada route.
3. Bila berkaitan dengan sekolah/kelas, tambahkan pemeriksaan scope di controller atau service.

## 6. Model

Empat belas model di `app/Models/`:

**Data master** — `User`, `School`, `SchoolClass`, `Student`, `Program`, `ProgramClass`, `CoachClass`

**Pelaporan** — `Report`, `ReportAttendance`, `ReportMedia`

**Jadwal** — `TeachingSchedule`, `TeachingScheduleTemplate`

**Operasional** — `ActivityLog`, `PushSubscription`

Catatan penting per model:

- **`Report`** — status `draft`, `submitted`, `approved`, `rejected`. Memiliki `teaching_schedule_id` yang **nullable dan unik** (satu sesi = satu laporan). Kolom `photo_path` legacy sudah dihapus. Field konten: `lesson_material`, `goals_materi`, `activity_report`.
- **`ReportMedia`** — `type` bernilai `photo`, `video`, atau `attendance`. `path` adalah jalur relatif pada disk privat, bukan URL publik; akses selalu lewat `/media/{media}`.
- **`TeachingSchedule`** — `day_of_week` di-denormalisasi lewat hook `saving` agar filter hari portabel antara MySQL dan SQLite. Jam selalu disimpan `HH:MM:SS` lewat mutator. Memiliki `is_active`; sesi nonaktif tidak menerima laporan baru tetapi tetap tampil sebagai riwayat.
- **`TeachingScheduleTemplate`** — satu baris per kelas dalam sebuah **pola**; identitas pola adalah `(day_of_week, school_id, start_date)`. `end_date` adalah turunan, bukan kolom.

## 7. Controller

`app/Http/Controllers/`:

**`Admin/`** (11) — `ActivityLogController`, `ClassController`, `CoachController`, `DashboardController`, `NotificationController`, `ProgramController`, `ReportController`, `ScheduleController`, `ScheduleTemplateController`, `SchoolController`, `UserController`

**`Coach/`** — `ReportController` (buat/edit/kirim laporan), `NotificationController` (tandai sudah dibaca), `StudentController`

**`SchoolPic/`** — `DashboardController`

**Lainnya** — `AttendanceController`, `MediaController`, `PushSubscriptionController`, `SchoolPic/…`, `StudentController`, `Auth/LoginController`

Beberapa hal yang sering menjebak:

- **`AttendanceController::export()`** memeriksa capability **per format**: CSV menerima `attendance.export` **atau** `attendance.export_csv`; Excel/XLSX dan PDF hanya menerima `attendance.export`. Finance memegang `attendance.export` (review meeting 2026-10-01, menggantikan aturan CSV-only yang lama), jadi ketiga format terbuka baginya; `attendance.export_csv` kini tidak dipegang role mana pun tetapi tetap sah sebagai capability.
- **`MediaController::authorizeMediaAccess()`** memutuskan hak akses per laporan: SuperAdmin/Relation/SPV Coach lolos; Coach harus pemilik laporan; PIC/Teacher/Finance harus berada dalam scope sekolah **dan** laporan sudah `approved`.
- **`Coach\ReportController::update()`** memvalidasi ulang penugasan kelas dengan `assignedClassOrFail($report->class_id, $report->report_date)` — parameter tanggal penting agar penugasan sementara yang terikat rentang tanggal ikut dinilai.

## 8. Service

Sembilan service di `app/Services/` — tempat sebagian besar logika non-trivial berada. Controller sebaiknya tipis.

| Service | Tanggung jawab |
|---|---|
| `AuthorizationService` | Sumber tunggal capability dan scope; `allows()`, `accessibleSchoolIds()` |
| `AttendanceScopeService` | Filter kueri kehadiran/laporan sesuai scope peminta |
| `AttendanceExportService` | Matriks kehadiran untuk CSV/Excel/PDF; dibangun dengan `chunk(1000)` |
| `MediaStorageService` | Simpan/hapus media pada disk privat; nama berkas aman |
| `ReportReminderService` | Deteksi laporan belum dibuat dan kirim pengingat |
| `CustomNotificationService` | Notifikasi operasional bertarget |
| `ScheduleTemplateService` | Bangun, generate, dan kelola pola jadwal |
| `TeachingScheduleImportService` | Impor jadwal dari dua format berkas Excel |
| `ActivityLogService` | Satu-satunya jalur penulisan activity log; metadata dibersihkan dari kunci sensitif |

## 9. Kehadiran

Kehadiran diisi coach sebagai bagian dari laporan. Status: `present`, `absent`, `sick`, `permission`.

Halaman `/attendance` bertingkat: daftar tanggal → sekolah → kelas → detail sesi.

**Angka akumulasi tidak ditampilkan di halaman.** Rekap hanya ada di dokumen yang diunduh: kolom terakhir CSV `TOTAL HADIR` dan kolom paling kanan PDF (tebal). Ini keputusan 2026-09-13 — panel ringkasan dan route `/attendance/summary` sudah dihapus dan **tidak boleh dihidupkan kembali tanpa keputusan baru**.

Catatan MySQL: `AttendanceScopeService::query()` menambahkan `ORDER BY report_attendances.id DESC`. Kueri yang memakai `GROUP BY` **harus** memanggil `reorder()` lebih dulu, jika tidak MySQL mode `ONLY_FULL_GROUP_BY` menolak kueri (error 1055). Regresinya dijaga `tests/Feature/AttendanceMysqlOnlyFullGroupByTest.php` yang berjalan di koneksi MySQL nyata (butuh env `TEST_MYSQL_*`; dilewati pada suite SQLite default).

## 10. Laporan

Alur: Coach membuat dan mengirim → Relation atau SuperAdmin mereview → setujui atau tolak. Penolakan wajib disertai `admin_notes`. Coach dapat mengedit laporan berstatus `draft` atau `rejected`, lalu mengirim ulang.

- **Satu sesi = satu laporan.** `reports.teaching_schedule_id` unik. Sesi yang sudah dilaporkan — oleh coach utama maupun coach pendamping — tidak muncul lagi sebagai pilihan.
- Persetujuan mencatat `approved_by` dan `approved_at`.
- SPV Coach dapat melihat tetapi **tidak** dapat menyetujui/menolak.
- Dua halaman berbeda: **antrean review** (kerja reviewer) dan **arsip** (riwayat baca-saja, dikelompokkan sekolah → kelas).

Detail lengkap: [`docs/modules/reports.md`](docs/modules/reports.md).

## 11. Jadwal Mengajar

Dua lapis data:

1. **Pola** (`teaching_schedule_templates`) — sekelompok baris dengan `(day_of_week, school_id, start_date)` sama. Tanggal mulai dan jumlah pertemuan melekat pada **blok sekolah**, bukan pada form global. Tidak ada periode global; `week_start` sudah pensiun dan diabaikan bila dikirim.
2. **Sesi** (`teaching_schedules`) — pertemuan bertanggal hasil generate dari pola.

Coach pendamping disimpan di pivot `teaching_schedule_coach`; `allCoaches()` menggabungkan coach utama dan pendamping.

Karena `DELETE /admin/schedules/{schedule}` akan menangkap path seperti `schedules/pattern`, **route statis wajib didaftarkan sebelum route ber-parameter**. Ini pernah menjadi sumber bug; jangan mengubah urutannya.

Detail lengkap: [`docs/modules/schedule.md`](docs/modules/schedule.md).

## 12. Media

Media baru disimpan lewat `MediaStorageService` ke disk `report_media`, berakar di `storage/app/report-media` — **di luar** symlink publik.

- Struktur: `reports/{tahun}/{report_id}/{images|videos|attendance}/`
- Nama berkas dibuat sistem: `{Ymd_His}_{8 karakter acak}.{ekstensi tersanitasi}` — tidak pernah memakai nama asli dari pengguna.
- Batas unggah: 10 foto × 10 MB, 3 video × 100 MB, 5 bukti kehadiran × 10 MB.
- Penyajian hanya lewat `/media/{media}` dengan header `Cache-Control: private, max-age=3600`.
- Database menyimpan metadata (`path`, `type`, `original_name`, `disk`, ukuran), bukan berkas binernya.

Detail lengkap: [`docs/modules/media.md`](docs/modules/media.md).

## 13. Notifikasi, Web Push, dan Queue

Dua kelas notifikasi:

- **`CustomNotification`** — mengimplementasikan `ShouldQueue`; notifikasi operasional bertarget.
- **`ReportReminderNotification`** — memakai trait `Queueable` tetapi **tidak** `ShouldQueue`, sehingga dikirim langsung.

Kanal Web Push ditangani `WebPushChannel` dengan beberapa prinsip: tidak pernah membuat catatan notifikasi kedua, tidak pernah melempar exception, menjadi no-op bila VAPID kosong, hanya menghapus langganan pada respons 404/410 dan terbatas pada pemiliknya, serta membatasi payload pada judul dan ringkasan. Pengiriman dilakukan job `SendWebPush` (`$tries = 3`, `$backoff = [10, 60]`, `$timeout = 30`).

**Konsekuensi bagi developer: queue worker wajib berjalan.** `QUEUE_CONNECTION` default `database`. Tanpa `php artisan queue:work`, notifikasi tidak terkirim dan tabel `jobs` menumpuk. Lihat [`docs/operations/queue-worker.md`](docs/operations/queue-worker.md).

Bila VAPID kosong, fitur push menjadi no-op total dan tombol aktivasi tidak muncul — aplikasi tetap berjalan normal.

## 14. PWA

- `public/manifest.json` — sengaja berekstensi `.json`, bukan `.webmanifest`, agar tidak perlu menambah MIME type di nginx.
- `public/sw.js` — konstanta `SW_VERSION` adalah kunci cache. **Naikkan versinya setiap kali mengubah aset statis**, jika tidak pengguna akan terus menerima berkas lama.
- Navigasi selalu *network-only*: halaman berisi data pengguna tidak pernah disimpan di perangkat. Path terproteksi dilewatkan begitu saja ke jaringan.
- Handler push **tidak** menulis apa pun ke Cache Storage.

```bash
npm run test:pwa     # 37 pemeriksaan routing service worker
```

Detail lengkap: [`docs/modules/pwa.md`](docs/modules/pwa.md).

## 15. Database dan Migrasi

28 migrasi. Tabel utama dikelompokkan:

- **Inti** — `users`, `schools`, `classes`, `students`, `coach_classes`, `school_user`, `programs`, `program_classes`
- **Pelaporan** — `reports`, `report_attendances`, `report_media`
- **Jadwal** — `teaching_schedules`, `teaching_schedule_coach`, `teaching_schedule_templates`, `teaching_schedule_template_coach`
- **Operasional** — `activity_logs`, `notifications`, `push_subscriptions`, `sessions`, `jobs`

Detail kolom dan relasi: [`docs/02_DATABASE_DOKUMENTASI.md`](docs/02_DATABASE_DOKUMENTASI.md).

**Temuan terbuka:** belum ada migrasi untuk tabel `cache` dan `cache_locks`, padahal `.env.example` menyetel `CACHE_STORE=database`. Jalankan `php artisan make:cache-table` lalu `php artisan migrate`, atau alihkan ke `file`.

```bash
php artisan migrate
php artisan db:seed
```

Seeder hanya untuk pengembangan — **jangan dijalankan di produksi**. Lihat [`docs/development/seeder.md`](docs/development/seeder.md).

## 16. Menjalankan Proyek

```bash
php artisan migrate --seed
php artisan queue:work          # terminal terpisah — wajib
composer dev                    # server web + vite
```

Pengujian:

```bash
php artisan test
npm run test:pwa
```

## 17. Konvensi dan Aturan Kerja

- **Kode adalah sumber kebenaran.** Bila dokumen berbeda dengan kode, perbarui dokumennya — jangan mengubah kode agar cocok dengan dokumen.
- **Scope ditegakkan di server.** Setiap kueri data ber-scope harus melewati `AuthorizationService::accessibleSchoolIds()` atau `AttendanceScopeService`. Filter dari request hanya mempersempit; tidak pernah memperluas.
- **Capability baru** ditambahkan di `AuthorizationService::ROLE_PERMISSIONS`, bukan di view.
- **Media tetap privat.** Jangan pernah menaruh berkas laporan di bawah `public/` atau mengembalikan URL disk langsung.
- **Jangan commit `.env`.** Rahasia (kredensial database, kunci VAPID privat) tidak boleh masuk repositori.
- **Jangan menghidupkan kembali Cloudinary** atau membuat tabel/role yang sudah dihapus (`admin` dan `PIC Sekolah` bukan nama peran yang berlaku).
- **Urutan route penting** pada grup jadwal — lihat §11.
- **Naikkan `SW_VERSION`** bila mengubah aset statis PWA.
- **Migrasi bersifat inkremental** dan harus punya jalur rollback yang teruji, termasuk di MySQL.

## 18. Alur Menambah Fitur

1. Mulai dari route di `routes/web.php` — tentukan middleware/capability dan urutan yang benar.
2. Tambahkan capability ke `ROLE_PERMISSIONS` bila perlu.
3. Tambahkan migrasi bila struktur data berubah.
4. Tambahkan kolom ke `$fillable` model, lalu relasi/metode yang dibutuhkan.
5. Taruh logika non-trivial di service, bukan di controller.
6. Perbarui view Blade terkait.
7. Tambahkan test: jalur sukses, penolakan akses, dan isolasi antar-sekolah.
8. Jalankan `php artisan test` dan `npm run test:pwa`.
9. Perbarui dokumentasi terkait di `docs/` beserta tanggal sinkronisasinya.
