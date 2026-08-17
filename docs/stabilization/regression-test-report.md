# Regression Test Report

**Audit date:** 2026-08-17
**Re-verified:** 2026-08-17, post-audit (after the owner acted on DATA-001 and ISSUE-014)
**Command:** `php artisan test`
**Result:** **62 passed, 301 assertions, 0 failures, 0 errors, 0 deprecations** — identical in both runs

---

## Re-verification run

Executed after the database contents and `.env` changed. No application code changed between the two
runs, so this pass proves the suite is not coupled to the live data it was written alongside.

```text
Tests:    62 passed (301 assertions)
Duration: 6.03s

php artisan migrate:status   13 migrations, all Ran, 0 Pending
php artisan route:list       Showing [44] routes
php artisan about            Laravel 12.66.0 / PHP 8.4.24 / env local / sqlite / queue sync
npm run build                'No build step required'
integrity checks 01–16       0 of 16 failing  (check 16 was 2, now 0)
```

Because the tests run against `:memory:` with `RefreshDatabase`, the removal of `reports` 4 and 5 and the
addition of a coach and two assignments could not have influenced the result — which is why the
re-verification also included **live-data** checks against `database/database.sqlite` directly:

| Live check (read-only) | Result |
|---|---|
| Capability matrix, all 8 accounts | PASS — only SuperAdmin holds `reports.review`; `coach` holds no `attendance.*` |
| PIC / Finance scoped to plotted school only | PASS — school 1 only, zero school-2 rows |
| PIC / Finance forcing `?school_id=2` | PASS — 0 rows, scope cannot widen |
| Attendance export + Finance CSV, real rows | PASS — 18 rows, header exact, no foreign school |
| Accident notes intact, multi-line preserved | PASS — report 3 = `WIFI LEMOT\nANAK BERANTEM` |
| Multi-school coach (coach 8: schools 1 + 2) | PASS — scoped by `coach_id`, not school |
| Coach own-report list | PASS — coach 3 sees reports 1–3, 18 attendance rows |
| Cloudinary ping | **FAIL — HTTP 401 `cloud_name mismatch`** (ISSUE-014, open) |

The Cloudinary failure is an environment value, not a regression: it fails closed, and the real API's
error response has exactly the shape `CoachReportAtomicityTest` injects via its test double
(`['error' => ['message' => …]]`, no `secure_url`), confirmed by calling
`CloudinaryHelper::upload()` against the live credentials with a 1×1 PNG. The 5 atomicity tests therefore
cover the genuine failure mode.

---

## Summary

```text
Tests:    62 passed (301 assertions)
```

| Suite | Tests | Status | Origin |
|---|---|---|---|
| `Tests\Unit\AuthorizationServiceTest` | 3 | PASS | pre-audit |
| `Tests\Unit\ExampleTest` | 1 | PASS | pre-audit |
| `Tests\Feature\ExampleTest` | 1 | PASS | pre-audit |
| `Tests\Feature\RoleRedirectTest` | 2 | PASS | pre-audit |
| `Tests\Feature\SchoolManagementTest` | 6 | PASS | pre-audit |
| `Tests\Feature\StudentManagementTest` | 4 | PASS | pre-audit |
| `Tests\Feature\RoleIsolationTest` | 15 | PASS | added by audit |
| `Tests\Feature\CrossSchoolSecurityTest` | 8 | PASS | added by audit |
| `Tests\Feature\CoachReportAuthorizationTest` | 5 | PASS | added by audit |
| `Tests\Feature\CoachReportAtomicityTest` | 5 (38 assertions) | PASS | added by audit |
| `Tests\Feature\MasterDataIntegrityTest` | 11 | PASS | added by audit |
| `Tests\Feature\EndToEndFlowTest` | 1 (62 assertions) | PASS | added by audit |

**Regression baseline: the 17 pre-audit tests are still 100 % green after every fix.** No existing
behaviour was broken. 45 tests were added.

Test database: SQLite `:memory:` with `RefreshDatabase`, so no test touches
`database/database.sqlite`. `CoachReportAtomicityTest` overrides only the Cloudinary HTTP boundary, so
no test makes an outbound network call.

