# Stabilization Report

**Audit date:** 2026-08-17
**Scope:** Phase 0–12 regression & stabilization audit. Phase 13 (WaHa) intentionally not started.
**Environment:** Laravel 12.66.0 / PHP 8.4.24 / Composer 2.10.1 / SQLite / Laravel Herd (Windows)

---

## Overall Status

**PASS WITH WARNINGS**

Every P0 and P1 defect found during the audit is **fixed, tested and green**. Both blockers that held the
verdict at NOT READY have since been cleared or de-escalated:

1. **Two damaged reports (DATA-001) — cleared.** `reports` 4 and 5 (`submitted`, zero attendance,
   produced by BUG-012 before it was fixed) are no longer in the database. Integrity check 16 now returns
   **0**, and the removal left no orphaned attendance rows. The rows were deleted rather than repaired via
   the recommended reject → re-enter → resubmit path, so the report text and accident notes in them were
   lost and are not recoverable from either backup in the repo (both predate their creation). Recorded in
   DATA-001; the recurrence is prevented in code and covered by 5 tests.
2. **Cloudinary credentials (ISSUE-014) — still open, downgraded to a single wrong value.** The keys are
   no longer blank, but `CLOUDINARY_CLOUD_NAME` holds a MediaFlows identifier rather than a Programmable
   Media cloud name, so Cloudinary answers `401 cloud_name mismatch`. Report **media attachment remains
   non-functional**; reports without media submit normally. This is a P2 environment item, not a code
   defect, and it fails closed — a rejected upload now produces a validation error and writes nothing.

Both §48 and §49 gates pass. The system is cleared for Phase 13 with ISSUE-014 tracked as an open
environment item — see the caveat under **WaHa Readiness** if Phase 13 will send report photos.

The remaining warnings are open *requirement* questions (documented, deliberately not implemented) and
P3 cosmetic items — none of those block Phase 13.

---

## Re-verification (2026-08-17, post-audit)

Run after the owner acted on the blockers. Nothing in the application code changed between the audit and
this pass; only the database contents and `.env` differ.

```text
php artisan test                62 passed (301 assertions), 0 failures, 0 errors
php artisan migrate:status      13 migrations, all Ran, 0 Pending
php artisan route:list          Showing [44] routes
php artisan about               Laravel 12.66.0 / PHP 8.4.24 / env local / sqlite / queue sync / debug ENABLED
npm run build                   'No build step required'
integrity checks 01–16          0 of 16 failing
Cloudinary ping                 HTTP 401 cloud_name mismatch   <<< ISSUE-014 still open
```

Live-data checks (read-only, against `database/database.sqlite` rather than `:memory:`), added because the
data shape changed since the audit — a new coach and two new assignments, one of which spans two schools:

| Check | Result |
|---|---|
| Capability matrix for all 8 live accounts | PASS — only SuperAdmin holds `reports.review`; `coach` holds no `attendance.*`; Finance holds `attendance.export_csv` but not `attendance.export` |
| PIC / Finance see only plotted school rows | PASS — both scoped to school 1, zero rows from school 2 |
| PIC / Finance forcing `?school_id=2` | PASS — 0 rows; the scope cannot be widened |
| Attendance export + Finance CSV on real rows | PASS — 18 rows each, header exact, no school-2 row |
| Accident notes intact, incl. multi-line | PASS — report 3 renders `WIFI LEMOT\nANAK BERANTEM` |
| Multi-school coach (coach 8: school 1 + school 2) | PASS — scoped by `coach_id`, not by school; sees only own reports |
| Coach's own report list | PASS — coach 3 sees reports 1–3 with all 18 attendance rows |

One apparent anomaly was chased down and dismissed: `accessibleSchoolIds()` returns `[]` for a `coach`,
which would yield zero attendance rows. It is unreachable — the `coach` role holds neither
`attendance.view` nor `attendance.export`, so `AttendanceController` aborts before the scope service is
consulted, and a coach's own reports come from `Coach\ReportController@index`
(`where('coach_id', Auth::id())`). The `[]` also fails **closed**, which is the safe direction.


---

## Phase Status

