# Modul: Laporan (Report)

Sinkron dengan kode: **2026-09-29**. Sumber: `app/Models/Report.php`, `app/Http/Controllers/Coach/ReportController.php`, `app/Http/Controllers/Admin/ReportController.php`, `app/Services/ReportReminderService.php`, `app/Services/AuthorizationService.php`, `resources/views/coach/reports/{index,show}.blade.php`, `database/migrations/2026_09_28_000001_add_teaching_schedule_to_reports.php`.

## Model

`Report` (`reports`) — kolom yang dapat diisi:

| Kolom | Isi |
|---|---|
| `coach_id` | Coach pembuat laporan |
| `school_id`, `class_id` | Sekolah & kelas sesi |
| `teaching_schedule_id` | Sesi mengajar yang dilaporkan (nullable, `nullOnDelete`, **UNIQUE**) |
| `report_date` | Tanggal laporan (cast `date`) |
| `lesson_material`, `goals_materi`, `activity_report` | Isi laporan pembelajaran |
| `notes` | Catatan tambahan — termasuk catatan kecelakaan/**Accident Notes** (lihat [Accident Notes](#accident-notes-pengingat-pribadi-coach)) |
| `status` | `draft` \| `submitted` \| `approved` \| `rejected` |
| `admin_notes` | Alasan penolakan (wajib saat reject) |
| `approved_by`, `approved_at` | Reviewer & waktu approval (cast `datetime`) |

Relasi: `coach()`, `school()`, `schoolClass()`, `teachingSchedule()`, `approvedBy()`, `attendances()`, `media()`, plus `photos()`, `videos()`, `attendanceMedia()`.

## Siklus hidup

```
draft ──submit──> submitted ──approve──> approved
                     │
                     └──reject (wajib admin_notes)──> rejected ──perbaiki & submit──> submitted
```

- Coach membuat laporan dari sesi mengajar yang ditugaskan, mengisi materi/tujuan/aktivitas, absensi murid, dan media, lalu menyimpan `draft` atau mengirim (`submitted`).
- Coach **hanya** dapat mengedit laporan berstatus `draft` atau `rejected` — ditegakkan di backend (`abort_if(! in_array($report->status, ['draft','rejected']), 403)`), bukan hanya disembunyikan di UI.
- Reviewer (Relation/SuperAdmin, capability `reports.review`) memutuskan approve atau reject. Reject mewajibkan `admin_notes`.
- Approve mencatat `approved_by` dan `approved_at`.

## Aturan final: SATU SESI = SATU LAPORAN (2026-09-28)

Sebelumnya keunikan laporan hanya bisa dinyatakan pada (coach, kelas, tanggal), sehingga dua coach pada sesi yang sama sama-sama sah membuat baris laporan.

Sekarang `reports.teaching_schedule_id` menunjuk langsung ke sesi, dengan **indeks unik**. Konsekuensinya:

- Satu sesi mengajar maksimum memiliki satu laporan, siapa pun pembuatnya (coach utama maupun coach pendamping).
- Validasi aplikasi menutup kasus normal; indeks unik menutup **celah balapan** dua request bersamaan. Pesan error duplikat dikenali dari `reports_teaching_schedule_id_unique` / `UNIQUE constraint failed: reports.teaching_schedule_id`.
- Sesi yang sudah punya laporan tidak ditawarkan lagi di form pembuatan laporan.
- **Sesi nonaktif** (`teaching_schedules.is_active = false`) tidak menerima laporan baru.
- Kolomnya **nullable**: laporan lama tetap sah. Backfill hanya menautkan laporan yang kandidat sesinya tunggal dan belum diklaim laporan lain — tidak ada baris yang dihapus atau digabung.

## Review vs Arsip

Dua halaman berbeda dengan maksud berbeda (aturan final 2026-09-28):

| Halaman | Route | Capability | Isi |
|---|---|---|---|
| **Antrean Review** | `GET admin/reports/review` | `reports.review` | Hanya laporan `submitted` dan `rejected` — daftar kerja reviewer. Dilengkapi hitungan `pendingCount` (menunggu keputusan) dan `rejectedCount` (perlu dikoreksi), dihitung dari kumpulan ter-scope yang sama. |
| **Arsip Laporan** | `GET admin/reports` | `reports.view_all` | Riwayat lengkap per sekolah → kelas, **baca-saja**. Tidak ada salinan data; menampilkan laporan yang sama. Filter sekolah/kelas/coach/status/rentang tanggal. |

Rute `reports/review` **wajib didaftarkan sebelum** `reports/{report}` agar kata "review" tidak tertangkap sebagai id.

Scope sekolah selalu diterapkan **sebelum** filter request, sehingga parameter filter hanya dapat mempersempit — tidak pernah melewati batas akses.

## Akses baca satu laporan (2026-09-29)

Aturan akses tingkat-laporan hanya ditulis **satu kali**, di `AuthorizationService::canAccessReport()`:

| Peran | Aturan |
|---|---|
| Coach | Laporan **miliknya** (`reports.coach_id`) **atau** laporan dari sesi mengajar tempat ia terlibat — coach utama maupun coach pendamping (`teaching_schedules` lewat `scopeForCoach`). Batasnya per **sesi**, bukan per sekolah: coach tidak membuka laporan kelas lain di sekolah yang sama. |
| Peran lain | Scope sekolah yang sudah berlaku (`canAccessSchool()`), tidak berubah. |

Role coach sengaja **tidak** memakai `canAccessSchool()`: `accessibleSchoolIds()` mengembalikan `null` (global) untuk coach, sehingga scope sekolah akan meloloskannya ke seluruh sekolah.

Dipakai bersama oleh tiga pintu:

- `GET coach/reports/{report}` (`coach.reports.show`) — halaman **detail laporan coach** (baca-saja, status apa pun).
- `GET coach/reports/{report}/download` (`coach.reports.download`).
- `GET admin/reports/{report}/download` (`admin.reports.download`) — untuk role coach, cabang ini memakai `canAccessReport()`, **bukan** `ensureSchoolAccess()`. Sebelum perbaikan 2026-09-29, coach mendapat **HTTP 200** di rute admin untuk laporan coach lain karena rutenya hanya membutuhkan capability `reports.download` (yang dimiliki coach) sementara scope sekolah meloloskannya. Sekarang unauthorized coach → **403**. Role lain tetap memakai scope sekolah seperti semula.

## Halaman detail laporan coach

`GET coach/reports/{report}` → view `coach/reports/show.blade.php`. Menampilkan isi laporan apa adanya dari kolom `reports`: termasuk **`lesson_material`** yang diisi coach pada form "Materi Pelajaran" — tidak ada nilai yang diambil ulang dari jadwal/kelas/program. Untuk laporan milik coach sendiri: catatan admin saat `rejected`, panel "Edit & Kirim Ulang", dan tombol Download Report saat `approved`. Laporan sesi bersama milik coach utama boleh dibaca coach pendamping, tetapi tanpa panel aksi.

## Materi yang Dipelajari di daftar laporan

Daftar `coach.reports.index` menampilkan kolom **"Materi yang Dipelajari"** dengan nilai `reports.lesson_material` apa adanya, ditambah label `Pertemuan N` dari `teaching_schedules.meeting_number` **bila** laporan tertaut sesi (`reports.teaching_schedule_id`) dan materinya belum berawalan "Pertemuan"/"Week". Kolom `teaching_schedules.topic` tidak pernah dipakai. Halaman detail memakai aturan label yang sama, sehingga nilai di daftar dan di detail selalu identik.

## Status yang dianggap TERLAKSANA (aturan final 2026-10-01)

`Report::COMPLETED_STATUSES` adalah **satu-satunya** definisi "pertemuan ini sudah terlaksana":

```php
public const COMPLETED_STATUSES = [self::STATUS_SUBMITTED, self::STATUS_APPROVED];
```

- `submitted` dan `approved` dihitung terlaksana.
- `rejected` **tidak** dihitung: laporan yang ditolak belum menyelesaikan pertemuan dan harus diperbaiki lalu dikirim ulang.
- `draft` (warisan lama) juga tidak dihitung — bukan bagian dari siklus hidup saat ini.

Konstanta ini dipakai bersama oleh `ReportReminderService` (menentukan sesi yang belum dilaporkan) dan oleh metrik progres di bawah, sehingga keduanya tidak mungkin berbeda pendapat.

## Progres "N/M Pertemuan Terlaksana"

Kartu progres pada daftar jadwal (`SchoolClass::sessionProgressFor()`) memakai sumber kebenaran **backend/database**, bukan hitungan JavaScript.

- **Penyebut (M)** = `meeting_count` pada pola/kelas tersebut (target pertemuan).
- **Pembilang (N)** = jumlah **sesi unik** dalam scope kelas/pola yang sudah punya laporan berstatus `COMPLETED_STATUSES`.

Aturan yang dikunci:

- Penghitungan dimulai **dari sisi sesi** (`teaching_schedules`), lalu `EXISTS` ke `reports` — bukan dari jumlah baris laporan. Karena `reports.teaching_schedule_id` UNIQUE, satu sesi maksimal dihitung **sekali**, berapa kali pun laporannya disunting atau dikirim ulang. Tidak ada "+2" saat resubmit.
- Sesi yang hanya **digenerate** tanpa laporan tidak dihitung terlaksana.
- Sesi `cancelled` tidak pernah menambah progres.
- Laporan lama tanpa tautan sesi dicocokkan lewat `class_id` + `report_date` (fallback yang sama dengan `withHistory()`), supaya belum pernah terhitung dua kali.
- Ditolaknya sebuah laporan menurunkan progres kembali mengikuti siklus hidup yang berlaku — bukan aturan baru.

Contoh: target 20 → sebelum ada laporan `0/20` → laporan Pertemuan 1 dikirim `1/20` → Pertemuan 2 dikirim `2/20` → laporan Pertemuan 1 ditolak `1/20` → dikirim ulang `2/20` (bukan `3/20`).

## Unduh / cetak

`GET admin/reports/{report}/download` dan `GET coach/reports/{report}/download` (capability `reports.download`; PIC memakai `pic.reports.download`).

- Hanya laporan berstatus `approved` yang dapat diunduh.
- Coach dapat mengunduh laporan miliknya sendiri, atau laporan dari sesi tempat ia mengajar (coach pendamping) — lewat `AuthorizationService::canAccessReport()`.
- Media foto dan bukti absensi dirender inline melalui URL terotorisasi `/media/{media}`.
- **Video** dirender sebagai poster bertaut **"Lihat Video"** yang menunjuk ke halaman **detail laporan** sesuai konteks (`admin.reports.show` / `coach.reports.show` / `pic.reports.show`), bukan ke URL berkas video. Tidak ada elemen `<video>` di dokumen cetak — video diputar dan diunduh dari halaman detail. Lihat [media.md](media.md).

## Accident Notes (pengingat pribadi coach)

Accident Notes adalah isi kolom `reports.notes` dan **tidak** menambah tabel atau kolom baru.

- Punya halaman sendiri: `GET coach/accident-notes` (`coach.accident-notes.index`, `role:coach` + `permission:accident_notes.view`), berisi catatan milik **coach yang login** saja, terbaru lebih dulu, dengan tautan "Lihat Laporan" ke laporan sumbernya.
- **Bukan** notification center: tidak ada database notification, web push, atau item notification center yang dibuat dari catatan ini (diverifikasi `CoachReportDetailAndMediaTest`).
- Tidak lagi ditampilkan di halaman "Laporan Saya" (`coach.reports.index`) — pemindahan penyajian saja, data tidak diubah.

## Reminder laporan

`ReportReminderService` menilai kelengkapan **per sesi**, bukan per coach:

- Sebuah sesi dianggap selesai begitu **ada** satu laporan untuk sesi itu — siapa pun pembuatnya.
- Rujukan utama `reports.teaching_schedule_id`. Kecocokan lama (kelas + tanggal, tanpa syarat coach yang sama) hanya dipakai sebagai cadangan untuk laporan yang belum punya tautan sesi (data sebelum migrasi).
- Hanya sesi **aktif** pada hari ini atau sebelumnya yang dihitung.
- Relation/SuperAdmin dapat mengingatkan semua coach (`POST admin/reports/remind`, `reports.remind`); PIC hanya coach di sekolah plot-nya (`POST pic/remind`).

Detail kanal pengiriman: lihat [notifications.md](notifications.md).

## Activity log

Aksi yang tercatat: `report.submitted`, `report.resubmitted`, `report.approved`, `report.rejected`, `report.reminder_sent`.

## Route terkait

| Method | URI | Name | Capability |
|---|---|---|---|
| GET | `coach/reports` | `coach.reports.index` | `reports.view` (+ `role:coach`) |
| GET | `coach/reports/create` | `coach.reports.create` | `reports.create` |
| POST | `coach/reports` | `coach.reports.store` | `reports.create` |
| GET | `coach/reports/{report}` | `coach.reports.show` | `reports.view` (+ `role:coach`) |
| GET | `coach/reports/{report}/edit` | `coach.reports.edit` | `reports.update` |
| PUT | `coach/reports/{report}` | `coach.reports.update` | `reports.update` |
| GET | `coach/reports/{report}/download` | `coach.reports.download` | `reports.download` |
| GET | `coach/accident-notes` | `coach.accident-notes.index` | `accident_notes.view` (+ `role:coach`) |
| GET | `admin/reports` | `admin.reports.index` | `reports.view_all` |
| GET | `admin/reports/review` | `admin.reports.review` | `reports.review` |
| GET | `admin/reports/{report}` | `admin.reports.show` | `reports.view_all` |
| PATCH | `admin/reports/{report}/approve` | `admin.reports.approve` | `reports.review` |
| PATCH | `admin/reports/{report}/reject` | `admin.reports.reject` | `reports.review` |
| GET | `admin/reports/{report}/download` | `admin.reports.download` | `reports.download` |
| POST | `admin/reports/remind` | `admin.reports.remind` | `reports.remind` |

## Test terkait

`CoachReportAuthorizationTest`, `CoachReportAtomicityTest`, `CoachReportDownloadTest`, `CoachReportDetailAndMediaTest`, `ReportArchiveTest`, `ReportGroupingTest`, `ReportDownloadMediaTest`, `SessionReportOwnershipTest`, `EndToEndFlowTest`, `MeetingRequirementsTest`.
