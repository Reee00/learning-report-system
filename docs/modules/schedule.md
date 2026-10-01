# Modul: Jadwal Mengajar (Schedule)

Sinkron dengan kode: **2026-10-01**. Sumber: `app/Models/TeachingSchedule.php`, `app/Models/TeachingScheduleTemplate.php`, `app/Services/ScheduleTemplateService.php`, `app/Services/TeachingScheduleImportService.php`, `app/Http/Controllers/Admin/ScheduleController.php`, `app/Http/Controllers/Admin/ScheduleTemplateController.php`.

## Tiga lapis data

| Lapis | Tabel | Arti |
|---|---|---|
| **Template / pola** | `teaching_schedule_templates` | Rencana berulang: satu baris = satu pertemuan dalam sepekan |
| **Sesi** | `teaching_schedules` | Pertemuan konkret pada satu tanggal, hasil generate dari template |
| **Penugasan coach** | `teaching_schedule_coach` (pivot) | Coach utama + coach pendamping |

Jumlah pertemuan yang direncanakan sebuah pola adalah `meeting_count` pada baris template. Kolom inilah yang menjadi **penyebut** pada kartu progres "N/M Pertemuan Terlaksana" — lihat [reports.md](reports.md).

Satu **pola** adalah kumpulan template dengan kunci `(day_of_week, school_id, start_date)`:

- `patternKey()` — kunci pola satu baris template.
- `scopePattern($query, $day, $schoolId, $startDate)` — scope kueri ke satu pola.
- `groupIntoPatterns(Collection)` — mengelompokkan koleksi template menjadi pola.
- `endDate()` — tanggal berakhir pola.

## Sesi (`teaching_schedules`)

Kolom penting: `school_id`, `class_id`, `program_id`, `coach_id`, `template_id`, `day_of_week`, `session_date`, `start_time`, `end_time`, `departure_time`, `arrival_time`, `jalan_minggu_ini`, `is_active`.

Model menyediakan `scopeActive()`, `scopeForCoach($coachId)` (mencakup coach pendamping lewat pivot), `report()` (relasi satu-lawan-satu ke laporan), `allCoaches()`, dan `additionalCoaches()`.

Nilai waktu dinormalisasi otomatis lewat mutator (`setStartTimeAttribute`, `setEndTimeAttribute`, `setDepartureTimeAttribute`, `setArrivalTimeAttribute`) dan hook `saving` pada model.

### `is_active` (aturan final 2026-09-28)

- Boolean, default `true`, ditambahkan **setelah** `jalan_minggu_ini`.
- Indeks gabungan `(is_active, session_date)` untuk kueri "sesi aktif hari ini".
- Sesi nonaktif tidak ditawarkan di form pembuatan laporan dan tidak dihitung pada reminder, tetapi tetap terlihat (redup) di daftar jadwal agar riwayat tidak hilang.
- **Nonaktif ≠ hapus.** Menonaktifkan sesi tidak menghapus laporan yang sudah ada dan tidak melepas tautan `reports.teaching_schedule_id`.
- Dibalik lewat `PATCH admin/schedules/{schedule}/active`.

## Coach pendamping

- Pivot `teaching_schedule_coach` menyimpan seluruh coach pada satu sesi (dan pada template).
- `additionalCoaches()` mengembalikan coach selain coach utama; `allCoaches()` mengembalikan seluruhnya.
- Coach pendamping dapat membuat laporan untuk sesi itu dan dapat mengunduh laporan yang disetujui dari sesi itu, tetapi tetap terikat aturan **satu sesi = satu laporan** (lihat [reports.md](reports.md)).

## `ScheduleTemplateService`