| Phase | Status |
|---|---|
| Admin → Relation | PASS |
| Authorization | PASS (2 P0 fixed) |
| School | PASS (1 P1 fixed) |
| Student | PASS |
| Program Kelas | PASS (1 P1 fixed) |
| Coach | PASS (1 P1 fixed) |
| Assignment | PASS (1 P0 fixed) |
| Program | PASS WITH WARNINGS (no update/delete route — by design per NoteTambahan) |
| PIC | PASS |
| Attendance | PASS (1 P0 + 1 P1 fixed; the 2 rows damaged by BUG-012 are gone — check 16 = 0, DATA-001 closed) |
| Export | PASS |
| Finance CSV | PASS WITH WARNINGS (PRD Q6 scope still open) |
| Accident Notes | PASS |

Report media attachment is code-correct but **non-functional in this environment** until a valid
Cloudinary cloud name is supplied (ISSUE-014). Reports without media submit normally.

---

## Critical Findings

Five P0 defects were found. Every one of them was reproducible, is now fixed, and has a dedicated
regression test.

1. **BUG-001 — Development database was never migrated.**
   `school_user`, `programs`, `program_classes` did not exist in `database/database.sqlite`, so every
   PIC-plotting and Program screen threw `SQLSTATE[HY000]: General error: 1 no such table: school_user`.
   Confirmed in `storage/logs/laravel.log` (3 occurrences).

2. **BUG-002 — Cross-role report leakage (security breach).**
   The global report review console `/admin/reports` and `/admin/reports/{report}` was gated on
   `permission:reports.view`. `reports.view` is a **Coach** capability (for their *own* reports), so any
   Coach could read every other coach's report — for every school. Returned 200 instead of 403.

3. **BUG-003 — Coach could report on a class they are not assigned to.**
   `Coach\ReportController@store` resolved the class with `SchoolClass::findOrFail($validated['class_id'])`.
   The class id came straight from the form, and assignment was never verified server-side.

4. **BUG-004 — Coach could write attendance rows for students of another class.**
   Attendance is posted as `attendance[student_id] = status`, so the **array keys are untrusted input**.
   Neither `store()` nor `update()` verified that the submitted student ids belong to the reported class.

5. **BUG-005 — `spv_coach` was locked into a login redirect loop.**
   `redirectByRole()` ended in `default => redirect('/')`; `/` redirects to `login`, which redirects back
   to `/`. The account could authenticate but could never reach a page.

A sixth finding, **P1 and the only one that had already damaged stored data**, was found last — on the
live database, not by a test:

6. **BUG-012 — A report could be stored without its attendance.**
   `store()` wrote report → media → attendance with **no transaction**, and read `$result['secure_url']`
   without checking the Cloudinary response. A failed upload raised
   `ErrorException: Undefined array key "secure_url"` *after* the report row had committed and *before*
   the attendance loop ran, leaving a `submitted` report with zero attendance — invisible in every
   attendance list and CSV export, yet sitting in the review queue as if complete. `update()` had the
   same shape plus an unpaired `attendances()->delete()`. Two live reports were damaged this way; the
   two matching `local.ERROR` entries in the log are 4 and 7 seconds after their creation timestamps.

Full reproduction, root cause and fix for each: [bug-list.md](bug-list.md).

---

## Fixed Issues

| ID | Sev | Module | One-line fix |
|---|---|---|---|
| BUG-001 | P0 | Database | Ran the 4 pending Phase migrations (`migrate --force`) after backing the DB up. |
| BUG-002 | P0 | Authorization / Reports | Console re-gated on `permission:reports.review`; nav guard aligned; object-level school check + pre-filter added in `Admin\ReportController`. |
| BUG-003 | P0 | Coach Assignment | `assignedClassOrFail()` — assignment is resolved from the DB, not the form. |
| BUG-004 | P0 | Attendance | `assertAttendanceBelongsToClass()` in both `store()` and `update()`, before any write. |
| BUG-005 | P0 | Authentication | All six roles mapped explicitly; unmapped role now `abort(403)` instead of looping. |
| BUG-006 | P1 | User Management | Role metadata centralized in `User::roleLabels()` / `roleBadgeColors()` / `schoolScopedRoles()`; validation uses `Rule::in(User::roleKeys())`. |
| BUG-007 | P1 | Master Data | `RESTRICT` FK 500s replaced with readable refusals on School / Class / User delete. |
| BUG-008 | P1 | Seeder | Rewritten idempotent; now covers all six canonical roles incl. pivot plotting. |
| BUG-012 | P1 | Report write / Attendance | `store()` and `update()` wrapped in `DB::transaction()`; Cloudinary response validated into a readable `ValidationException`; remote deletes moved after commit. |
| BUG-009 | P2 | User Management | Finance plotting column now rendered (`isSchoolScoped()`); header renamed `Sekolah (Scope)`. |
| BUG-013 | P2 | Report edit | Media-cap failures now use the validation bag the view actually renders, and roll the status change back. |
| BUG-010 | P3 | Layout | Navbar role badge now reads `User::roleLabel()` — was `ucfirst()`, rendering "Spv coach". |

