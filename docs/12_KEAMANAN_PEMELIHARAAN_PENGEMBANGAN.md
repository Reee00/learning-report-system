# Keamanan dan Pemeliharaan

- Gunakan middleware auth/permission dan `AuthorizationService` pada setiap endpoint.
- Enforce school/class/report scope di backend; jangan mengandalkan filter UI.
- Media baru disimpan di private local disk dan disajikan lewat controller yang memeriksa role, report, dan school scope.
- Database menyimpan path/reference dan metadata media, bukan binary.
- Jangan commit `.env`, credential, atau file media.
- Pertahankan foreign-key rules dan transaksi report/attendance/media saat mengubah domain.
- Cloudinary sudah dihapus penuh (2026-09-11); jalur upload adalah `MediaStorageService`.
- Jalankan test dan review migration sebelum deployment.
- Catat perubahan permission, role, status, dan scope dalam dokumentasi ini serta `contextproject.md`.

## Hardening Login (audit keamanan 2026-09-11)
- Rate limiting kunci `email|ip` — 5 percobaan gagal per kombinasi, decay progresif (`RateLimiter`). IP yang menyasar banyak akun dan akun yang diserang dari banyak IP keduanya ter-throttle.
- Pesan error generik identik untuk email salah maupun password salah — mencegah account enumeration.
- Session ID di-regenerate saat login sukses (anti session fixation); logout meng-invalidate session dan me-regenerate CSRF token.
- Konfigurasi session: http_only, same_site=lax, secure via `SESSION_SECURE_COOKIE`.

## Activity Log (audit keamanan 2026-09-11)
- Tabel `activity_logs`; satu-satunya jalur tulis adalah `ActivityLogService::log()` — metadata otomatis disaring (password/token/secret dibuang, tidak pernah tersimpan).
- Yang dicatat: login/logout/login-gagal, user CRUD + reset password, master data (sekolah/kelas/program/siswa) CUD + import, laporan (submit/resubmit/approve/reject), reminder & notifikasi custom, jadwal CRUD + import, assignment coach. Setiap baris memuat actor, role, action, subject, deskripsi, IP, user agent, metadata.
- Konsol hanya untuk SuperAdmin (`/admin/activity-logs`): filter user/role/action/tanggal/kata kunci + detail view.
- Retensi 7 hari: `php artisan activity-logs:purge` terjadwal harian 02:17 (`routes/console.php`).

## Notifikasi Custom (audit UX 2026-09-11)
- Capability `notifications.send`: Relation (target semua coach), PIC School (hanya coach yang mengajar — utama maupun tambahan — di sekolah plot-nya). Target di luar scope DITOLAK server-side (`CustomNotificationService`), bukan sekadar disembunyikan di UI.
- Notifikasi tersimpan lewat database channel Laravel; coach melihat banner belum-dibaca dan menandai dibaca lewat route yang memeriksa kepemilikan.

## Pola Jadwal per Hari + Sekolah (refactor 2026-09-25)
- Pola berulang (`teaching_schedule_templates`) dipisah dari sesi aktual (`teaching_schedules` + kolom `template_id`/`meeting_number`). Sejak 2026-09-25 identitas pola adalah **(hari + sekolah + tanggal mulai)**: tiap sekolah punya `start_date` dan `meeting_count` sendiri, tidak ada periode global. Reminder, visibility, multi-coach, dan filter tetap membaca tabel sesi — sesi hasil generate dikenali otomatis.
- Semua route pola memakai `permission:schedules.manage` (kecuali detail pola `schedules.pattern.show` yang cukup `schedules.view`); scope sekolah PIC dipaksa di service (`build()` memvalidasi "sekolah di luar scope Anda", `assertPatternScope()` → 403 untuk generate/hapus pola, `assertSchoolInScope()` untuk aksi per baris). Semua error validasi dikumpulkan sekali — tidak ada yang disimpan bila ada error (atomik).
- **Rute statis didaftarkan sebelum rute ber-parameter**: `DELETE schedules/pattern` dan `GET schedules/pattern` harus mendahului `DELETE schedules/{schedule}` / `GET schedules/{schedule}/edit`, jika tidak model binding akan menangkap kata "pattern" sebagai id dan mengembalikan 404.
- Import workbook perusahaan membaca tanggal mulai PER BLOK SEKOLAH dari kolom KET (bukan satu `week_start` global). Blok tanpa tanggal mulai valid, atau yang tanggalnya tidak jatuh pada hari sheet-nya, DILEWATI dengan pesan yang menyebut sekolah + sheet — sistem tidak pernah mengarang tanggal. `meetings` (default 20) menentukan jumlah pertemuan. Workbook perusahaan sendiri tetap READ-ONLY.
- §4: "Jalan Minggu Ini?" adalah status operasional, bukan definisi pola. Mengubahnya tidak menyentuh `start_date`/`meeting_count`, dan generate ulang tidak pernah menambah sesi melebihi `meeting_count`, sehingga pola berulang tidak bisa rusak karena perubahan status mingguan.
- Libur/penyesuaian tanggal = hapus/pindah sesi individual di modul jadwal; "Generate" hanya menambah pertemuan yang kurang dan `renumberSessions()` menjaga nomor pertemuan tetap 1..N. Menghapus pola atau baris pola TIDAK menghapus sesi yang sudah dibuat (`template_id` → null lewat `nullOnDelete`).
- Activity log: `schedule.pattern_created`, `schedule.pattern_generated`, `schedule.pattern_deleted`, `schedule.template_generated`, `schedule.template_deleted`.
