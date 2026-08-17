# Bug List

Audit date: 2026-08-17. Fix order followed the mandated priority: P0 → P1 → P2 → P3.
Every entry was reproduced before being fixed and re-tested after being fixed.

| ID | Severity | Module | Status |
|---|---|---|---|
| BUG-001 | P0 | Database / Migration | VERIFIED |
| BUG-002 | P0 | Authorization / Report Review | VERIFIED |
| BUG-003 | P0 | Coach Assignment / Report | VERIFIED |
| BUG-004 | P0 | Attendance | VERIFIED |
| BUG-005 | P0 | Authentication | VERIFIED |
| BUG-006 | P1 | User Management | VERIFIED |
| BUG-007 | P1 | Master Data (School / Class / User delete) | VERIFIED |
| BUG-008 | P1 | Seeder / Demo data | VERIFIED |
| BUG-009 | P2 | User Management (UI) | VERIFIED |
| BUG-010 | P3 | Layout (UI) | VERIFIED |
| BUG-011 | P1 | Model / Fatal error | VERIFIED (fixed in Phase 3, confirmed non-reproducible) |
| BUG-012 | **P1** | **Report write / Attendance (data loss)** | **VERIFIED** |
| BUG-013 | P2 | Report edit (invisible error message) | VERIFIED |
| ISSUE-012 | P3 | Navigation | OPEN — needs product decision |
| ISSUE-013 | P3 | Repo hygiene | OPEN — flagged, nothing deleted |
| ISSUE-014 | P2 | Configuration (Cloudinary `cloud_name` invalid) | OPEN — owner action, see CONFIG-001 |
| ISSUE-015 | P3 | Media lifecycle (orphaned Cloudinary assets) | OPEN — schema change out of scope, see MEDIA-001 |

BUG-012 was found **late in the audit, on live data** — not by a test. It is the only defect in this list
that had already damaged stored records.

**Re-verification 2026-08-17 (post-audit):** the two damaged rows no longer exist in
`database/database.sqlite`; integrity check 16 now returns **0 of 3 reports**. The audit did not remove
them — see DATA-001 in [data-integrity-report.md](data-integrity-report.md) for how the state was
reached and what it cost. ISSUE-014 changed cause but is still open: the credentials are no longer
blank, but `CLOUDINARY_CLOUD_NAME` holds a value Cloudinary rejects.

---

## BUG-001

### Severity
P0

### Module
Database / Migration

### Reproduction
1. Open `/admin/users` on the development environment.
2. Or open any Program screen.

### Expected
Page renders. `school_user`, `programs`, `program_classes` exist.

### Actual
```text
SQLSTATE[HY000]: General error: 1 no such table: school_user
```
3 occurrences in `storage/logs/laravel.log` (2026-08-17).

### Root Cause
The four Phase migrations
(`2026_08_14_000000_migrate_admin_role_to_relation_and_expand_roles`,
`2026_08_17_000000_create_programs_table`,
`2026_08_17_000001_create_program_classes_table`,
`2026_08_17_000002_create_school_user_table`)
were authored but never applied to `database/database.sqlite`. The test suite passed because
`RefreshDatabase` migrates a fresh `:memory:` database, which masked the state of the dev DB.

### Fix
Backed the database up to `database/database.sqlite.bak-20260817`, then ran
`php artisan migrate --force`. Non-destructive: the migrations only add tables and widen the role
column. No data was dropped or rewritten.

### Regression Test
`php artisan migrate:status` → 13 migrations, all `Ran`, 0 `Pending`.
`php _audit_db.php` → all tables present, all 15 integrity checks return 0.

### Status
VERIFIED

---

## BUG-002

### Severity
P0 — security breach, cross-role and cross-school data leakage

### Module
Authorization / Report Review console

### Reproduction
1. Log in as `coach@lrs.com`.
2. `GET /admin/reports`.
3. `GET /admin/reports/{id}` for a report belonging to a *different* coach and a different school.

### Expected
403 on both. The global review console reads every coach's report and must be SuperAdmin-only.

### Actual
200 on both. Any Coach could read every report in the system, for every school, including
Accident Notes.

### Root Cause
The routes were gated on `permission:reports.view`. `reports.view` is the **Coach** capability that
authorizes a coach to read *their own* reports (`ROLE_PERMISSIONS['coach']` in
`app/Services/AuthorizationService.php`). Gating a global console on a per-owner capability grants the
console to every holder of that capability. `Admin\ReportController` additionally performed no
object-level check, so it depended entirely on the route gate.

