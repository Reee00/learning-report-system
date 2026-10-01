# Arsitektur Sistem

Disinkronkan dengan kode: **2026-09-28**.

## Bentuk Aplikasi

Laravel MVC dengan Blade, Eloquent, middleware, service layer, dan job antrean. Seluruh route web berada di `routes/web.php`; **tidak ada `routes/api.php`**. Satu-satunya route berkas `console` adalah penjadwalan (`routes/console.php`).

## Lapisan

```
Browser
  └─> middleware (auth, role, permission, permission_any)
        └─> Controller (Admin / Coach / SchoolPic / Attendance / Student / Media / PushSubscription / Auth)
              └─> Service (otorisasi, scope, export, media, jadwal, notifikasi)
                    └─> Model Eloquent ──> Database
                          └─> Notification ──> kanal database + WebPushChannel
                                                 └─> Job SendWebPush ──> Queue worker
```

## Service

| Service | Tanggung jawab |
|---|---|
| `AuthorizationService` | Peta capability per peran, scope sekolah/kelas, kewenangan menulis laporan |
| `AttendanceScopeService` | Membatasi kueri kehadiran sesuai peran sebelum filter apa pun |
| `AttendanceExportService` | Matriks kehadiran → CSV, Excel (OpenSpout), PDF |
| `MediaStorageService` | Menyimpan/menghapus berkas media lewat abstraksi Filesystem |
| `ReportReminderService` | Menilai kelengkapan laporan per sesi |
| `CustomNotificationService` | Notifikasi manual ke coach, dibatasi scope pengirim |
| `ScheduleTemplateService` | Membangun pola, generate sesi, deteksi bentrok, penomoran pertemuan |
| `TeachingScheduleImportService` | Impor jadwal dari Excel (dua format) |
| `ActivityLogService` | Satu-satunya jalur tulis `activity_logs`, dengan penyaringan metadata |

## Infrastruktur Runtime

PHP `^8.4`, Laravel `^12.0`, MySQL sebagai target produksi, Bootstrap 5.3 + Bootstrap Icons via CDN, OpenSpout (Excel), Dompdf (PDF), FastExcel (impor), Minishlink WebPush (push).

`config/queue.php` default `database`; `config/filesystems.php` menaruh media laporan pada disk privat `report_media`.

Nilai `.env` aktif berstatus `NEED VERIFICATION` bila `.env` tidak tersedia.

## Alur Request

Browser → middleware → controller → service (otorisasi & scope) → model → Blade. Controller tidak pernah mengambil keputusan scope sendiri; itu selalu tugas service.

## Alur Asinkron

Notifikasi keluar melalui dua kanal sekaligus:

- **`database`** — menulis baris `notifications`; ini yang membuat UI notifikasi terisi.
- **`WebPushChannel`** — memvalidasi lalu men-dispatch job `SendWebPush`; worker yang benar-benar mengirim ke perangkat.

Konsekuensinya aplikasi **butuh queue worker**. Lihat [operations/queue-worker.md](operations/queue-worker.md).

## Penyajian Media

Berkas media tidak berada di `public/` dan tidak ada symlink ke sana. Akses selalu lewat `GET /media/{media}` yang memeriksa peran dan kepemilikan laporan sebelum melakukan stream. Lihat [modules/media.md](modules/media.md).

## PWA

`public/manifest.json` + `public/sw.js`. Service worker bersifat **network-only untuk navigasi** — tidak ada HTML terautentikasi yang masuk Cache Storage. Lihat [modules/pwa.md](modules/pwa.md).

## Catatan Penamaan

Namespace controller `Admin` adalah warisan historis. `admin/*` adalah URL kompatibilitas, **bukan** peran. Otorisasi sebenarnya dilakukan lewat capability di `AuthorizationService`, bukan lewat nama namespace atau prefix URL.