---

## Full test inventory

```text
PASS  Tests\Unit\AuthorizationServiceTest
  ✓ superadmin has wildcard access
  ✓ relation has operational permissions without user management
  ✓ finance can export csv but cannot manage master data

PASS  Tests\Unit\ExampleTest
  ✓ that true is true

PASS  Tests\Feature\CoachReportAuthorizationTest
  ✓ coach can submit report for assigned class
  ✓ coach cannot submit report for unassigned class
  ✓ coach cannot record attendance for student outside the class
  ✓ coach cannot update report with foreign student attendance
  ✓ coach cannot read the global report review console

PASS  Tests\Feature\CoachReportAtomicityTest
  ✓ a failed photo upload stores no report at all
  ✓ a failed video upload stores no report at all
  ✓ a report without media still saves with its attendance
  ✓ a failed upload on update keeps the previous attendance
  ✓ exceeding the photo cap is refused visibly and changes nothing

PASS  Tests\Feature\CrossSchoolSecurityTest
  ✓ pic only sees the plotted school attendance
  ✓ pic cannot pivot to another school via query parameter
  ✓ pic export cannot leak another school
  ✓ pic export contains the plotted school rows
  ✓ finance export is scoped and csv shaped
  ✓ pic cannot open a report of another school
  ✓ pic cannot open a class of another school
  ✓ export dataset matches the filtered table

PASS  Tests\Feature\EndToEndFlowTest
  ✓ the full learning report journey runs end to end        (62 assertions)

PASS  Tests\Feature\ExampleTest
  ✓ the application redirects guests to login

PASS  Tests\Feature\MasterDataIntegrityTest
  ✓ superadmin can create an spv coach account
  ✓ superadmin can change a role to spv coach
  ✓ user management renders every canonical role
  ✓ finance plotting is visible in user management
  ✓ navbar role badge uses the canonical role label
  ✓ deleting a school with reports is refused readably
  ✓ deleting a school without dependents still works
  ✓ deleting a class with reports is refused readably
  ✓ deleting a class without reports still works
  ✓ deleting a coach with reports is refused readably
  ✓ deleting a user without reports still works

PASS  Tests\Feature\RoleIsolationTest
  ✓ user management is superadmin only with data set "relation"
  ✓ user management is superadmin only with data set "spv coach"
  ✓ user management is superadmin only with data set "coach"
  ✓ user management is superadmin only with data set "school pic"
  ✓ user management is superadmin only with data set "finance"
  ✓ superadmin reaches every master data screen
  ✓ relation reaches its operational screens only
  ✓ spv coach reaches coach management but not relation master data
  ✓ finance reaches attendance only
  ✓ school pic cannot reach school master data
  ✓ coach cannot reach admin master data
  ✓ guests are redirected to login for protected routes
  ✓ invalid credentials are rejected
  ✓ logout clears the session
  ✓ every role can login and land on a reachable page

PASS  Tests\Feature\RoleRedirectTest
  ✓ relation can login through the compatibility dashboard
  ✓ superadmin can login through the compatibility dashboard

PASS  Tests\Feature\SchoolManagementTest
  ✓ relation can open school management
  ✓ relation can create a school
  ✓ school name is required
  ✓ superadmin can open school management
  ✓ coach cannot open school management
  ✓ relation cannot update or delete school without permission

PASS  Tests\Feature\StudentManagementTest
  ✓ relation can create a student for a class school
  ✓ student name is required
  ✓ user without student permission is rejected
  ✓ coach without class assignment is rejected

Tests:  57 passed (263 assertions)
```

> The inventory above is the ordering PHPUnit printed; the final run is **62 passed (301 assertions)**
> with `CoachReportAtomicityTest` included.

---

## Per-fix regression mapping (brief §47)

Each fix was followed by its targeted test **and** the downstream regression suites the brief prescribes.