### Fix
Three layers, none of them frontend-only:

1. `routes/web.php` — both routes re-gated on `permission:reports.review` (SuperAdmin-only capability).
2. `resources/views/layouts/app.blade.php` — nav link guard changed to the same capability, so UI and
   route agree.
3. `app/Http/Controllers/Admin/ReportController.php` — added `ensureSchoolAccess()` on `show`,
   `approve` and `reject`, plus a school-scope pre-filter in `index()` applied **before** the
   request-supplied `school_id` filter, so a `?school_id=` can only narrow the scope, never widen it.

### Regression Test
`CoachReportAuthorizationTest::coach_cannot_read_the_global_report_review_console`
`RoleIsolationTest::coach_cannot_reach_admin_master_data`
`CrossSchoolSecurityTest::pic_cannot_open_a_report_of_another_school`

### Status
VERIFIED

---

## BUG-003

### Severity
P0 — authorization bypass / data corruption

### Module
Coach Assignment / Coach report submission

### Reproduction
1. Log in as a Coach assigned to class A only.
2. `POST /coach/reports` with `class_id` = id of class B (another coach's class, another school).

### Expected
403. Assignment is the authorization boundary for a report write.

### Actual
Report created against class B, with `school_id` copied from class B.

### Root Cause
`Coach\ReportController@store` did `SchoolClass::findOrFail($validated['class_id'])`. Validation only
checked that the class *exists*, never that it is assigned to the acting coach. The class dropdown was
correctly filtered in Blade, so the hole was invisible in the UI — a frontend-only control.

### Fix
`assignedClassOrFail(int $classId): SchoolClass` resolves the class through
`whereHas('coachAssignments', fn ($q) => $q->where('coach_id', Auth::id()))` and `abort_if(null, 403)`.
`store()` now calls it instead of `findOrFail`.

### Regression Test
`CoachReportAuthorizationTest::coach_cannot_submit_report_for_unassigned_class`
`CoachReportAuthorizationTest::coach_can_submit_report_for_assigned_class` (proves the happy path
still works)

### Status
VERIFIED

---

## BUG-004

### Severity
P0 — data corruption / cross-class data leakage

### Module
Attendance

### Reproduction
1. Log in as a Coach assigned to class A.
2. `POST /coach/reports` with a valid `class_id` = A, but `attendance[{id of a student in class B}] = 'absent'`.
3. Repeat against `PUT /coach/reports/{report}` on an owned report.

### Expected
422 with a validation message on `attendance`.

### Actual
`report_attendances` rows written linking a report of class A to a student of class B. The row then
appears in the PIC and Finance attendance list and CSV export of the *wrong* school.

### Root Cause
Attendance is posted as a map, `attendance[student_id] => status`, so the **array keys are untrusted
user input**. The validation rules covered the values (`in:present,absent,sick,permission`) but never
the keys. Both `store()` and `update()` looped the submitted map and wrote it directly.

### Fix
`assertAttendanceBelongsToClass(array $attendance, int $classId): void` diffs the submitted student ids
against `Student::where('class_id', $classId)->pluck('id')` and throws
`ValidationException::withMessages(['attendance' => ...])` if anything foreign is present. Called in
`store()` (after the class is resolved) and in `update()` (against `$report->class_id`) — in both cases
**before** any write.

### Regression Test
`CoachReportAuthorizationTest::coach_cannot_record_attendance_for_student_outside_the_class`
`CoachReportAuthorizationTest::coach_cannot_update_report_with_foreign_student_attendance`
`EndToEndFlowTest` (proves legitimate 3-student attendance still persists)
`data-integrity-report.md` check 08 (`attendance student not in report class` → 0 rows)

### Status
VERIFIED

---

## BUG-005

### Severity
P0 — authentication failure, role locked out

### Module
Authentication

### Reproduction
1. Create an `spv_coach` account (or log in as `spv@lrs.com`).
2. Submit the login form with correct credentials.

### Expected
Redirect to a page the role can actually open.

### Actual
Infinite redirect: `POST /login` → `/` → `login` → `/`. Credentials were accepted; the user could
never land anywhere. Same trap for any future role.

### Root Cause
`Auth\LoginController::redirectByRole()` ended in `default => redirect('/')`, and `/` is itself a
redirect to `login` for an authenticated user without a mapped landing page. `spv_coach` had no `match`
arm, so it fell into `default`.

### Fix
All six canonical roles mapped explicitly; the fallback is now a loud failure rather than a silent loop:

```php
default => abort(403, 'Role akun belum memiliki halaman awal. Hubungi SuperAdmin.'),
```

Landing pages: superadmin → `admin.dashboard`, relation → `admin.schools.index`,
spv_coach → `admin.coaches.index`, coach → `coach.reports.index`, school_pic → `pic.dashboard`,
finance → `attendance.index`.

### Regression Test
`RoleIsolationTest::every_role_can_login_and_land_on_a_reachable_page` (asserts the redirect target
per role **and** that the target returns 200)
`RoleRedirectTest` (2 tests, pre-existing — still green)

### Status
VERIFIED

---

## BUG-006

### Severity
P1 — role permission wrong / core feature broken

### Module
User Management

### Reproduction
1. Log in as SuperAdmin, open `/admin/users`.
2. Try to create an account with role `spv_coach`.
3. With an existing `spv_coach` row in the table, reload the page.

### Expected
`SPV Coach` selectable in all three role dropdowns; the row renders with a role badge.

### Actual
`spv_coach` absent from the filter, create and edit dropdowns — the role was uncreatable through the
UI. `POST` with `role=spv_coach` was rejected by validation. Rendering an existing `spv_coach` row
threw:

```text
Undefined array key "spv_coach" (View: resources/views/admin/users/index.blade.php)
```
2 occurrences in `storage/logs/laravel.log`.

### Root Cause
Role metadata was duplicated in four places that drifted apart: the `in:` validation list in
`store()`, the `in:` list in `update()`, and a local `@php $roleColors / $roleLabels @endphp` block in
the Blade view — none of which were updated when `spv_coach` was added to `User` and to
`AuthorizationService::ROLE_PERMISSIONS`.

### Fix
Single source of truth on the model, so a new role cannot be missed in one place again:
`User::roleLabels()`, `User::roleBadgeColors()`, `User::schoolScopedRoles()`, plus the instance helpers
`roleLabel()`, `roleBadgeColor()`, `isSchoolScoped()`.

- `UserController@store/@update` → `'role' => ['required', Rule::in(User::roleKeys())]`.
- The four repeated `in_array($request->role, ['school_pic','finance'], true)` calls collapsed into one
  hoisted `$isSchoolScoped = in_array($request->role, User::schoolScopedRoles(), true)`.
- All three Blade dropdowns loop `User::roleLabels()`; the local `@php` block was deleted.
- The dropdown-toggle JS reads `@json(User::schoolScopedRoles())` instead of a hardcoded array, so the
  frontend list can never diverge from the backend list.

### Regression Test
`MasterDataIntegrityTest::superadmin_can_create_an_spv_coach_account`
`MasterDataIntegrityTest::superadmin_can_change_a_role_to_spv_coach`
`MasterDataIntegrityTest::user_management_renders_every_canonical_role` (loops `User::roleKeys()`, so
it fails automatically if a future role is added without labels)

### Status
VERIFIED

---

## BUG-007

### Severity
P1 — unhandled 500 on a normal admin action

### Module
Master Data — School / Program Kelas / User delete

### Reproduction
1. As SuperAdmin, delete a school that still has reports. Same for a class with reports, and for a
   coach who has authored reports.

### Expected
A readable refusal explaining that historical reports still reference the row.

### Actual
HTTP 500:
```text
SQLSTATE[23000]: Integrity constraint violation: 19 FOREIGN KEY constraint failed
(Connection: sqlite, Database: :memory:, SQL: delete from "users" where "id" = 2)
```
6 occurrences in `storage/logs/laravel.log`.

### Root Cause
`reports.coach_id`, `reports.school_id` and `reports.class_id` are declared **without** `onDelete`, so
SQLite applies `RESTRICT`. That is the correct data design — historical reports must not disappear —
but the three `destroy()` methods issued the delete with no pre-check, so the database refusal surfaced
as an unhandled exception.

### Fix
Root-cause fix, not exception hiding (rule 13). The FK semantics were left untouched; the application
now asks the question the database is going to ask:

- `app/Models/School.php`, `app/Models/SchoolClass.php`, `app/Models/User.php` each gained a
  `reports()` relation, documented as existing to answer "may this row still be deleted?".
- `Admin\SchoolController@destroy`, `Admin\ClassController@destroy`, `Admin\UserController@destroy`
  return `back()->with('error', ...)` when `->reports()->exists()`.

`User::destroy()` keeps its existing self-delete guard; the report guard is additive.

### Regression Test
Paired tests, so the guard cannot silently block legitimate deletes:
`MasterDataIntegrityTest::deleting_a_school_with_reports_is_refused_readably` / `..._without_dependents_still_works`
`MasterDataIntegrityTest::deleting_a_class_with_reports_is_refused_readably` / `..._without_reports_still_works`
`MasterDataIntegrityTest::deleting_a_coach_with_reports_is_refused_readably` / `deleting_a_user_without_reports_still_works`

### Status
VERIFIED

---

## BUG-008

### Severity
P1 — demo/QA data cannot exercise the system

### Module
Seeder

### Reproduction
1. `php artisan db:seed` twice.
2. Try to log in as an `spv_coach` or `finance` user.

### Expected
Idempotent seed; one account per canonical role.

### Actual
No `spv_coach` and no `finance` account existed at all, so two of the six roles could not be tested
end to end. Every record used `create()`, so a second run duplicated the school, class and students
(and hit unique constraints on users).

### Fix
`DatabaseSeeder` rewritten: `updateOrCreate()` / `firstOrCreate()` throughout, keyed on natural keys
(email, school name, class name+school, student name+class). Added `spv@lrs.com` (spv_coach) and
`finance@lrs.com` (finance). PIC and Finance both get `schools()->sync([$school->id])` so the pivot
scope — not only the legacy `users.school_id` — is populated.

### Regression Test
Ran `php artisan db:seed --force` **twice** (after backing up to
`database/database.sqlite.bak-preseed-20260817`). Row counts identical between run 1 and run 2:
schools 1, classes 2, students 6, coach_classes 1, programs 1, program_classes 1. `school_user` grew
1 → 2, which is the intended new Finance plotting. All six roles present.

### Status
VERIFIED

---

## BUG-009

### Severity
P2 — UI/relationship display issue

### Module
User Management (UI)

### Reproduction
1. Plot a Finance account to a school.
2. Open `/admin/users`.

### Expected
The school scope is visible for Finance, because Finance is school-scoped and the scope decides what
that account can export.

### Actual
The scope column showed `-` for Finance. The column header read `Sekolah (PIC)`, implying the scope
only applies to PIC.

### Root Cause
The column was guarded on `$user->role === 'school_pic'`, hardcoded — even though
`accessibleSchoolIds()` restricts Finance the same way.

### Fix
Guard changed to `$user->isSchoolScoped()` (which reads `User::schoolScopedRoles()`); header renamed
`Sekolah (Scope)`; the edit modal's school-field visibility uses the same helper. The create modal now
also honours `old('role')`/`old('school_ids')`, so a validation failure no longer hides the field the
user was filling in.

### Regression Test
`MasterDataIntegrityTest::finance_plotting_is_visible_in_user_management` — asserts the exact badge
markup `<span class="badge bg-success me-1 mb-1">School A</span>` with `assertSee(..., false)`. The
first version of this test used `assertSee('School A')` and passed for the wrong reason (it matched
the plotting `<select>` options); it was tightened.

### Status
VERIFIED

---

## BUG-010

### Severity
P3 — cosmetic, same root cause as BUG-006

### Module
Layout (navbar)

### Reproduction
Log in as `spv_coach` and read the role badge in the top-right dropdown.

### Expected
`SPV Coach` — the same label User Management shows for that account.

### Actual
`Spv coach`.

### Root Cause
`resources/views/layouts/app.blade.php` built the label with
`str_replace('_', ' ', ucfirst($user->role))`. This was the last place still deriving a role label from
the raw string instead of the centralized map.

### Fix
One-line change to `{{ auth()->user()->roleLabel() }}`.

### Regression Test
`MasterDataIntegrityTest::navbar_role_badge_uses_the_canonical_role_label` — asserts `SPV Coach` is
present and `Spv coach` is absent.

### Status
VERIFIED

---

## BUG-011

### Severity
P1 (historical — already fixed before this audit)

### Module
`App\Models\User` — fatal error

### Reproduction
Not reproducible on the current code. Found by log audit only.

### Expected
n/a

### Actual
```text
Declaration of App\Models\User::isRelation(): bool must be compatible with
Illuminate\Database\Eloquent\Model::isRelation($key)
```
7 occurrences in `storage/logs/laravel.log`: 2026-08-14 (×2), 2026-08-16 (×3), 2026-08-17 04:52 (×2).
A `FatalError`, i.e. a hard white screen.

### Root Cause
`User::isRelation(): bool` collided with `Illuminate\Database\Eloquent\Concerns\HasAttributes::isRelation($key)`,
an inherited framework method with an incompatible signature.

### Fix
Already fixed during Phase 3 by renaming to `isRelationUser()` — recorded in
`docs/implementation/implementation-notes.md:198`. Verified by static search: `isRelation(` no longer
exists in application code; the only call sites are
`AuthorizationService.php:76` and `:100`, both calling `isRelationUser()`. The last log occurrence
(04:52) predates the rename; nothing after it.

### Regression Test
`AuthorizationServiceTest` (3 tests) exercises `canAccessClass()` and `accessibleSchoolIds()`, both of
which call `isRelationUser()`. Whole suite green with 0 errors.

### Status
VERIFIED — non-reproducible. Logged here because the brief requires the log audit to be reported
(§35), not because action is outstanding.

---

## BUG-012

### Severity
P1 — "attendance wrong" per the brief's own §43 definition. Argued up from P2 because it **destroys
data that was submitted correctly**, and argued down from P0 because it is not a security breach, not
cross-school leakage, and the report text itself survives.

### Module
Report write path (`Coach\ReportController@store` / `@update`) → Attendance

### Reproduction
Found on live data, then reproduced deterministically in a test.

1. Cloudinary credentials in `.env` are blank (the actual state of this project — ISSUE-014), or the
   Cloudinary API is unreachable, or it rejects the file. Any of the three produces the same response
   shape: a decoded JSON body with **no `secure_url` key**.
2. Log in as a Coach, open **Buat Laporan**, fill the report, mark attendance for every student, attach
   at least one photo, submit.
3. The request 500s: `ErrorException: Undefined array key "secure_url"`.
4. Inspect the database.

```sql
SELECT r.id, r.status, (SELECT COUNT(*) FROM report_attendances a WHERE a.report_id = r.id) att
FROM reports r WHERE att = 0;
-- 4 | submitted | 0
-- 5 | submitted | 0
```

### Expected
Either the whole submission succeeds, or nothing at all is stored. Attendance was submitted and valid,
so it must never be silently dropped.

### Actual
The `reports` row is committed with `status = submitted` and **zero attendance rows**. The coach sees a
500 page and reasonably assumes nothing was saved, so they submit again — producing a second damaged
row (exactly what happened: reports 4 and 5, 2 minutes apart). Downstream, every attendance list and
every CSV export joins through `report_attendances`, so the lesson is invisible to PIC, Finance and
SPV Coach while the report itself sits in the SuperAdmin review queue as if it were complete.

### Root Cause
Two independent causes, both fixed:

1. **No transaction.** `store()` wrote in the order report → media → attendance. An exception in the
   media step aborted the request *after* `Report::create()` had already committed on its own and
   *before* the attendance loop ran. `update()` had the same shape, plus two
   `return back()->with('error', …)` guards (photo/video cap) placed **after** `$report->update([...
   'status' => 'submitted'])`, and a `$report->attendances()->delete()` that was not paired with its
   re-insert — so a failure between them wiped existing attendance outright.
2. **Unchecked upload result.** `CloudinaryHelper::upload()` returns `json_decode($response, true)`,
   which is `null` on a transport failure and `['error' => ['message' => …]]` on an API rejection.
   Reading `$result['secure_url']` on either raised `Undefined array key` — a crash where a handled
   failure belonged.

Confirmed against the log, matching creation time to error time:

```text
report 4 created 07:17:27  →  [2026-08-17 07:17:31] local.ERROR: Undefined array key "secure_url" {"userId":3,…}
report 5 created 07:19:18  →  [2026-08-17 07:19:25] local.ERROR: Undefined array key "secure_url" {"userId":3,…}
```

### Fix
`app/Http/Controllers/Coach/ReportController.php`:

- `store()` and `update()` now wrap the entire write sequence in `DB::transaction()`. Report, media and
  attendance land together or not at all.
- New `storeMedia()` validates the Cloudinary response and throws a readable `ValidationException`
  (which rolls the transaction back) instead of letting `Undefined array key` escape. It also logs
  *why* it failed, naming any blank credential.
- New `syncAttendance()` holds the delete-then-insert rewrite in one place, documented as
  transaction-only.
- New `uploadToCloudinary()` isolates the HTTP boundary so the failure is testable without calling the
  real API.
- In `update()`, `CloudinaryHelper::delete()` calls were moved **out** of the transaction: remote
  deletions cannot be rolled back, so public ids are collected inside and deleted only after commit.

No schema change. No behaviour change on the success path.

### Regression Test
`tests/Feature/CoachReportAtomicityTest.php` — 5 tests, 38 assertions. A subclass overrides only
`uploadToCloudinary()` to return the exact failure body Cloudinary sends, so the real guard, the real
validation and the real transaction all execute:

```text
✓ a failed photo upload stores no report at all
✓ a failed video upload stores no report at all
✓ a report without media still saves with its attendance
✓ a failed upload on update keeps the previous attendance
✓ exceeding the photo cap is refused visibly and changes nothing
```

Downstream regression: full suite **62 passed / 301 assertions** (was 57/263), including
`CoachReportAuthorizationTest` (the BUG-003/BUG-004 guards on the same write path),
`CrossSchoolSecurityTest`, `EndToEndFlowTest` and the 17 pre-audit tests.

### Status
VERIFIED — code fixed and covered by 5 tests.

The two rows this defect had already damaged are **no longer present** in the database as of the
2026-08-17 post-audit re-verification: `reports` now holds 3 rows (ids 1–3), all with complete
attendance, and integrity check 16 returns 0. The audit itself neither repaired nor removed them; see
DATA-001 in [data-integrity-report.md](data-integrity-report.md) for the resulting state, the fact that
the recommended non-destructive repair path (SuperAdmin rejects → coach re-enters attendance) was not the
route taken, and what was lost as a result.

---

## BUG-013

### Severity
P2 — validation/UX, no data impact once BUG-012's transaction is in place.

### Module
Report edit (`Coach\ReportController@update`) / media cap

### Reproduction
1. Coach edits a `rejected` report that already has 10 photos.
2. Attach one more photo, submit.

### Expected
A visible error saying the 10-photo limit was exceeded, and nothing changed.

### Actual
Silent no-op. The controller did `return back()->with('error', 'Total foto tidak boleh lebih dari 10.')`,
but `resources/views/coach/reports/edit.blade.php` renders only `$errors->all()` — it never reads
`session('error')`. So the coach saw the edit form again with no explanation, while the report had
**already** been flipped to `submitted` by the `$report->update()` call a few lines earlier.

### Root Cause
Wrong failure channel (`session('error')` on a view that only renders the validation bag), combined with
a mid-sequence `return` after the mutation had been applied — the same ordering flaw as BUG-012.

### Fix
Both cap checks now `throw ValidationException::withMessages([...])` inside the transaction. The message
reaches the bag the view actually renders, and the throw rolls back the status flip.

### Regression Test
`CoachReportAtomicityTest::exceeding_the_photo_cap_is_refused_visibly_and_changes_nothing` — asserts the
exact message on the `photos` key **and** that status, lesson material, photo count and attendance are
all unchanged.

### Status
VERIFIED

---

## ISSUE-012

### Severity
P3

### Module
Navigation

### Reproduction
Log in as `spv_coach`; look for a Dashboard link.

### Expected
Undecided — see below.

### Actual
`spv_coach` holds the `dashboard.view` capability and `GET /admin/dashboard` returns 200 for that role,
but `layouts/app.blade.php` renders a Dashboard link only for SuperAdmin and School PIC. Login lands
`spv_coach` on `admin.coaches.index`, so the dashboard is reachable only by typing the URL.

### Root Cause
Either the capability grant or the nav is the mistake; the brief's source documents do not say which.
`docs/audit/role-permission-map.md` leaves the SPV policy scope TBD.

### Fix
**None applied.** Adding a nav link is new UI surface (rule 2), and removing `dashboard.view` would
change an approved permission map without authority (rule 8). Not a data-exposure risk:
`dashboard.view` is held only by `spv_coach` and SuperAdmin, both of which are operational-global
(`accessibleSchoolIds()` returns `null`), so the dashboard's global counters are within their scope. No
school-scoped role (`school_pic`, `finance`) holds `dashboard.view`.

### Regression Test
n/a — no change made.

### Status
OPEN — needs a product decision.

---

## ISSUE-013

### Severity
P3 — repo hygiene

### Module
Project root / repository

### Reproduction
`ls` the project root.

### Expected
No ad-hoc debug scripts or database copies.

### Actual
```text
_dbcheck.php                                  (dumps users + password hash prefixes)
_logincheck.php                               (contains the demo credential admin@lrs.com / password)
_audit_db.php                                 (read-only integrity queries, written for this audit)
database/database.sqlite.bak-20260817         (pre-migration backup)
database/database.sqlite.bak-preseed-20260817 (pre-seed backup)
```

### Root Cause
Debug/QA artifacts from the Phase implementation work and from this audit.

### Fix
**Nothing deleted** — per the brief, problematic existing artifacts are documented first, not removed.
All five live outside `public/`, so they are not reachable over HTTP with the standard Laravel document
root. Recommended owner action: delete the three `_*.php` scripts (or move them under `docs/audit/`)
and delete or `.gitignore` the two `.bak-*` database copies once the migration is trusted.

### Regression Test
n/a.

### Status
OPEN — flagged for the owner.

---

## ISSUE-014

### Severity
P2 — a core feature (report photo/video attachment) cannot work, but the cause is environment
configuration, not code. Recorded as an issue because it is the trigger of BUG-012.

### Module
Configuration / Cloudinary

### Reproduction
```text
$ curl -u <api_key>:<api_secret> https://api.cloudinary.com/v1_1/<cloud_name>/ping
HTTP 401  {"error":{"message":"cloud_name mismatch"}}
```

Or through the application's own code path:

```text
CloudinaryHelper::upload($file, 'lrs/photos')
=> ['error' => ['message' => 'Invalid cloud_name mediaflows_2f82a1be-...']]
```

### Expected
The three Cloudinary credentials resolve to values Cloudinary accepts, so `CloudinaryHelper::upload()`
authenticates and returns a body containing `secure_url`.

### Actual — two successive states

**State 1 (during the audit, 2026-08-17 morning).** All three resolved to `""`. `.env` lines 62–64
declared the keys with **empty values**, so every upload posted to
`https://api.cloudinary.com/v1_1//auto/upload` (note the empty path segment) and was rejected. This is
the state that triggered BUG-012 and damaged reports 4 and 5.

**State 2 (current, after the owner filled `.env`).** All three are now non-empty and `blank()` is false
for each, so the *configuration* half is satisfied. The *values* are still not accepted:

| Key | Length | Shape verdict |
|---|---|---|
| `CLOUDINARY_CLOUD_NAME` | 47 | **Wrong.** Holds a MediaFlows-style identifier (`mediaflows_<uuid>`), not a Programmable Media cloud name. |
| `CLOUDINARY_API_KEY` | 15 | Plausible — 15 digits, matches Cloudinary's format. Cannot be confirmed until the cloud name is right. |
| `CLOUDINARY_API_SECRET` | 27 | Plausible — 27 chars, matches Cloudinary's format. Same caveat. |

Cloudinary answers the Admin API ping with `401 {"error":{"message":"cloud_name mismatch"}}` and the
upload endpoint with `Invalid cloud_name mediaflows_...`. A cloud name is a short public identifier
(typically 6–20 chars of `[a-z0-9_-]`, e.g. `dxk3zqp7v`) and is **not** a secret — it appears in every
delivery URL — so quoting it here exposes nothing.

### Root Cause
State 1: credentials were never filled in for this environment. State 2: the value pasted into
`CLOUDINARY_CLOUD_NAME` came from a different Cloudinary product (MediaFlows) than the Programmable
Media environment the API key belongs to. Ruled out as causes in both states:
`config/services.php:37-41` maps the keys correctly; **no OS environment variable shadows them**
(`getenv()`, `$_ENV` and `$_SERVER` all report unset when checked *before* Dotenv loads — an earlier
check that reported them "present" was invalid, because Laravel's own Dotenv populates the environment
during bootstrap); no config cache is in play (`bootstrap/cache/` contains only `packages.php` and
`services.php`, no `config.php`, and `php artisan config:clear` was run).

### Fix
**Not fixed — deliberately.** No credential was invented, guessed or written (rule 8: do not assume
unclear requirements; rule 14: do not expose credentials). A cloud name cannot be derived from the key
pair, so there is nothing here that is technically fixable without the owner.

Owner action:

1. Cloudinary Console → **Programmable Media → Dashboard → Product Environment Credentials**.
2. Copy the **Cloud name** field (short, not the MediaFlows id) into `CLOUDINARY_CLOUD_NAME`, and take
   the API key/secret from that same product environment so all three match.
3. `php artisan config:clear`.
4. Re-verify with the ping above — expect `200 {"status":"ok"}` — then submit one report with a photo.

Code-side mitigation that *was* applied: `storeMedia()` logs Cloudinary's own `error.message` (so
`cloud_name mismatch` lands in `storage/logs/laravel.log` verbatim) and names any *blank* key in the
message the coach sees, **without printing any value**. A wrong-but-present value is reported by
Cloudinary's message rather than guessed at.

