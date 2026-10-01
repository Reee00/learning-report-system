# Proses Bisnis

Disinkronkan dengan kode: **2026-09-28**.

## Persiapan Data

Relation/SuperAdmin mengelola Sekolah, Program, Kelas, Murid, dan Pengguna. SPV Coach mengelola akun Coach dan penugasan Coach ke kelas.

**School Workspace** (2026-09-24) menjadikan halaman detail sekolah sebagai satu tempat persiapan: informasi sekolah, kelas yang ter-assign, dan program per kelas. Dari sana dapat dibuat kelas baru, dipindahkan kelas master yang masih kosong dari sekolah lain, diubah namanya, dipasang/dilepas programnya, dan dihapus kelas yang belum terpakai. Semuanya tetap memakai `SchoolClass` + pivot `program_classes` — **tidak ada tabel kelas kedua**.

## Jadwal Mengajar

Jadwal dibangun dalam dua lapis:

1. **Pola** (`teaching_schedule_templates`) — rencana berulang, dengan identitas **(hari + sekolah + tanggal mulai)**. Setiap sekolah punya `start_date` dan `meeting_count` sendiri; tidak ada periode global.
2. **Sesi** (`teaching_schedules`) — pertemuan konkret per tanggal, hasil *generate* dari pola atau impor Excel.

Setiap sesi dapat dinonaktifkan (`is_active = false`) tanpa dihapus. Sesi nonaktif tidak menerima laporan baru dan tidak dihitung pada reminder, tetapi riwayatnya tetap terlihat.

Generate bersifat **idempoten**: sesi yang sudah ada dilewati, dan "Generate" hanya menambah pertemuan yang kurang. Libur atau pergeseran tanggal ditangani dengan menghapus/memindahkan sesi individual. Menghapus pola **tidak** menghapus sesi yang sudah dibuat.

Detail: [modules/schedule.md](modules/schedule.md).

## Laporan Coach

1. Coach membuka sesi mengajar yang ditugaskan (sebagai coach utama atau coach pendamping).
2. Coach membuat laporan: tanggal, materi, tujuan materi, aktivitas, catatan, absensi murid, dan media (foto/video/bukti absensi).
3. Coach menyimpan `draft` atau mengirim (`submitted`).
4. Reviewer (Relation/SuperAdmin) meninjau di **antrean review**.
5. Reviewer memilih approve atau reject. **Reject mewajibkan `admin_notes`.**
6. Coach hanya dapat mengedit laporan berstatus `draft` atau `rejected`. Setelah diperbaiki, laporan dikirim ulang menjadi `submitted`.
7. Approve mencatat reviewer dan waktu approval.

### Aturan satu sesi = satu laporan (2026-09-28)

`reports.teaching_schedule_id` menunjuk langsung ke sesi dan bersifat **unik**. Satu sesi maksimum memiliki satu laporan, siapa pun pembuatnya. Indeks unik menutup celah balapan dua permintaan bersamaan, bukan hanya mengandalkan validasi aplikasi. Kolomnya nullable agar laporan lama tetap sah; backfill hanya menautkan kandidat yang tunggal dan belum diklaim.

### Review vs Arsip

Dua halaman berbeda, dua capability berbeda:

- **Antrean review** (`reports.review`) — hanya laporan `submitted` dan `rejected`. Ini daftar kerja reviewer.
- **Arsip** (`reports.view_all`) — riwayat lengkap per sekolah → kelas, baca-saja, dengan filter.

Detail: [modules/reports.md](modules/reports.md).

## Kehadiran

Kehadiran **berasal dari laporan**, bukan tabel terpisah. Tidak ada modul absensi mandiri.

Alur drill-down: `/attendance` (per tanggal sesi) → sekolah → kelas → sesi laporan.

**Akumulasi (TOTAL HADIR) tidak ditampilkan di halaman mana pun** — hanya ada di dokumen unduh/cetak. Ini keputusan UX 2026-09-13.

Scope:

| Peran | Cakupan |
|---|---|
| Relation, SuperAdmin, SPV Coach | Global |
| Coach | Laporan sendiri |
| PIC DK SCHOOL, TEACHER SCHOOL | Sekolah yang diplot, hanya laporan `approved` |
| Finance | Seluruh sekolah, hanya laporan `approved`; export **CSV saja** |

Filter sekolah/kelas/tanggal/status hanya mempersempit scope, tidak pernah memperluasnya.

Detail: [modules/attendance.md](modules/attendance.md).

## Tampilan Sekolah

- **PIC** — dasbor sendiri (`/pic/dashboard`), laporan `approved`, kehadiran, export, dan pengingat ke coach di sekolah plot-nya.
- **TEACHER SCHOOL** — memakai view bersama; laporan `approved`, kehadiran, export.
- **Finance** — kehadiran seluruh sekolah dan **hanya export CSV**; tidak ada dasbor Finance terpisah.

## Media

Media disimpan oleh `MediaStorageService` pada disk privat `report_media`, di luar `public/`, dan hanya disajikan lewat route terotorisasi `/media/{media}`.

Integrasi Cloudinary **sudah dihapus penuh** dari kode — tidak ada helper, tidak ada perintah migrasi, tidak ada pemanggilan API. Berkas lama di Cloudinary (bila ada) harus dipindahkan manual di luar aplikasi.

Detail: [modules/media.md](modules/media.md).

## Notifikasi dan Pengingat

Notifikasi manual dikirim Relation (semua coach) atau PIC (hanya coach di sekolah plot-nya), dengan tiga jenis: Perubahan Jadwal, Reminder Laporan, Warning/Info Operasional. Target di luar scope **ditolak server-side**.

Pengingat laporan dinilai **per sesi**: sebuah sesi dianggap selesai begitu ada satu laporan untuk sesi itu, siapa pun pembuatnya. Pengingat hanya dipicu manusia — tidak ada penjadwalan otomatis.

Notifikasi keluar lewat kanal `database` **dan** Web Push. Karena `CustomNotification` diantrekan dan push selalu lewat job, aplikasi **memerlukan queue worker**. Detail: [modules/notifications.md](modules/notifications.md), [modules/web-push.md](modules/web-push.md), [operations/queue-worker.md](operations/queue-worker.md).