| Metode | Fungsi |
|---|---|
| `build(User, array $payload)` | Menyusun baris template dari payload form, memvalidasi bentrokan, melaporkan `warnings` |
| `generate(TeachingScheduleTemplate, bool $checkConflicts)` | Membuat sesi dari satu template untuk seluruh tanggal kandidat |
| `candidateDates(TeachingScheduleTemplate)` | Daftar tanggal yang mungkin untuk pola |
| `renumberSessions(TeachingScheduleTemplate)` | Menomori ulang pertemuan (`pertemuan ke-N`) |
| `paginatedPatterns(User, ?int $day, array $filters, int $perPage)` | Daftar pola ter-scope + filter (termasuk filter coach lewat pivot) |
| `findPattern(User, int $day, int $schoolId, string $startDate)` | Detail satu pola |
| `deletePattern(User, int $day, int $schoolId, string $startDate)` / `generatePattern(...)` | Hapus / generate satu pola (lihat **Hapus pola** di bawah) |
| `sessionExists()` | **Idempotensi**: sesi untuk (template, tanggal) tidak dibuat dua kali |
| `coachConflictOnDate()` | Mencegah satu coach dijadwalkan dua kali pada jam bentrok |
| `conflictingPatternExists()` | Mencegah pola bentrok di kelas yang sama |

Scope ditegakkan lewat `scopedSchoolIds()` / `scopePatternQuery()` / `assertPatternScope()`, sehingga pengguna non-global hanya melihat dan mengubah pola pada sekolah dalam scope-nya.

**Regenerasi tidak destruktif**: sesi yang sudah ada dilewati, sesi nonaktif tidak disentuh, laporan tidak pernah dihapus.

## Hapus pola / jadwal sekolah (aturan final 2026-10-01)

Sesi hasil generate **bukan** riwayat historis: ia hanya wujud konkret dari sebuah konfigurasi. Karena itu pola dan sesi hasil generate-nya dihapus bersama, supaya tidak ada sesi yatim yang masih tampil di jadwal.

Aturannya bergantung pada ada tidaknya riwayat, dan diperiksa lewat `TeachingSchedule::withHistory()` / `hasHistory()`:

| Keadaan sesi | Akibat |
|---|---|
| Belum punya laporan/absensi | Dihapus bersama pola, di dalam satu transaksi |
| Sudah punya laporan (**status apa pun** — `draft`, `submitted`, `approved`, `rejected`) atau absensi/media | **Seluruh penghapusan DITOLAK**, dengan pesan error yang menyebut jumlah pertemuan yang menghalangi |

Poin-poin yang mengikat:

- Riwayat tidak pernah dihapus demi merapikan konfigurasi. **Tidak ada** `cascade` brutal ke `reports`/`report_attendances`/`report_media`.
- Penolakan bersifat **semua-atau-tidak-sama-sekali**: bila satu sesi saja ber-riwayat, tidak ada sesi/template yang dihapus sebagian.
- Laporan lama tanpa tautan sesi (`reports.teaching_schedule_id IS NULL`) tetap dikenali lewat kecocokan `class_id` + `report_date`, sehingga sesi yang laporannya dibuat sebelum migrasi tautan pun tidak pernah bisa terhapus diam-diam.
- Guard yang sama berlaku untuk tiga pintu: `admin.schedules.pattern.destroy`, `admin.schedules.templates.destroy`, dan `admin.schedules.destroy` (hapus satu sesi).
- **Nonaktif ≠ hapus**, dan sebaliknya: menonaktifkan sesi tetap tidak menghapus apa pun, tetapi sesi nonaktif **ikut** terhapus bila polanya dibuang dan ia belum ber-riwayat.

## `TeachingScheduleImportService` — impor Excel

Impor massal jadwal dari berkas `.xlsx` (`POST admin/schedules/import`, capability `schedules.manage`) memakai FastExcel.