### Regression Test
`CoachReportAtomicityTest::a_report_without_media_still_saves_with_its_attendance` proves reports
without media are unaffected; the two failure tests prove a failed upload is now harmless. Confirmed
against the **real** API on 2026-08-17: the live response shape
(`['error' => ['message' => ...]]`, no `secure_url`) is identical to the shape the test double injects,
so the tests exercise the real failure mode rather than an imagined one.

### Status
OPEN — owner action, one `.env` value. Detail: CONFIG-001 in
[data-integrity-report.md](data-integrity-report.md).

---

## ISSUE-015

### Severity
P3 — latent; zero impact today (`report_media` is empty project-wide).

### Module
Media lifecycle / Cloudinary

### Reproduction
Delete a photo from a report in the Coach edit screen, then look for the asset in Cloudinary.

### Expected
The remote asset is deleted with the row.

### Actual
The row is deleted; the remote file stays forever.

### Root Cause
`update()` calls `CloudinaryHelper::delete($media->cloudinary_public_id)`, but `report_media` has **no
`cloudinary_public_id` column** — `2026_03_01_215607_create_report_media_table.php` creates only
`report_id, type, path, original_name`, and the attribute is absent from `ReportMedia::$fillable`. The
expression is always `null`, so `!empty(null)` is false and the delete call never runs.