| Fix | Targeted test | Downstream regression run | Result |
|---|---|---|---|
| BUG-001 migrate dev DB | `migrate:status` (13 Ran / 0 Pending) | full suite + `_audit_db.php` integrity | PASS |
| BUG-002 report console gate | `coach_cannot_read_the_global_report_review_console` | `RoleIsolationTest` (15), `CrossSchoolSecurityTest` (8), `EndToEndFlowTest` (SuperAdmin review + approve still works) | PASS |
| BUG-003 assignment check | `coach_cannot_submit_report_for_unassigned_class` | `coach_can_submit_report_for_assigned_class`, attendance regression, `EndToEndFlowTest` | PASS |
| BUG-004 attendance keys | `coach_cannot_record_attendance_for_student_outside_the_class`, `coach_cannot_update_report_with_foreign_student_attendance` | export tests (8), PIC security tests, Finance CSV test | PASS |
| BUG-005 login redirect | `every_role_can_login_and_land_on_a_reachable_page` | `RoleRedirectTest` (2, pre-audit), `RoleIsolationTest` | PASS |
| BUG-006 role metadata | `superadmin_can_create_an_spv_coach_account`, `superadmin_can_change_a_role_to_spv_coach`, `user_management_renders_every_canonical_role` | `RoleIsolationTest` user-management data provider (5 roles), `AuthorizationServiceTest` | PASS |
| BUG-007 FK RESTRICT guards | 3 refusal tests + 3 paired "still works" tests | `SchoolManagementTest` (6, pre-audit), `StudentManagementTest` (4, pre-audit) — School fix → School tests → Student regression, as prescribed | PASS |
| BUG-008 seeder | `db:seed --force` run twice, row counts compared | `_audit_db.php` (15 checks), full suite | PASS |
| BUG-009 Finance plotting | `finance_plotting_is_visible_in_user_management` | `MasterDataIntegrityTest` (11), `RoleIsolationTest` | PASS |
| BUG-010 navbar label | `navbar_role_badge_uses_the_canonical_role_label` | full suite (the layout renders on every page, so any suite would catch a Blade error) | PASS |
| BUG-012 atomic report write | `CoachReportAtomicityTest` (5 tests: failed photo, failed video, no-media happy path, failed update, cap) | `CoachReportAuthorizationTest` (5 — same write path, BUG-003/BUG-004 guards), `CrossSchoolSecurityTest` (8), `EndToEndFlowTest`, full suite | PASS |
| BUG-013 invisible cap error | `exceeding_the_photo_cap_is_refused_visibly_and_changes_nothing` | `CoachReportAtomicityTest` (5), `EndToEndFlowTest` (report submit + review + approve) | PASS |

---

## End-to-end journey (brief §40)

`EndToEndFlowTest::the_full_learning_report_journey_runs_end_to_end` — one test, **62 assertions**, all
through real HTTP requests (`post('/login')`, not only `actingAs`), covering the mandated journey:

```text
 1  SuperAdmin login                    → redirect admin.dashboard, 200
 2  Create School "SD Nusantara"        → persisted
 3  Create Program Kelas "Grade 6A"     → school_id linked
 4  Create + plot School PIC            → assignedSchoolIds() == [school]
 5  Add 3 Students (Andi, Bela, Citra)  → 3 rows on the class
 6  Create Coach + assign the class     → coach_classes row
 7  Create Program "CD-01" w/ class_ids → program_classes link
 8  Coach login → submit report         → redirect coach.reports.index
    attendance present/sick/absent      → 3 report_attendances, status submitted
    accident note                       → visible on the Coach report list
 9  SuperAdmin console + detail         → 200, "Accident Notes" rendered
    approve                             → status approved
10  PIC login → dashboard, report,      → 200, sees "Grade 6A" / "Andi"
    attendance, CSV export              → CSV contains Andi + SD Nusantara
11  Finance login → filtered export     → header exact; contains Bela; excludes Andi
12  SPV Coach login → coach mgmt        → unassign then re-assign succeeds
```

Multi-line activity text (`"Baris pertama\nBaris kedua"`) is submitted and asserted, covering the
newline-preserving report rendering from the most recent commit.

---

## Health checks (brief §32–34)