- Mendukung dua bentuk berkas: **format normal** (satu baris per sesi) dan **workbook per-perusahaan** dengan satu sheet per hari (`hasCompanySheets()`, `companyColumnMap()`, `dayFromSheetName()`).
- Kolom `Ket` dapat memuat **tanggal mulai**; nilai itu diurai (`parseKetStartDate()`) lalu dibersihkan dari keterangan (`stripKetStartDate()`).
- `Jam Kelas` diurai menjadi pasangan jam mulai–selesai (`parseJamKelas()`, `normalizeTimePair()`).
- Nama sekolah, kelas, program, dan coach dinormalisasi agar cocok dengan data master (`normalizeSchoolName()`, `normalizeClassName()`, `normalizePersonName()`, `schoolsByNormalName()`, `coachesByNormalName()`, `resolveProgram()`).
- Coach pendamping dibaca dari daftar email pada satu sel (`parseAdditionalCoachEmails()`).
- `patternRowExists()` dan `duplicateExists()` mencegah baris ganda saat impor diulang.
- Scope pengguna tetap ditegakkan (`scopedSchoolIds()`); pengguna non-global tidak dapat mengimpor untuk sekolah di luar scope-nya.

Ketika sebuah nama tidak dapat dipetakan ke data master, baris tersebut dilaporkan sebagai peringatan dan tidak menghentikan keseluruhan impor.

## Route terkait

| Method | URI | Name | Capability |
|---|---|---|---|
| GET | `admin/schedules` | `admin.schedules.index` | `schedules.view` |
| GET | `admin/schedules/create` | `admin.schedules.create` | `schedules.manage` |
| POST | `admin/schedules` | `admin.schedules.store` | `schedules.manage` |
| POST | `admin/schedules/bulk` | `admin.schedules.bulk.store` | `schedules.manage` |
| GET | `admin/schedules/class-students/{class}` | `admin.schedules.class-students` | `schedules.manage` |
| GET | `admin/schedules/pattern` | `admin.schedules.pattern.show` | `schedules.view` |
| POST | `admin/schedules/pattern/generate` | `admin.schedules.pattern.generate` | `schedules.manage` |
| DELETE | `admin/schedules/pattern` | `admin.schedules.pattern.destroy` | `schedules.manage` |
| GET | `admin/schedules/templates` | `admin.schedules.templates` | `schedules.manage` |
| POST | `admin/schedules/templates/{template}/generate` | `admin.schedules.templates.generate` | `schedules.manage` |
| DELETE | `admin/schedules/templates/{template}` | `admin.schedules.templates.destroy` | `schedules.manage` |
| GET | `admin/schedules/{schedule}/edit` | `admin.schedules.edit` | `schedules.manage` |
| PUT | `admin/schedules/{schedule}` | `admin.schedules.update` | `schedules.manage` |
| DELETE | `admin/schedules/{schedule}` | `admin.schedules.destroy` | `schedules.manage` |
| PATCH | `admin/schedules/{schedule}/active` | `admin.schedules.toggle-active` | `schedules.manage` |
| POST | `admin/schedules/import` | `admin.schedules.import` | `schedules.manage` |
| GET | `admin/schedules/template` | `admin.schedules.template` | `schedules.manage` |
| GET | `admin/schedules/semester` | `admin.schedules.semester` | `schedules.manage` |
| POST | `admin/schedules/semester` | `admin.schedules.semester.store` | `schedules.manage` |

Semua rute jadwal berada di grup `admin` (prefix `admin/`, name `admin.`). **Tidak ada rute `coach/schedules`** — coach tidak memiliki halaman jadwal tersendiri; ia melihat sesinya lewat form pembuatan laporan dan halaman `/coach/reports`.

**Urutan pendaftaran rute itu penting.** `schedules/pattern`, `schedules/templates`, dan `schedules/import` wajib didaftarkan **sebelum** `schedules/{schedule}`, jika tidak segmen literal itu akan tertangkap sebagai parameter `{schedule}`.

Capability `schedules.view` dimiliki Relation, SPV Coach, Coach, PIC DK SCHOOL, SuperAdmin. `schedules.manage` dimiliki Relation, PIC DK SCHOOL, SuperAdmin.

## Test terkait

`ScheduleManagementTest`, `ScheduleBulkTest`, `ScheduleRegenerationTest`, `ScheduleSessionActiveTest`, `SemesterScheduleTest`, `SharedCoachAssignmentTest`, `RelationCoachAssignmentTest`, `SchedulePatternDeletionTest`.