---

## Remaining Issues

**Nothing P0/P1 is open.** What remains is one environment item, a set of **requirement questions**
(rule 8: do not assume unclear requirements), and cosmetics.

### Closed since the audit

| # | Item | Outcome |
|---|---|---|
| DATA-001 | `reports` 4 and 5 were `submitted` with zero attendance (damage from BUG-012). | **CLOSED.** Rows no longer exist; integrity check 16 = 0; no orphans left behind. Cleared by deletion rather than the recommended reject → re-enter → resubmit, so the report text and accident notes were lost — both repo backups predate those rows. Recurrence prevented in code + 5 tests. |

### Open — not blocking Phase 13

| # | Item | Action |
|---|---|---|
| ISSUE-014 | `CLOUDINARY_CLOUD_NAME` holds a MediaFlows identifier, not a Programmable Media cloud name. Cloudinary answers `401 cloud_name mismatch`, so every report submitted with media is refused (cleanly, with no write). | Cloudinary Console → Programmable Media → Dashboard → Product Environment Credentials → copy the **Cloud name**, and take the API key/secret from that same environment. Then `php artisan config:clear` and re-ping. No credential was invented (rules 8, 14); a cloud name cannot be derived from a key pair. |

### Open requirement questions — NOT implemented on purpose

| # | Question | Current behaviour | Source of ambiguity |
|---|---|---|---|
| Q-A | May Relation **update/delete** schools? | Denied (`schools.update`/`schools.delete` absent) | PRD says "School Management ✓" without CRUD verbs; `docs/audit/role-permission-map.md` marks it TBD; an existing test encodes the denial. |
| Q-B | **PRD Q6 — Finance scope:** all schools or only plotted schools? | Plotted schools only (`accessibleSchoolIds()`) | PRD Q6 left open. |
| Q-C | What is `spv_coach`'s attendance scope? | Global — any class holding at least one coach assignment (`whereHas('schoolClass.coachAssignments')`) | Audit doc marks the G/S policy scope TBD. |
| Q-D | May Coach input Program? | No | Audit doc TBD. |

### P3 / cosmetic — logged, not changed

| # | Item | Why untouched |
|---|---|---|
| P3-1 | `spv_coach` holds `dashboard.view` and can open `/admin/dashboard`, but the navbar renders no Dashboard link for that role (login lands on `admin.coaches.index`). | Adding the link is new UI surface; needs a product decision (rule 2/8). Not a leak: `dashboard.view` is held only by `spv_coach` + SuperAdmin, both operational-global, so the global counters are inside their scope. |
| P3-2 | `programs` has no update/delete route. | Documented as by design in `NoteTambahan.md`. |
| P3-3 | `report_status` attendance filter is validated and supported by the scope service but not exposed in the UI. | Harmless; adding the control is a feature. |
| P3-4 | Debug scripts `_dbcheck.php`, `_logincheck.php`, `_audit_db.php` in the project root; DB backups `database/database.sqlite.bak-20260817`, `database/database.sqlite.bak-preseed-20260817`. | **Not deleted** — flagged for the owner. They sit outside `public/`, so they are not web-reachable, but they are dev debris and `_logincheck.php` contains the demo credential `admin@lrs.com` / `password`. Recommend removal (or `.gitignore`) before any deployment. |
| ISSUE-015 | Deleting report media never removes the Cloudinary asset: `report_media` has no `cloudinary_public_id` column, so the delete call is always a no-op. | Persisting the id needs an additive migration + capturing the upload response field — schema and behaviour change beyond stabilization (rule 2/4). No impact today (`report_media` is empty). |

---

## Regression Result

**PASS — no regression.**

```text
Tests:      62 passed (301 assertions)
Failures:   0
Errors:     0
Deprecations: 0
```

Pre-audit baseline (the 17 Phase 0–12 tests) is 100 % green after every fix. 45 tests were added by
this audit. Detail and per-fix regression mapping: [regression-test-report.md](regression-test-report.md).

Health checks:

