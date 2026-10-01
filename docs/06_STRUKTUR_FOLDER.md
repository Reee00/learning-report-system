# Struktur Folder

Disinkronkan dengan kode: **2026-09-28**.

## Aplikasi

| Path | Isi |
|---|---|
| `app/Models/` | 14 model: `ActivityLog`, `CoachClass`, `Program`, `ProgramClass`, `PushSubscription`, `Report`, `ReportAttendance`, `ReportMedia`, `School`, `SchoolClass`, `Student`, `TeachingSchedule`, `TeachingScheduleTemplate`, `User` |
| `app/Http/Controllers/Admin/` | 11 controller: `ActivityLog`, `Class`, `Coach`, `Dashboard`, `Notification`, `Program`, `Report`, `Schedule`, `ScheduleTemplate`, `School`, `User` |
| `app/Http/Controllers/Coach/` | `Notification`, `Report`, `Student` |
| `app/Http/Controllers/SchoolPic/` | `Dashboard` |
| `app/Http/Controllers/` | `AttendanceController`, `MediaController`, `PushSubscriptionController`, `StudentController`, `Controller`, plus `Auth/` |
| `app/Http/Middleware/` | `RoleMiddleware`, `PermissionMiddleware`, `PermissionAnyMiddleware` |
| `app/Services/` | 9 service: `ActivityLogService`, `AttendanceExportService`, `AttendanceScopeService`, `AuthorizationService`, `CustomNotificationService`, `MediaStorageService`, `ReportReminderService`, `ScheduleTemplateService`, `TeachingScheduleImportService` |
| `app/Notifications/` | `CustomNotification`, `ReportReminderNotification`, dan `Channels/WebPushChannel` |
| `app/Jobs/` | `SendWebPush` |
| `app/Providers/` | `AppServiceProvider`, `WebPushServiceProvider` |
| `app/Console/Commands/` | `PurgeActivityLogs` (satu-satunya perintah kustom) |

> **Catatan:** perintah `media:migrate-cloudinary` **sudah tidak ada**. Integrasi Cloudinary dihapus penuh dari kode.

## Database

| Path | Isi |
|---|---|
| `database/migrations/` | **28 berkas** |
| `database/seeders/` | `DatabaseSeeder` — lihat [development/seeder.md](development/seeder.md) |

## Tampilan dan Aset

| Path | Isi |
|---|---|
| `resources/views/` | Blade: layout/sidebar, `admin/`, `coach/`, `pic/`, `attendance/`, `students/`, `reports/`, `notifications/`, `partials/`, `vendor/pagination/` |
| `resources/views/partials/` | `pwa-head`, `pwa-scripts`, `push-notifications`, `push-notifications-scripts`, `whatsapp-link`, `password-toggle-scripts` |
| `resources/css/`, `resources/js/` | Sumber aset. Layout utama memakai Bootstrap CDN dan **tidak** memakai `@vite`; `npm run build` adalah no-op |
| `public/` | `manifest.json`, `sw.js`, `offline.html`, `icons/` |

## Route dan Konfigurasi

| Path | Isi |
|---|---|
| `routes/web.php` | Seluruh route aplikasi (89) |
| `routes/console.php` | Penjadwalan; hanya `activity-logs:purge` |
| `bootstrap/app.php` | Alias middleware, `trustProxies`, renderer `PostTooLargeException` |
| `bootstrap/providers.php` | `AppServiceProvider`, `WebPushServiceProvider` |
| `config/webpush.php` | Konfigurasi VAPID dan opsi klien |
| `config/filesystems.php` | Disk `report_media` + pemilih `report_media_disk` |
| `config/queue.php` | Default `QUEUE_CONNECTION=database` |

## Test

| Path | Isi |
|---|---|
| `tests/Feature/` | 36 berkas — otorisasi, scope, laporan, jadwal, master data, media, PWA, Web Push, end-to-end |
| `tests/Unit/` | `AuthorizationServiceTest`, `ExampleTest` |
| `tests/JavaScript/` | `service-worker-routing.test.js` — 37 pemeriksaan service worker |
| `tests/Support/` | `FakeWebPush`, `ImmediatePushProbe` |

## Penamaan

Namespace `Admin` dan prefix URL `admin/` dipertahankan untuk kompatibilitas, **bukan** peran operasional. Tidak ada peran `admin` saat runtime.
