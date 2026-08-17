# Security Audit

**Audit date:** 2026-08-17
**Scope:** Phase 0–12. Authentication, capability authorization, multi-school isolation, export
authorization, role escalation, input trust boundaries.
**Method:** static review of every authorization decision point + 28 automated security tests
(`RoleIsolationTest` 15, `CrossSchoolSecurityTest` 8, `CoachReportAuthorizationTest` 5) exercised
through real HTTP requests.

---

## Authentication
**PASS**

| Check | Result | Evidence |
|---|---|---|
| Guests redirected to login on protected routes | PASS | `RoleIsolationTest::guests_are_redirected_to_login_for_protected_routes` (loops `attendance.index`, `admin.schools.index`, `pic.dashboard`, `coach.reports.index`) |
| Invalid credentials rejected | PASS | `RoleIsolationTest::invalid_credentials_are_rejected` |
| Logout clears the session | PASS | `RoleIsolationTest::logout_clears_the_session` |
| Every role has a reachable landing page | PASS | `RoleIsolationTest::every_role_can_login_and_land_on_a_reachable_page` — asserts redirect target **and** 200 on it, for all six roles |
| Passwords hashed | PASS | `bcrypt`/`Hash::make` only; no plaintext write path. Verified hash prefixes `$2y$` in the dev DB. |
| No credential in application code | PASS | Only `.env` / `config/`. See finding **F-3** for a debug script at the project root. |

Fixed here: **BUG-005** — `spv_coach` could authenticate but was trapped in a `login ↔ /` redirect loop.
An unmapped role now `abort(403)` with an actionable message instead of looping.

---

## Authorization
**PASS**

Model: capability-based, not role-string-based. `AuthorizationService::ROLE_PERMISSIONS` is the single
map; SuperAdmin is a wildcard. Enforced by three middleware aliases registered in `bootstrap/app.php`:
`permission:x` (`PermissionMiddleware`), `permission_any:a,b` (`PermissionAnyMiddleware`),
`role:x,y` (`RoleMiddleware`).

| Check | Result | Evidence |
|---|---|---|
| All 44 application routes carry an auth gate | PASS | `php artisan route:list --except-vendor` reviewed route by route; no duplicate and no shadowed path |
| Capability names on routes match the capability's meaning | PASS **after fix** | **BUG-002** — the global report console was gated on `reports.view`, a *Coach* capability. Re-gated on `reports.review`. |
| UI guards agree with route guards | PASS | `layouts/app.blade.php` nav guards read the same `AuthorizationService` capabilities as the routes |
| Authorization enforced in the backend, never only in the UI | PASS **after fix** | **BUG-003/BUG-004** — the Coach class dropdown was correctly filtered in Blade, but `store()` trusted the submitted `class_id`. Now resolved from `coach_assignments`. |
| Object-level check in addition to the route gate | PASS **after fix** | `Admin\ReportController::ensureSchoolAccess()` on `show`/`approve`/`reject`; `AuthorizationService::canAccessClass()` for class access |
| Every role's positive and negative surface asserted | PASS | `RoleIsolationTest` — superadmin, relation, spv_coach, coach, school_pic, finance |

---

## School Isolation
**PASS**

`AuthorizationService::accessibleSchoolIds()` returns `null` for operational-global roles (superadmin,
relation, spv_coach) and an **array** for restricted roles (school_pic, finance). The `null` vs `[]`
distinction is deliberate and load-bearing: a PIC with no plotting gets `[]` → `whereIn('school_id', [])`
→ zero rows. It must never degrade to "no filter = all schools".

Scope sources are merged by `User::assignedSchoolIds()` — the `school_user` pivot (multi-school
plotting) plus the legacy `users.school_id` column — so neither an old nor a new record shape can leave
an account unscoped.

| Check | Result | Evidence |
|---|---|---|
| PIC sees only the plotted school's attendance | PASS | `CrossSchoolSecurityTest::pic_only_sees_the_plotted_school_attendance` |
| PIC cannot open another school's report | PASS | `..::pic_cannot_open_a_report_of_another_school` |
| PIC cannot open another school's class | PASS | `..::pic_cannot_open_a_class_of_another_school` |
| Finance restricted to plotted schools | PASS | `..::finance_export_is_scoped_and_csv_shaped` |
| PIC/Finance see only `approved` reports | PASS (intended) | `AttendanceScopeService::scopeReports()` forces `status = approved` for both roles |
| Coach sees only their own reports | PASS | `scopeReports()` forces `coach_id = $user->id` |
| Students AJAX endpoint scoped | PASS | Covered by the class-access check; no unscoped student lookup route exists |