```text
php artisan migrate:status   13 migrations, all Ran, 0 Pending
php artisan route:list       44 application routes, no duplicate, no shadowed path
php artisan about            Laravel 12.66.0 / PHP 8.4.24 / env local / sqlite
npm run build                'No build step required'  (no Vite pipeline; Bootstrap 5.3 via CDN)
storage/logs/laravel.log     every error class traced to a bug entry; the 2 newest
                             (Undefined array key "secure_url") are BUG-012, now fixed
```

---

## Security Result

**PASS.** 8/8 cross-school isolation tests and 15/15 role isolation tests green; both P0 authorization
holes closed with backend checks, not UI guards. Full matrix: [security-audit.md](security-audit.md).

```text
Authentication                 PASS
Authorization                  PASS
School Isolation               PASS
Direct URL Access              PASS
Query Parameter Manipulation   PASS
Export Authorization           PASS
Role Escalation                PASS
```

---

## Data Integrity Result

**PASS — 16 of 16 checks.**

Checks 01–15 (structural) all return **0 rows** — no orphans, no school/class mismatch, no attendance
outside its report class, no duplicate assignment, no school-scoped account without a scope. Check 09 in
particular proves the BUG-004 fix holds on live data.

Check **16** — *report with zero attendance rows* — was **added by this audit** after BUG-012 was found.
It returned 2 rows during the audit and returns **0** on re-verification: `reports` now holds ids 1–3,
each with 6 of 6 students recorded. Check 07 confirms the removal left no orphaned attendance rows
(18 rows = 3 reports × 6 students). Detail: [data-integrity-report.md](data-integrity-report.md).

---

## Final Quality Gate (brief §48)

```text
[x] Authentication
[x] Role authorization
[x] Relation workflow
[x] School management
[x] Student management
[x] Program Kelas
[x] Coach
[x] Coach Assignment
[x] Program
[x] PIC plotting
[x] Cross-school security
[x] Attendance
[x] Attendance Export
[x] Finance CSV
[x] Accident Notes
[x] Existing features            (nothing the audit touched regressed; report media upload is still
                                  non-functional in this environment — invalid Cloudinary cloud name,
                                  ISSUE-014 — exactly as it was before the audit began, so it is not a
                                  regression. Media is optional on the report form; reports without
                                  media submit normally.)
[x] Database integrity           16 of 16 checks pass (check 16 was 2 rows, now 0)
[x] Laravel tests                62 passed / 301 assertions
[x] Frontend build
```

All 19 items pass.

## WaHa Gate (brief §49)

```text
ALL P0        = PASS
ALL P1        = PASS      (BUG-012 included — fixed, 5 regression tests)
CORE E2E      = PASS
SECURITY TEST = PASS
REGRESSION    = PASS
BUILD         = PASS
```

## WaHa Readiness

```text
SYSTEM STATUS:
READY FOR WAHA
```

Both gates pass: all 19 §48 items and all 6 §49 criteria. Phase 0–12 is stable, no P0/P1 is open, the
database is clean on all 16 integrity checks, and no security or isolation boundary is crossed.

**One open item to weigh before starting Phase 13.** ISSUE-014 (P2) leaves report photo/video upload
non-functional. It does not block Phase 13 architecturally — nothing about WhatsApp delivery depends on
Cloudinary, media is optional on the report form, and the failure path writes nothing and leaks nothing.
But **if Phase 13 is scoped to send report photos over WhatsApp, ISSUE-014 becomes a hard prerequisite**,
because there will be no stored media to attach. Fix it first in that case.

Two further Phase-13 prerequisites, neither a stabilization defect:

1. `QUEUE_CONNECTION=sync`. Outbound WhatsApp calls must not run inside the request cycle; move to
   `database` (or Redis) and run a worker first.
2. `APP_DEBUG=true`. Correct for local; must be `false` wherever WaHa credentials are configured, so
   stack traces cannot expose the API token.

**Phase 13 status: NOT IMPLEMENTED — DEFERRED — PHASE 13.** No WaHa/WhatsApp code, config, route,
job, webhook or dependency exists anywhere in the project (verified by static search).

Re-verification commands, for repeating this verdict at any time:

```text
php artisan test                                   expect 62 passed (301 assertions)
integrity check 16 (report with zero attendance)   expect 0
curl -u <key>:<secret> \
  https://api.cloudinary.com/v1_1/<cloud>/ping     expect 200 {"status":"ok"}  (currently 401)
```
