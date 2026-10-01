# Keamanan dan Pemeliharaan

Disinkronkan dengan kode: **2026-09-28**.

## Aturan Dasar

- Gunakan middleware `auth` plus `permission`/`permission_any` di setiap endpoint, dan `AuthorizationService` untuk keputusan scope.
- **Enforce scope sekolah/kelas/laporan di backend.** Jangan pernah mengandalkan filter atau tombol yang disembunyikan di UI.
- Media disimpan di disk privat di luar `public/` dan disajikan lewat controller yang memeriksa peran, laporan, dan scope sekolah.
- Database menyimpan path/reference dan metadata media, **bukan** binernya.
- Jangan commit `.env`, credential, atau berkas media.
- Pertahankan foreign-key rules dan transaksi pada domain laporan/kehadiran/media saat mengubah schema.
- Jalankan test dan review migrasi sebelum deployment.
- Catat setiap perubahan capability, peran, status, atau scope pada [reference/permissions.md](reference/permissions.md) dan `contextproject.md`.

## Rahasia dan Kredensial

- `VAPID_PRIVATE_KEY` **hanya** dibaca dari `.env`. Tidak pernah dikirim ke klien, tidak masuk manifest, service worker, atau payload push. Hanya `VAPID_PUBLIC_KEY` yang boleh sampai ke browser.
- Endpoint langganan push **tidak pernah** ditulis ke log. Saat pengiriman gagal, yang dicatat adalah `push_subscription_id`, bukan endpoint.
- Metadata activity log disaring otomatis: password, token, dan secret dibuang sebelum disimpan.
- Jangan mengisi nilai asli pada `.env.example` atau berkas yang di-commit.

> **Catatan audit 2026-09-28 (diverifikasi terhadap riwayat git).** Dua berkas yang **terlacak git** pernah memuat kredensial Cloudinary dalam bentuk teks biasa: `docs/qa/HIGH-PRIORITY.md` dan `docs/qa/QA-AUDIT-REPORT.md`. `git log --all -S"<nilai>"` mengonfirmasi permukaan itu terjadi pada commit `d365130` dan **hanya** pada kedua berkas tersebut.
>
> Koreksi terhadap catatan audit sebelumnya: **`README.md` tidak pernah memuat nilai asli** — blok Cloudinary di sana selalu berisi placeholder (`your_cloud_name`, dst.). Demikian pula `.env` **tidak pernah terlacak git** (`.gitignore` memuatnya; `git ls-files .env` tidak menemukan apa pun).
>
> Pada sinkronisasi ini nilai di kedua berkas QA sudah di-redaksi menjadi `<redacted>`. **Status akhir: CLOSED (2026-09-28).** Kredensial Cloudinary lama **sudah dihapus/diinvalidasi di sisi penyedia**, sehingga salinan yang tersisa di riwayat git tidak lagi dapat dipakai. Ini menutup paparan; tidak ada tindakan lanjutan.
>
> **Cleanup Cloudinary 2026-09-28.** Seluruh sisa jejak Cloudinary di kode sudah dihapus, dan blok `CLOUDINARY_*` di `.env.example` ikut dihapus karena tidak ada kode yang membacanya. Tiga baris `CLOUDINARY_*` di `.env` lokal **sengaja tidak disentuh** oleh perapian dokumentasi — kini config mati yang aman dihapus kapan saja, dan penghapusannya diserahkan ke pemilik sistem. Dengan kredensial yang sudah diinvalidasi, keberadaannya tidak lagi menimbulkan risiko.

## Hardening Login (audit keamanan 2026-09-11)

- Rate limiting berkunci `email|ip` — 5 percobaan gagal per kombinasi, decay progresif. IP yang menyasar banyak akun dan akun yang diserang dari banyak IP keduanya ter-throttle.
- Pesan galat generik yang identik untuk email salah maupun password salah — mencegah account enumeration.
- Session ID di-regenerate saat login sukses (anti session fixation); logout meng-invalidate session dan me-regenerate CSRF token.
- Konfigurasi session: `http_only`, `same_site=lax`, `secure` lewat `SESSION_SECURE_COOKIE`.

## Activity Log (audit keamanan 2026-09-11)

- Tabel `activity_logs`; satu-satunya jalur tulis adalah `ActivityLogService::log()` — metadata otomatis disaring (password/token/secret dibuang, tidak pernah tersimpan).
- Yang dicatat: login/logout/login-gagal, CRUD pengguna + reset password, master data (sekolah/kelas/program/murid) CUD + impor, laporan (submit/resubmit/approve/reject), reminder & notifikasi, jadwal CRUD + impor + pola, dan penugasan coach. Setiap baris memuat actor, role, action, subject, deskripsi, IP, user agent, dan metadata.
- Konsol hanya untuk SuperAdmin (`/admin/activity-logs`): filter pengguna/role/action/tanggal/kata kunci + tampilan detail.
- Retensi 7 hari: `php artisan activity-logs:purge`, terjadwal harian **02:17** di `routes/console.php`.

## Notifikasi Manual (audit UX 2026-09-11)