### Fix
**Not fixed.** Persisting the `public_id` requires an additive migration plus capturing the field from
the upload response — a schema and behaviour change beyond a stabilization audit (rule 2: no new
features; rule 4: no destructive migrations). Documented for the owner.

The BUG-012 fix did make the existing loop correct for the day the column is added: public ids are now
collected inside the transaction and the Cloudinary calls run only **after** commit, so a rollback can no
longer delete a remote file whose database row was restored.

### Regression Test
n/a — no behaviour to assert until the column exists.

### Status
OPEN — flagged for the owner. Detail: MEDIA-001 in [data-integrity-report.md](data-integrity-report.md).

---

## DEFERRED — PHASE 13

Static search for `waha`, `whatsapp`, `webhook`, `wa_session`, `twilio` (case-insensitive) across the
whole project excluding `vendor/`, `node_modules/`, `storage/` and `public/build/`:

```text
docs/**, NoteTambahan.md, implementation_planning.md, PRD v2   requirement text only
docs/stabilization/**                                          this audit's own reports
composer.lock                                                  framework dependency metadata
config/logging.php                                             LOG_SLACK_WEBHOOK_URL — Laravel default
```

**No WaHa or WhatsApp implementation exists in `app/`, `routes/`, `database/` or `resources/`.**
No requirement was implemented, stubbed, or partially wired.

| Requirement found in the source documents | Status |
|---|---|
| WhatsApp report delivery to parents / PIC | DEFERRED — PHASE 13 |
| WaHa session / gateway configuration | DEFERRED — PHASE 13 |
| Notification service + queue worker | DEFERRED — PHASE 13 |
| Inbound webhook endpoint | DEFERRED — PHASE 13 |
| "Which role may send notifications" (open in `docs/audit/role-permission-map.md`) | DEFERRED — PHASE 13, decision required before implementation |
