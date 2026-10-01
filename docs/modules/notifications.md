# Modul: Notifikasi & Reminder

Sinkron dengan kode: **2026-09-28**. Sumber: `app/Notifications/CustomNotification.php`, `app/Notifications/ReportReminderNotification.php`, `app/Notifications/Channels/WebPushChannel.php`, `app/Services/CustomNotificationService.php`, `app/Services/ReportReminderService.php`, `app/Http/Controllers/Admin/NotificationController.php`, `app/Http/Controllers/Coach/NotificationController.php`.

## Dua kelas notifikasi

| Kelas | Antrean | Dipakai untuk |
|---|---|---|
| `CustomNotification` | **Ya** — `implements ShouldQueue` | Notifikasi manual admin/PIC, dan pemberitahuan approve/reject |
| `ReportReminderNotification` | **Tidak** — `extends Notification { use Queueable; }` | Pengingat laporan belum dibuat |

Perbedaan ini disengaja dan penting saat deployment:

- `CustomNotification` diantrekan. Pengirimannya dikerjakan queue worker, sehingga **wajib ada queue worker yang berjalan** (lihat [queue-worker.md](../operations/queue-worker.md)).
- `ReportReminderNotification` **tidak** diantrekan, sehingga barisnya di tabel `notifications` ditulis langsung pada request — halaman notifikasi penerima tetap terisi meski worker mati. Push-nya tetap lewat job, karena `WebPushChannel` men-dispatch `SendWebPush`, jadi push tetap butuh worker.

Keduanya memakai kanal `['database', WebPushChannel::class]`.

## Kanal `database`

Menulis satu baris di tabel `notifications` (UUID) berisi `title`, `body`/`message`, `type`, `url`. Baris inilah yang dirender di UI notifikasi, dan `notification_id` pada payload push berasal dari sini sehingga notifikasi yang diklik dapat dikaitkan kembali ke baris database.

**Tidak ada halaman indeks notifikasi.** Notifikasi ditampilkan lewat komponen di layout (`resources/views/layouts/app.blade.php`), bukan lewat rute `/notifications`. Satu-satunya aksi tulis adalah menandai satu notifikasi sebagai sudah dibaca:

```
POST coach/notifications/{id}/read  →  coach.notifications.read   (middleware: role:coach)
```

`Coach\NotificationController::read()` memakai `$request->user()->notifications()->findOrFail($id)`, sehingga ID notifikasi milik pengguna lain berakhir 404 — coach tidak dapat menandai notifikasi orang lain.

## Kanal `WebPushChannel`

Lima prinsip yang dipegang kanal ini:

1. **Tidak pernah membuat baris notifikasi kedua.** Kanal push hanya mengirim; pencatatan tetap milik kanal `database`.
2. **Tidak pernah melempar exception ke pemanggil.** Kegagalan push tidak boleh menggagalkan aksi bisnis (approve, reminder, dan sebagainya).
3. **No-op bila VAPID belum dikonfigurasi.** Kunci kosong = push dimatikan sepenuhnya, tanpa error.
4. **Hanya menghapus subscription yang dilaporkan kedaluwarsa** (HTTP 404/410), dan hanya milik notifiable yang bersangkutan.
5. **Payload dibatasi** pada `title`, `body`, `icon`, `badge`, ditambah `type`, `notification_id`, dan `url`. Tidak ada data sensitif lain.

Kanal men-dispatch `SendWebPush::dispatch($notifiable->getMorphClass(), $notifiable->getKey(), $notification)` — objek notifikasi dikirim utuh supaya `$notification->id` (UUID baris database) tetap identik di sisi worker.

Detail: [web-push.md](web-push.md).

## Notifikasi manual (`admin.notifications.*`)

Dua rute, keduanya capability `notifications.send`:

| Method | URI | Name |
|---|---|---|
| GET | `admin/notifications/create` | `admin.notifications.create` |
| POST | `admin/notifications` | `admin.notifications.store` |

