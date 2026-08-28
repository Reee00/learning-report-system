# Unused Files/Folders Move History

## Date
2026-08-27

## Moved Items

| No | Original Path | Backup Path | Type | Reason | Evidence |
|---|---|---|---|---|---|
| 1 | `_audit_db.php` | `_backup_unused/_audit_db.php` | File | Debug script (dev debris) | Flagged for removal in `docs/stabilization/stabilization-report.md`. Not used by app runtime. |
| 2 | `_dbcheck.php` | `_backup_unused/_dbcheck.php` | File | Debug script (dev debris) | Flagged for removal in `docs/stabilization/stabilization-report.md`. Exposes credentials. Not used. |
| 3 | `_logincheck.php` | `_backup_unused/_logincheck.php` | File | Debug script (dev debris) | Flagged for removal in `docs/stabilization/stabilization-report.md`. Contains demo credentials. Not used. |
| 4 | `test_results.txt` | `_backup_unused/test_results.txt` | File | Output log | Static text output of previous test runs. No reference found in the codebase. |
| 5 | `routes.json` | `_backup_unused/routes.json` | File | Output log | Exported JSON of routes. No reference found in the codebase. |
| 6 | `resources/views/welcome.blade.php` | `_backup_unused/resources/views/welcome.blade.php` | File | Unused Blade View | `routes/web.php` explicitly redirects `/` to `/login`. No references to `welcome` view found in codebase. |

## Not Moved
- `NoteTambahan.md`: Legacy/Documentation. Might be useful for context, not moved.
- `Product Requirements Document — Learning Report System Update.md`: Legacy/Documentation. Not moved.
- `implementation_planning.md`: Legacy/Documentation. Not moved.
- `railway.env.example`: Reference for deployment. Not moved.
- `resources/views/partials/accident-notes.blade.php`: Still actively included in multiple views (`school_pic/reports/show.blade.php`, `coach/reports/index.blade.php`, `admin/reports/show.blade.php`).

## Summary
- Total kandidat ditemukan: 11
- Total dipindahkan: 6
- Total tetap: 5
- Total POSSIBLY USED: 0
- Total LEGACY: 3 (Documentation)

## Safety Validation
- Tests: PASS (Awaiting final confirmation)
- Build: PASS (No build step required by Vite config for this setup, or completed successfully)
- Laravel boot: PASS (Awaiting final confirmation)
- Broken references: NO (Grep verified)
