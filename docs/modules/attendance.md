# Modul: Kehadiran (Attendance)

Sinkron dengan kode: **2026-10-01**. Sumber: `app/Http/Controllers/AttendanceController.php`, `app/Services/AttendanceScopeService.php`, `app/Services/AttendanceExportService.php`, `app/Models/ReportAttendance.php`.

## Prinsip

Kehadiran **berasal dari laporan**, bukan tabel terpisah. `report_attendances` menyimpan satu baris per (laporan, murid), dengan indeks unik `(report_id, student_id)`. Tidak ada modul absensi mandiri.

## Alur drill-down: Sekolah → Kelas → Tanggal → Murid

UX final 2026-09-13. Semua halaman di bawah capability `attendance.view`:

| Langkah | Route | Isi |
|---|---|---|
| 1. Daftar | `GET /attendance` | Dikelompokkan per **tanggal sesi** (accordion layar penuh, 15 tanggal per halaman) |
| 2. Sekolah | `GET /attendance/schools/{school}` | Rincian satu sekolah |
| 3. Kelas | `GET /attendance/schools/{school}/classes/{class}` | Rincian satu kelas; 404 bila kelas bukan milik sekolah pada URL |
| 4. Sesi | `GET /attendance/sessions/{report}` | Absensi satu sesi laporan; 404 bila laporan di luar scope |

**Akumulasi kehadiran (TOTAL HADIR) tidak ditampilkan di halaman mana pun.** Keputusan UX 2026-09-13: akumulasi hanya ada di dokumen unduh/cetak. Halaman index, sekolah, kelas, dan sesi menampilkan kehadiran per sesi saja. Route `GET /attendance/summary` dan panel kanan akumulasi **sudah dihapus** — jangan merujuknya.

## Scope (ditegakkan di backend)

`AttendanceScopeService::query()` menerapkan scope pada laporan induk sebelum filter request apa pun:

- **SuperAdmin, Relation, SPV Coach** — operasional global.
- **Coach** — hanya laporan miliknya sendiri.
- **PIC DK SCHOOL, TEACHER SCHOOL** — hanya sekolah yang diplot (`school_user`, ditambah `users.school_id` legacy), dan hanya laporan `approved`.
- **Finance** — **all-school** (scope datang dari role, bukan plot sekolah), tetap hanya laporan `approved`.

Filter sekolah/kelas/tanggal/status **hanya mempersempit**; tidak pernah memperluas scope.

### Finance — akses GLOBAL (aturan final 2026-10-01)

Finance **tidak memerlukan plotting sekolah**. Scope seluruh sekolah datang dari role: `AuthorizationService::accessibleSchoolIds()` mengembalikan `null` (global) untuk Finance, dan Finance sengaja **tidak** termasuk `User::schoolScopedRoles()`. Karena itu:

- Finance tidak perlu — dan tidak boleh diwajibkan — punya baris `school_user` hanya untuk mendapat akses attendance.
- Finance dapat menelusuri keempat tingkat drill-down (Attendance → Sekolah → Kelas → Tanggal → Murid) untuk sekolah mana pun.
- Filter sekolah/kelas/tanggal bekerja sama seperti role lain.
- Finance dapat mengekspor kehadiran **lintas sekolah** dalam format CSV, Excel, maupun PDF, dan hasilnya mengikuti filter yang dipilih (aturan final 2026-10-01).
- Batas yang tersisa hanyalah **status approval**: Finance tetap hanya melihat laporan `approved` (aturan lama, tidak diubah).

Scope di atas ditegakkan di server (`AttendanceScopeService` + `canAccessSchool()`), bukan di tampilan. `canAccessClass()` **tidak** dipakai sebagai gerbang halaman attendance: predikat itu adalah wewenang *pengelolaan* kelas (dipakai modul siswa) yang menolak role tanpa penugasan kelas — termasuk Finance.

**Finance tetap tidak memperoleh capability lain.** Yang dimilikinya hanya `attendance.view` dan `attendance.export`; master data, review laporan, manajemen coach, jadwal, dan roster siswa tetap 403.

## Export

`GET /attendance/export` — middleware `permission_any:attendance.export,attendance.export_csv`.

| Format | Parameter | Capability wajib |
|---|---|---|
| CSV | (default) | `attendance.export` **atau** `attendance.export_csv` |
| Excel (`.xlsx`) | `?format=excel` atau `?format=xlsx` | `attendance.export` |
| PDF | `?format=pdf` | `attendance.export` |

Finance memegang `attendance.export` (capability penuh), sehingga **ketiga format** tersedia baginya dan tombolnya dirender di tampilan. Aturan lama QA M-001 — yang membatasi Finance ke CSV saja — **digantikan** oleh kebutuhan review meeting 2026-10-01: Finance memakai CSV, Excel, dan PDF untuk pelaporan.

`attendance.export_csv` tetap terdefinisi di middleware route dan di [permissions.md](../reference/permissions.md) sebagai capability yang lebih sempit (CSV data mentah saja), tetapi kini **tidak dipegang role mana pun**. Memegang keduanya sekaligus tidak masuk akal karena `attendance.export` sudah mencakup CSV; capability yang sempit itu disimpan untuk peran "data mentah saja" bila suatu saat dibutuhkan.

Isi dokumen:
- CSV — matriks sekolah/kelas/murid dengan kolom tanggal, ditambah kolom terakhir **`TOTAL HADIR`** (jumlah status `Hadir` per murid dari kumpulan data ter-scope yang sama; kunci matriks unik per murid+tanggal sehingga duplikat tidak mungkin terhitung ganda).
- Excel — satu sheet per blok kelas, nama sheet unik, siap cetak (orientasi & ukuran halaman diatur). Karena satu workbook memuat banyak sheet dan nama sheet dijamin unik, export lintas sekolah tetap aman.
- PDF — A4 landscape, pemisahan sekolah → kelas, page break antar kelas, kolom `TOTAL HADIR` tebal di paling kanan.

Matriks dibangun dengan `chunk(1000)` sehingga koleksi model tidak pernah dimaterialisasi penuh (QA M-003).

Pemetaan status kehadiran → label hanya didefinisikan **satu kali** di `AttendanceExportService` dan dipakai seluruh format; tidak boleh ada definisi ganda.

## Media bukti absensi

Foto bukti absensi disimpan sebagai `report_media` bertipe `attendance` (maks 5 foto × 10 MB per laporan), di `reports/{year}/{id}/attendance/`. Lihat [media.md](media.md).

## Route terkait

| Method | URI | Name | Capability |
|---|---|---|---|
| GET | `/attendance` | `attendance.index` | `attendance.view` |
| GET | `/attendance/schools/{school}` | `attendance.school` | `attendance.view` |
| GET | `/attendance/schools/{school}/classes/{class}` | `attendance.class` | `attendance.view` |
| GET | `/attendance/sessions/{report}` | `attendance.session` | `attendance.view` |
| GET | `/attendance/export` | `attendance.export` | `attendance.export` \| `attendance.export_csv` |

## Test terkait

`AttendanceGroupingTest`, `AttendanceExcelPdfExportTest`, `AttendanceMysqlOnlyFullGroupByTest` (butuh `TEST_MYSQL_*`; di-skip pada SQLite), `CrossSchoolSecurityTest`, `RoleIsolationTest`, `FinanceAttendanceAccessTest`.