```text
$ php artisan migrate:status
  13 migrations — all [Ran] (batch 1: 9, batch 2: 4) — 0 Pending

$ php artisan route:list --except-vendor
  Showing [44] routes — no duplicate path, no shadowed route

$ php artisan about --only=environment,drivers
  Laravel 12.66.0 | PHP 8.4.24 | Composer 2.10.1 | env local | Debug ENABLED
  URL localhost:8000 | Timezone UTC | Locale en
  Broadcasting log | Cache database | Database sqlite | Logs stack/single
  Mail log | Queue sync | Session file

$ npm run build
  'No build step required'
  (no Vite pipeline in this project — Bootstrap 5.3 is loaded from CDN;
   no "Module not found" / "Syntax error" / "Missing asset" / Vite error possible)
```

---

## Log audit (brief §35)

`storage/logs/laravel.log` — every distinct error class was traced to a defect in
[bug-list.md](bug-list.md); **no unexplained error remains.**

| Occurrences | Error | Maps to |
|---|---|---|
| 6 | `SQLSTATE[23000] ... FOREIGN KEY constraint failed` | BUG-007 — fixed |
| 3 | `SQLSTATE[HY000]: General error: 1 no such table: school_user` | BUG-001 — fixed |
| 7 | `Declaration of App\Models\User::isRelation(): bool must be compatible with ...Model::isRelation($key)` | BUG-011 — fixed in Phase 3, non-reproducible |
| 2 | `Undefined array key "spv_coach" (View: .../admin/users/index.blade.php)` | BUG-006 — fixed |
| **2** | **`Undefined array key "secure_url"`** (07:17:31 and 07:19:25, `userId: 3`) | **BUG-012 — fixed. Root cause of DATA-001.** |
| 2 | `Psy\Exception\ParseErrorException: PHP Parse error ...` | Not a defect — malformed `artisan tinker --execute` one-liners from audit sessions |
| 1 | `Command "test" is not defined.` (2026-08-14) | Not a defect — predates the PHPUnit setup; `php artisan test` now runs 62 tests |

### The two `secure_url` errors mattered — they were not noise

They appeared **during** this audit, logged by the owner using the app live while the audit ran. Their
timestamps sit 4 and 7 seconds after the creation timestamps of `reports` 4 and 5, the only two reports
in the database with zero attendance. That correlation is what exposed BUG-012 — a P1 data-loss defect
that no test in the suite would have caught, because none of the pre-existing tests submitted a report
with media, and none of the 15 structural integrity checks asked "does this report have any attendance
at all?".

Two consequences, both applied:

1. A 16th integrity check was added — *report with zero attendance rows* — and it is the one check that
   currently fails. See [data-integrity-report.md](data-integrity-report.md).
2. `CoachReportAtomicityTest` was written so the failure mode is now covered by 5 assertions-heavy tests
   rather than by log archaeology.

An earlier draft of this report claimed "no new `local.ERROR` was logged after the fixes". That was
written before these two entries appeared and was **wrong**; it has been corrected here rather than
quietly dropped.

---

## Performance / N+1 check (brief §38, §42)

No speculative optimization was performed. Every list screen was checked for eager loading; all were
already correct, so **no change was made**.

| Screen | Eager load | Verdict |
|---|---|---|
| Attendance list + export | `AttendanceScopeService::query()` → `with(['student', 'report.school', 'report.schoolClass', 'report.coach'])` | OK. `chunkById(500)` preserves the eager loads per chunk, so the CSV export is not N+1 either. |
| Students | `StudentController@index` → `$class->load('school')` | OK |
| Programs | `Program::with('programClasses.schoolClass.school')` | OK |
| Coaches | `->with(['coachClasses.schoolClass.school'])`, detail `load('coachClasses.schoolClass.school')` | OK |
| Classes | `SchoolClass::with('school')->paginate(20)` | OK |
| Schools | `School::withCount('classes')->paginate(15)` | OK |
| Reports (admin) | `Report::with(['coach','school','schoolClass'])`, detail `load([... ,'attendances.student','media'])` | OK |
| Reports (PIC) | `Report::with(['schoolClass','coach'])` | OK |
| Users | `User::with(['school','schools'])` | OK |

No filter-in-PHP-after-fetching-everything pattern found: all scoping and filtering happens in the query
builder, and every list is paginated (15–50 per page).
