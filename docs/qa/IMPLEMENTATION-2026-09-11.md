# Implementasi Meeting 2026-09-11 — Laporan Status

Date: 2026-09-11. Scope: fix Add Video bug, QA HIGH/MEDIUM fixes, meeting requirements A–G.

## 1. Add Video Bug — FIXED
Root cause: Herd PHP 8.4 `php.ini` limits (`upload_max_filesize=2M`, `post_max_size=8M`) vs form allowing 3 videos × 100 MB; nginx already allowed 128M+.
- `C:\Users\Nale\.config\herd\bin\php84\php.ini`: `upload_max_filesize=101M`, `post_max_size=310M`, `memory_limit=256M` (backup: `php.ini.bak-20260911`).
- `public/.user.ini`: mirrors limits for FPM deployments.
- `bootstrap/app.php`: `PostTooLargeException` rendered as friendly flash error (no blank 500).
- Coach create/edit views: client-side size/count validation (photos 10×10 MB, videos 3×100 MB, attendance 5×10 MB) + submit-time safety belt.
- Storage/metadata/auth/playback paths were already correct (`MediaStorageService`, `/media/{media}`); verified by existing MediaStorageTest suite.

## 2. QA Fixes
| ID | Fix |
|----|-----|
| H-001 | `.env` verified NOT git-tracked; added `.env.example` with placeholders. **Credential rotation is a user action** — local `.env` still holds real Cloudinary keys. |
| H-002 | `Admin\DashboardController` stats now scoped via `accessibleSchoolIds()`. |
| M-001 | PDF export requires full `attendance.export` capability (controller 403 + CSV-only button rendering). |
| M-002 | Seeder adds `teacher_school` account + School B. |
| M-003 | `AttendanceExportService` matrix built with `chunk(1000)`. |
| M-004 | `reports.photo_path` dropped (verified unused); removed from `$fillable`. |
| M-005 | Unique `(report_id, student_id)` on `report_attendances` after dedupe; MySQL-safe rollback. |
| M-006 | Report update re-validates coach class assignment (`assignedClassOrFail`). |

## 3. Meeting Requirements
- **A. Teacher School**: backend forces `status=approved` (request filter ignored); status filter dropdown + status column hidden in view.
- **B. Relation Dashboard**: relation gains `dashboard.view`; login lands on `admin.dashboard`; sidebar link enabled.
- **C. PIC Dashboard**: coach filter (only coaches teaching in plotted schools), stats, approved reports only; cross-school detail 403.
- **D. Reminders**: `ReportReminderService` — incomplete = past/current `teaching_schedules` session without matching submitted/approved report. Relation reminds all (`POST /admin/reports/remind`); PIC only own-school coaches (`POST /pic/remind`, cross-school silently refused with error flash). Laravel database notifications; coach banner + mark-read.
- **E. Teaching Schedule**: FastExcel import (columns: `tanggal, nama_sekolah, nama_kelas, email_coach, jam_mulai, jam_selesai, topik`), CSV template, scoped visibility (SuperAdmin/Relation global, PIC plotted, Coach own), `schedules.manage` guards import/delete.
- **F. Report Form**: `goals_materi` + `activity_report` replace `activity_summary` (backfilled); required, max 2000; updated create/edit forms with help texts, admin/PIC show views, download/print view.
- **G. Attendance Media**: `attendance_media[]` upload (max 5 × 10 MB images) stored via `MediaStorageService` under `reports/{year}/{id}/attendance/`; galleries on review/PIC views; listed in download view.

## 4. Tests
- `php artisan test`: **131 passed, 481 assertions** (17 new tests in `MeetingRequirementsTest` covering A–G incl. cross-school isolation, reminder scoping, field validation, attendance media caps).
- Local MySQL migrated: 6 new migrations applied (rollback + re-run verified).
- `php artisan db:seed` re-run (idempotent).

## 5. Remaining Risks
- Deployment targets other than local Herd must replicate PHP upload limits at the server PHP level (deployment is not Docker-based; the Dockerfile was removed 2026-09-11).
- Reminder logic requires schedule imports to be kept current.
- Cloudinary credential rotation (user action).
- QA LOW items intentionally not implemented.