- Capability `notifications.send`: Relation (semua coach), PIC (hanya coach yang mengajar di sekolah plot-nya).
- Target di luar scope **ditolak server-side** oleh `CustomNotificationService` dan `assertSchoolInScope()`, bukan sekadar disembunyikan di UI.
- Rujukan `schedule_id`/`report_id` juga divalidasi berada dalam scope pengirim.
- Notifikasi tersimpan lewat kanal `database` Laravel; penandaan dibaca memakai route yang memeriksa kepemilikan (`findOrFail` pada relasi notifikasi pengguna), sehingga ID milik orang lain berakhir 404.

## Pola Jadwal per Hari + Sekolah (refactor 2026-09-25)

- Pola berulang (`teaching_schedule_templates`) dipisah dari sesi aktual (`teaching_schedules` dengan `template_id`/`meeting_number`). Identitas pola adalah **(hari + sekolah + tanggal mulai)**; tiap sekolah punya `start_date` dan `meeting_count` sendiri, tanpa periode global.
- Semua route pola memakai `permission:schedules.manage`, kecuali detail pola (`schedules.pattern.show`) yang cukup `schedules.view`. Scope sekolah dipaksa di service: `build()` menolak "sekolah di luar scope Anda", `assertPatternScope()` → 403 untuk generate/hapus pola, `assertSchoolInScope()` untuk aksi per baris.
- Validasi dikumpulkan sekali — **tidak ada yang disimpan bila ada satu error** (atomik).
- **Rute statis didaftarkan sebelum rute ber-parameter.** `GET`/`DELETE schedules/pattern` harus mendahului `DELETE schedules/{schedule}` dan `GET schedules/{schedule}/edit`, jika tidak model binding akan menangkap kata "pattern" sebagai id dan mengembalikan 404.
- Impor workbook perusahaan membaca tanggal mulai **per blok sekolah** dari kolom KET. Blok tanpa tanggal mulai valid, atau yang tanggalnya tidak jatuh pada hari sheet-nya, **dilewati** dengan pesan yang menyebut sekolah + sheet — sistem tidak pernah mengarang tanggal. Workbook sumber tetap read-only.
- "Jalan Minggu Ini?" adalah status operasional, bukan definisi pola: mengubahnya tidak menyentuh `start_date`/`meeting_count`, dan generate ulang tidak pernah menambah sesi melebihi `meeting_count`.
- Libur/penyesuaian tanggal dilakukan dengan menghapus/memindahkan sesi individual; "Generate" hanya menambah pertemuan yang kurang dan `renumberSessions()` menjaga nomor tetap 1..N. Menghapus pola atau baris pola **tidak** menghapus sesi yang sudah dibuat (`template_id` → `null` lewat `nullOnDelete`).
- Activity log: `schedule.pattern_created`, `schedule.pattern_generated`, `schedule.pattern_deleted`, `schedule.template_generated`, `schedule.template_deleted`.

## Satu Sesi = Satu Laporan (2026-09-28)

- `reports.teaching_schedule_id` **nullable + UNIQUE** + `nullOnDelete`. Validasi aplikasi menutup kasus normal; indeks unik menutup celah balapan dua permintaan bersamaan.
- Backfill hanya menautkan laporan yang kandidat sesinya tunggal dan belum diklaim. **Tidak ada baris yang dihapus atau digabung.**
- Kolom nullable membuat laporan lama tetap sah.

## PWA dan Web Push

- Service worker bersifat **network-only untuk navigasi** dan passthrough untuk path terproteksi. **Tidak ada halaman terautentikasi yang masuk Cache Storage** — penting pada perangkat bersama. Handler `push` juga tidak menulis apa pun ke Cache Storage.
- Cache dibersihkan saat logout lewat pesan `LRS_CLEAR_CACHES`.
- Payload push dibatasi pada `title`, `body`, `icon`, `badge`, `type`, `notification_id`, `url` — tidak ada isi laporan atau data murid.
- `safeTargetUrl()`/`safeIconUrl()` memvalidasi URL dari payload sebelum dipakai.
- Kanal push tidak pernah melempar exception ke pemanggil dan tidak pernah membuat baris notifikasi kedua.
- Perubahan `public/sw.js` yang memengaruhi cache **wajib** menaikkan `SW_VERSION`.

## Queue

`QUEUE_CONNECTION` default `database`. Tanpa worker, notifikasi dan seluruh Web Push tidak terkirim. Pastikan `--timeout` worker lebih kecil dari `retry_after` (default 90 detik) agar satu job tidak dijalankan dua kali. Lihat [operations/queue-worker.md](operations/queue-worker.md).

## Pemeliharaan Rutin

| Frekuensi | Tindakan |
|---|---|
| Harian | Scheduler menjalankan `activity-logs:purge` (02:17) |
| Harian | Pantau `php artisan queue:failed` — job gagal yang menumpuk berarti ada masalah nyata |
| Berkala | Periksa pertumbuhan `push_subscriptions`; penumpukan berarti worker sempat mati atau endpoint kedaluwarsa tidak terbersihkan |
| Berkala | Pantau ukuran `storage/app/report-media` |
| Setiap deploy | `php artisan queue:restart` agar worker memuat kode baru |
| Setiap perubahan aturan | Perbarui dokumen terkait dan `contextproject.md` |
