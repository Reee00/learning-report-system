# Modul: Media (Penyimpanan Lokal Privat)

Sinkron dengan kode: **2026-09-29**. Sumber: `app/Services/MediaStorageService.php`, `app/Models/ReportMedia.php`, `app/Http/Controllers/MediaController.php`, `config/filesystems.php`.

## Prinsip: tidak ada layanan cloud

Media laporan disimpan **di disk server sendiri**, bersifat privat, dan hanya dapat diakses lewat route terotorisasi.

> Integrasi Cloudinary sudah **dihapus sepenuhnya** dari kode. Tidak ada lagi `CloudinaryHelper`, perintah `media:migrate-cloudinary`, maupun pemanggilan API Cloudinary di `app/`, `resources/`, `routes/`, `config/`, atau `tests/`. Setiap referensi Cloudinary yang masih tersisa di dokumentasi lama bersifat historis dan tidak berlaku.

## Disk `report_media`

```php
'report_media' => [
    'driver' => 'local',
    'root'   => storage_path('app/report-media'),
    'throw'  => false,
    'report' => false,
],
```

Pemilih disk: `'report_media_disk' => env('REPORT_MEDIA_DISK', 'report_media')`.

Root berada **di luar `public/`** dan tidak ada symlink ke sana, sehingga berkas tidak dapat dijangkau lewat URL apa pun. Satu-satunya jalan masuk adalah `MediaController`. Seluruh operasi berkas melewati abstraksi Filesystem Laravel, jadi penyedia di belakangnya dapat diganti lewat konfigurasi tanpa menyentuh logika bisnis.

## Tata letak berkas

```
reports/{tahun}/{report_id}/images/{namafile}      ← type 'photo'
reports/{tahun}/{report_id}/videos/{namafile}      ← type 'video'
reports/{tahun}/{report_id}/attendance/{namafile}  ← type 'attendance'
```

`{tahun}` diambil dari `report_date` laporan (fallback: tahun berjalan). Hanya ada tiga subfolder; tipe selain `photo` dan `attendance` masuk ke `videos`.

**Nama berkas asli pengguna tidak pernah dipakai sebagai nama di disk.** Nama dibuat ulang: `{Ymd_His}_{8 karakter acak}.{ekstensi}`. Ekstensi dibersihkan menjadi alfanumerik huruf kecil (fallback `bin`), sehingga path traversal dan injeksi ekstensi tertutup. Nama asli tetap disimpan di kolom `original_name` untuk keperluan tampilan dan unduh.

## Batas unggahan

Divalidasi di `Coach\ReportController` pada `store()` **dan** `update()` dengan aturan identik:

| Kolom | Jumlah maks | Ukuran per berkas | Tipe |
|---|---|---|---|
| `photos[]` | 10 | 10 MB (`max:10240`) | `image` |
| `videos[]` | 3 | 100 MB (`max:102400`) | whitelist mimetype: mp4, mpeg, quicktime, x-msvideo, x-matroska, webm, avi, mov |
| `attendance_media[]` | 5 | 10 MB (`max:10240`) | `image` |

Batas request keseluruhan ditangani di `bootstrap/app.php`: `PostTooLargeException` dirender menjadi **JSON 413** untuk permintaan AJAX/JSON, atau redirect-back berisi pesan galat untuk form biasa. Pesannya menyebut batas server: 3 × 100 MB atau 10 × 10 MB.

## Akses berkas: `GET /media/{media}`

`ReportMedia::url()` mengembalikan `route('media.serve', ['media' => $this->id])` — bukan URL disk. Route-nya bermiddleware `auth`, dan `MediaController::serve()` memeriksa:

| Peran | Aturan |
|---|---|
| SuperAdmin, Relation, SPV Coach | Seluruh laporan |
| Coach | **Hanya laporan miliknya sendiri** (`report.coach_id`) |
| PIC DK SCHOOL, Teacher School, Finance | `canAccessSchool()` **dan** laporan berstatus `approved` |
| Peran lain | 403 |

Finance lolos `canAccessSchool()` untuk semua sekolah (scope all-school), sehingga satu-satunya penahan baginya adalah status `approved`.

Berkas di-stream dengan `response()->file()` (video besar tidak dimuat ke memori), `Content-Disposition: inline` memakai `original_name`, dan `Cache-Control: private, max-age=3600`. MIME ditebak dari ekstensi dengan peta eksplisit; fallback memakai `isVideo()`.

## Unduh berkas: `GET /media/{media}?download=1`

Tidak ada rute kedua. Tombol **Download** pada foto, bukti absensi, dan video memakai route + otorisasi yang **sama**, hanya berbeda cara penyajian: parameter `?download=1` mengubah `Content-Disposition` menjadi `attachment` (nama berkas tetap `original_name`). `ReportMedia::downloadUrl()` membangun URL ini; `ReportMedia::url()` tetap `inline` untuk pratinjau.

Konsekuensinya: berkas privat tidak pernah dipindah ke `public/`, dan siapa pun yang menekan Download melewati pemeriksaan akses yang sama seperti saat pratinjau — unauthorized → 403, tamu → redirect login.

> Asimetri yang disengaja: aturan **baca laporan** (`AuthorizationService::canAccessReport()`) mengizinkan coach pendamping membuka laporan sesi bersama, tetapi gerbang **media** di sini tetap `reports.coach_id`. Jadi coach pendamping dapat membaca teks laporan, sementara foto/videonya tidak ikut terbuka.

## Rendering di dokumen

Saat laporan diunduh/dicetak:

- **Foto** dan **bukti absensi** dirender inline lewat URL `/media/{media}` yang terotorisasi.
- **Video** tidak dapat diputar di dalam dokumen cetak, jadi dirender sebagai poster bertaut **"Lihat Video"** yang menunjuk ke halaman **detail laporan** sesuai konteks (`admin.reports.show` / `coach.reports.show` / `pic.reports.show`) — bukan ke URL berkas video. Dokumen cetak tidak memuat elemen `<video>`; pemutaran dan unduhan video terjadi di halaman detail (player + tombol Download Video).

## Penggantian berkas & pembersihan

`MediaStorageService` adalah satu-satunya penulis dan penghapus berkas media.

- `store(Report, UploadedFile, string $type)` — menyimpan berkas lalu membuat baris `ReportMedia`.
- `delete(ReportMedia)` — menghapus berkas di disk (memakai `$media->disk`, fallback disk aktif), **memangkas direktori kosong** ke atas sampai batas `reports/` (tidak pernah melewatinya), lalu menghapus baris database.
- `deleteAllForReport(Report)` — dipakai saat laporan dihapus.
- `absolutePath()` mengembalikan `null` bila berkas tidak ada di disk → controller membalas 404.

Hasilnya: tidak ada berkas yatim dan tidak ada baris database tanpa berkas.

## Migrasi dari Cloudinary

Perintah `media:migrate-cloudinary` **sudah tidak ada**. Bila masih ada berkas lama di Cloudinary, pengunduhannya harus dilakukan manual di luar aplikasi lalu ditempatkan mengikuti tata letak direktori di atas; aplikasi tidak lagi memiliki jalur otomatis untuk itu.

## Test terkait

`MediaStorageTest`, `ReportDownloadMediaTest`, `CoachReportDownloadTest`, `CoachReportDetailAndMediaTest`.