Tiga jenis notifikasi (`NotificationController::TYPES`):

| Nilai `type` | Label |
|---|---|
| `schedule` | Perubahan Jadwal |
| `reminder` | Reminder Laporan |
| `operational` | Warning / Info Operasional |

Validasi `store()`: `type` (wajib, salah satu dari tiga), `coach_ids` (wajib array, minimal 1, harus ada di `users`), `title` (maks 150), `message` (maks 1000), serta `schedule_id` / `report_id` opsional sebagai rujukan.

Aturan scope: bila `schedule_id` atau `report_id` diisi, `assertSchoolInScope()` memastikan record terkait berada dalam scope pengirim — jika tidak, permintaan ditolak 403 (`Record terkait berada di luar scope Anda.`). `schedule_id` menghasilkan tautan ke daftar jadwal; `report_id` menghasilkan tautan ke detail laporan.

Setiap pengiriman mencatat activity log `notification.sent` berisi jenis, jumlah, dan daftar id coach penerima, lalu mengembalikan pesan sukses berisi jumlah coach yang berhasil dikirimi.

## `CustomNotificationService`

| Metode | Fungsi |
|---|---|
| `targetableCoaches(User $sender)` | Daftar coach yang boleh dikirimi pengirim, sudah dibatasi scope sekolahnya |
| `send(User $sender, $coachIds, string $title, string $message, string $type, ?string $actionUrl)` | Mengirim dan mengembalikan **jumlah** notifikasi terkirim |
| `schoolScope(User $sender)` | Scope sekolah pengirim (`null` = global) |

Penerima disaring ulang terhadap scope pengirim, sehingga pengirim non-global tidak dapat menargetkan coach di luar sekolahnya meski id-nya dikirim dari klien.

## `ReportReminderService`

Menentukan siapa yang belum membuat laporan, dinilai **per sesi** (lihat [reports.md](reports.md)):

- Sesi dianggap selesai begitu **ada** satu laporan untuk sesi itu — siapa pun pembuatnya.
- Rujukan utama `reports.teaching_schedule_id`; kecocokan lama (kelas + tanggal) hanya cadangan untuk laporan yang belum punya tautan sesi.
- Hanya sesi **aktif** dengan `session_date <= hari ini` yang dihitung.
- Dipicu manusia dari UI: `POST admin/reports/remind` (capability `reports.remind`) dan `POST pic/remind`.
- PIC hanya dapat mengirim ke coach di sekolah plot-nya.

**Tidak ada reminder terjadwal otomatis.** Satu-satunya tugas pada `routes/console.php` adalah `activity-logs:purge` (harian 02:17). Pengingat laporan selalu berangkat dari aksi pengguna.

## Ringkasan route

| Method | URI | Name | Middleware |
|---|---|---|---|
| GET | `admin/notifications/create` | `admin.notifications.create` | `permission:notifications.send` |
| POST | `admin/notifications` | `admin.notifications.store` | `permission:notifications.send` |
| POST | `coach/notifications/{id}/read` | `coach.notifications.read` | `role:coach` |
| POST | `admin/reports/remind` | `admin.reports.remind` | `permission:reports.remind` |
| POST | `pic/remind` | `pic.remind` | `role:school_pic` + `permission:attendance.view` |
| POST | `push-subscriptions` | `push-subscriptions.store` | `auth` |
| DELETE | `push-subscriptions` | `push-subscriptions.destroy` | `auth` |

## Activity log

Aksi tercatat: `notification.sent`, `report.reminder_sent`.

## Test terkait

`CustomNotificationTest`, `WebPushTest`. Perilaku pengingat per sesi diuji lewat `SessionReportOwnershipTest`, `ScheduleSessionActiveTest`, dan `SemesterScheduleTest`; tidak ada berkas test tersendiri untuk `ReportReminderService`.