---

## Direct URL Access
**PASS**

Every negative case was exercised by requesting the URL directly while authenticated as the wrong role
or the wrong school — not by checking whether a link is rendered.

| Attempt | Expected | Result |
|---|---|---|
| Coach → `GET /admin/reports` | 403 | PASS (was 200 — BUG-002) |
| Coach → `GET /admin/reports/{other coach's report}` | 403 | PASS (was 200 — BUG-002) |
| Coach → `GET /admin/schools`, `/admin/classes`, `/admin/users` | 403 | PASS |
| School PIC → `GET /admin/schools` | 403 | PASS |
| School PIC → `GET /pic/reports/{report of another school}` | 403 | PASS |
| School PIC → `GET /classes/{class of another school}` | 403 | PASS |
| Finance → `GET /admin/users` | 403 | PASS |
| Relation / SPV Coach / Coach / PIC / Finance → `GET /admin/users` | 403 | PASS (`RoleIsolationTest` data provider, 5 roles) |
| SPV Coach → `GET /admin/schools`, `/admin/classes`, `/admin/programs` | 403 | PASS |
| Guest → any protected route | redirect to login | PASS |

---

## Query Parameter Manipulation
**PASS**

The audited anti-pattern — *user supplies `school_id` → query runs directly* — does not occur. In both
attendance and reports, the scope is applied to the builder **before** any request-supplied filter, so a
`?school_id=` can only ever narrow the result set:

```php
// AttendanceScopeService::query()   — scope first, via whereHas(scopeReports)
// then ->when($filters['school_id'] ...)  can only add a second constraint

// Admin\ReportController::index()
$accessibleSchoolIds = $this->authorization->accessibleSchoolIds($this->actingUser());
if ($accessibleSchoolIds !== null) {
    $query->whereIn('school_id', $accessibleSchoolIds);   // applied before $request->school_id
}
```

| Attempt | Expected | Result |
|---|---|---|
| PIC → `/attendance?school_id={other school}` | own school rows only (not other school's) | PASS — `..::pic_cannot_pivot_to_another_school_via_query_parameter` |
| PIC → `/attendance/export?school_id={other school}` | no foreign rows in CSV | PASS — `..::pic_export_cannot_leak_another_school` |
| Finance → `/attendance?school_id={unplotted school}` | empty | PASS |
| Filters themselves validated | yes | `AttendanceController::validatedFilters()` — `exists:schools,id`, `exists:classes,id`, `date`, `after_or_equal:date_from`, `in:` allow-lists for both status fields |

Also verified: **attendance array keys** are input too. `attendance[student_id] => status` validated the
values but not the keys, which allowed a foreign student to be attached to a report
(**BUG-004**, fixed).

---

## Export Authorization
**PASS**

| Check | Result | Evidence |
|---|---|---|
| Export requires a capability | PASS | `AttendanceController@export` → `abort_unless(allows('attendance.export') || allows('attendance.export_csv'))` |
| Export uses the same scoped builder as the table | PASS | Both call `AttendanceScopeService::query($user, $filters)` with the same validated `$filters`; the export cannot widen what the table shows |
| Export dataset matches the filtered table | PASS | `CrossSchoolSecurityTest::export_dataset_matches_the_filtered_table` |
| Export cannot leak another school | PASS | `..::pic_export_cannot_leak_another_school` |
| Export contains the rows it should | PASS | `..::pic_export_contains_the_plotted_school_rows` |
| CSV shape stable | PASS | Header `tanggal,sekolah,kelas,coach,siswa,status_absensi,status_laporan`; `Content-Type: text/csv; charset=UTF-8`; streamed via `streamDownload` + `chunkById(500)` so a large export cannot exhaust memory |
| Finance filtered export excludes non-matching students | PASS | `EndToEndFlowTest` — `attendance_status=sick` export contains `Bela`, asserts `Andi` **absent** |

---

## Role Escalation
**PASS**

| Vector | Result | Evidence |
|---|---|---|
| Non-SuperAdmin reaching User Management | PASS | 5 roles × `admin.users.index` → 403 (`RoleIsolationTest` data provider) |
| Self-promotion via `POST /admin/users` | PASS | The whole `users.*` group requires `users.manage`, held only by SuperAdmin (wildcard) |
| Role value injection | PASS | `Rule::in(User::roleKeys())` on both `store()` and `update()` — an arbitrary `role` string is rejected |
| Privilege inherited by omission | PASS | `allows()` is a strict allow-list: `in_array($permission, ROLE_PERMISSIONS[$user->role] ?? [], true)`. An unknown role gets `[]`, i.e. nothing — not everything. |
| Self-deletion / lockout | PASS | `UserController@destroy` refuses self-delete; SuperAdmin cannot be locked out by the report-FK guard because the guard only refuses accounts that authored reports |
| Coach escalating through assignment | PASS | `coaches.assign` requires the SPV/SuperAdmin capability; a Coach cannot assign themselves a class |

---

## Findings

### F-1 — Cross-role report leakage (P0) — FIXED
The global report review console was gated on `reports.view`, a per-owner **Coach** capability, so every
Coach could read every report of every school. Fixed in three layers (route capability, nav guard,
object-level school check + pre-filter). Regression test:
`CoachReportAuthorizationTest::coach_cannot_read_the_global_report_review_console`. Detail: BUG-002.

### F-2 — Untrusted input reached the database on two write paths (P0) — FIXED
`class_id` (BUG-003) and the `attendance` **array keys** (BUG-004) were trusted because the UI filtered
them. Both are now resolved/validated server-side before any write. This was the audit's most important
class of finding: the frontend was correct, so nothing looked wrong.

### F-3 — Demo credential in a root-level debug script (P3) — DOCUMENTED, NOT REMOVED
`_logincheck.php` contains `admin@lrs.com` / `password`, and `_dbcheck.php` prints password-hash
prefixes. Both are **outside `public/`**, so they are not reachable over HTTP with the standard Laravel
document root, and both credentials are the public demo seeds — no real secret is exposed. Left in place
per the brief (document before deleting); recommended for removal. Detail: ISSUE-013.

### F-4 — `APP_DEBUG=true` (informational)
Correct for a local environment and not a defect here. Flagged because Phase 13 will introduce a WaHa
API token: debug must be `false` in any environment holding that token, or a stack trace can print it.

### F-5 — `QUEUE_CONNECTION=sync` (informational, Phase 13 prerequisite)
Not a security defect today. With `sync`, a Phase-13 outbound WhatsApp call would run inside the request
cycle, so a slow or hostile gateway would stall user requests. Move to `database`/Redis with a worker
before implementing WaHa.

### F-6 — `spv_coach` attendance scope is broad (P3, open requirement)
`scopeReports()` scopes SPV Coach with `whereHas('schoolClass.coachAssignments')` — any class that has
at least one coach assignment, not only classes assigned to *their* coaches, and
`accessibleSchoolIds()` returns `null` for the role. That is consistent with SPV Coach being defined as
operational-global, but `docs/audit/role-permission-map.md` leaves the policy scope TBD. **Not changed**
(rule 8). Needs a product decision.

### F-7 — No residual hardcoded role checks
Static search for `is_admin`, `role == 'admin'`, `'admin'`, `TODO`, `FIXME`, `HACK`: no hits in
application code. `'admin'` survives only in `routes/web.php:71` (a URL prefix string, not a role) and
inside the two migrations that must reference the literal to migrate away from it — including the
`down()` path. Correctly left untouched (rule 9: no blind global replace).

### F-8 — Unhandled exception on the report write path (P1 integrity, secondary disclosure risk) — FIXED
Not an authorization hole, recorded here because it had two security-adjacent consequences.

`Coach\ReportController@store` read `$result['secure_url']` straight off the Cloudinary response. A
failed upload returns a body without that key, so the request died with
`ErrorException: Undefined array key "secure_url"`:

1. **Integrity.** There was no transaction, so the `reports` row had already committed while the
   attendance loop had not yet run — a `submitted` report with zero attendance, invisible to every
   scoped list and export. Two live records were damaged this way. This is the data-integrity half of
   the brief's P0 definition ("data corruption") landing at P1 severity because the loss is recoverable
   and the report text survives. Detail: BUG-012 / DATA-001.
2. **Disclosure.** With `APP_DEBUG=true` (F-4), an unhandled exception renders Laravel's debug page:
   stack frames, file paths and surrounding source. The frames here are the Cloudinary upload path, so
   with credentials configured a variable dump could put them on screen for whoever triggered the
   error — a Coach, i.e. the lowest-privilege authenticated role.

Fixed: the response is validated and converted into a `ValidationException` (a handled 422/redirect, no
debug page), the whole write runs inside `DB::transaction()`, and the log entry names any blank
credential by key **without** printing its value. Regression: `CoachReportAtomicityTest`, 5 tests.

The general lesson, and the reason this belongs in a security audit: an unhandled exception on a write
path is not only a crash — it is an uncontrolled exit that can leave the database in a state no
validation rule allows, and, with debug enabled, can narrate the internals to the user who caused it.

---

## Verdict

```text
Authentication                 PASS
Authorization                  PASS
School Isolation               PASS
Direct URL Access              PASS
Query Parameter Manipulation   PASS
Export Authorization           PASS
Role Escalation                PASS
```

**No open P0 or P1 security issue.** Two P0 authorization holes were found and closed with backend
enforcement; the residual findings are informational or awaiting a product decision.

One P1 **integrity** defect on the report write path (F-8) was also found and fixed. It does not change
any verdict above — no isolation or authorization boundary was crossed. The two records it damaged are no
longer in the database and integrity check 16 now returns 0, so it no longer holds the system verdict
back either. See [stabilization-report.md](stabilization-report.md) and
[data-integrity-report.md](data-integrity-report.md).

---

## Re-verification on live data (2026-08-17, post-audit)

Repeated after the database gained a coach and two assignments — one of which spans two schools, a shape
the original audit never exercised. Read-only, against `database/database.sqlite` rather than `:memory:`.

| Check | Result |
|---|---|
| Capability matrix for all 8 live accounts | PASS — only SuperAdmin holds `reports.review`; `coach` holds no `attendance.view`/`attendance.export`; Finance holds `attendance.export_csv` but not `attendance.export` |
| PIC and Finance see only their plotted school | PASS — both scoped to school 1, zero rows from school 2 |
| PIC and Finance forcing `?school_id=2` | PASS — 0 rows; the scope cannot be widened |
| Export / Finance CSV on real rows | PASS — header exact, no foreign-school row |
| Multi-school coach (coach 8 → school 1 + school 2) | PASS — a coach is scoped by `coach_id`, not by school, so spanning schools grants nothing extra |

### F-9 — `accessibleSchoolIds()` returns `[]` for a Coach (investigated, not a defect)

Worth recording because it looks alarming in isolation. A `coach` is not operational-global, so
`accessibleSchoolIds()` falls through to `assignedSchoolIds()`, which is empty for a coach — meaning a
coach passed to `AttendanceScopeService` would see **zero** rows, including their own.

Not reachable and not a hole:

1. The `coach` role holds neither `attendance.view` nor `attendance.export`, so `AttendanceController`
   aborts before the scope service is consulted.
2. A coach's own reports come from `Coach\ReportController@index`, which filters
   `where('coach_id', Auth::id())` and does not use the school scope at all. Verified live: coach 3 sees
   reports 1–3 with all 18 attendance rows.
3. The failure direction is **closed** (`whereIn('school_id', [])` → nothing), never open. This is the
   same `null` vs `[]` discipline that protects PIC and Finance.

No change made (rule 12: do not modify unrelated code). Recorded so a future change that grants `coach`
an `attendance.*` capability knows it must also decide the coach's school scope first.

### ISSUE-014 revisited — credential handling under a *wrong* value

The Cloudinary credentials are no longer blank, but the cloud name is wrong, so uploads are rejected with
`401 cloud_name mismatch`. Two security-relevant properties were re-confirmed under this new state:

- The failure is a handled `ValidationException`, not an unhandled exception — so F-8's disclosure vector
  (a debug page rendered to the lowest-privilege role, with `APP_DEBUG=true`) does not reopen.
- The log entry records Cloudinary's own `error.message` and names any *blank* config key, but **never
  prints a credential value**. Verified by reading the code path and by running a real upload probe.

Credential values were never printed during this verification — only measured (length, charset, whether
they embed one another) and tested through Cloudinary's own ping endpoint.
