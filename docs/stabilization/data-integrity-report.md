# Data Integrity Report

**Audit date:** 2026-08-17
**Re-verified:** 2026-08-17, after the owner acted on the two blockers
**Database:** `database/database.sqlite` (live development database)
**Method:** 16 SQL integrity queries run directly against the live database via PDO, read-only.
**Backups present:** `database/database.sqlite.bak-20260817`,
`database/database.sqlite.bak-preseed-20260817`

Per the brief (§39), this audit documented problematic existing data instead of deleting it. **No row was
removed, repaired or rewritten by the audit** — in either pass.

---

## Live snapshot

| Table | Rows (audit) | Rows (re-verify) | Change |
|---|---|---|---|
| `schools` | 2 | 2 | — |
| `classes` | 3 | 3 | — |
| `students` | 6 | 6 | — |
| `users` | 7 | **8** | +1 coach (`gilbran@lrs.com`) |
| `coach_classes` | 2 | **4** | +2 assignments |
| `reports` | 5 | **3** | **−2 (ids 4 and 5 removed — see DATA-001)** |
| `report_attendances` | 18 | 18 | — (3 reports × 6 students) |
| `report_media` | 0 | 0 | — |
| `programs` | 1 | 1 | — |
| `program_classes` | 1 | 1 | — |
| `school_user` | 2 | 2 | — |
| `migrations` | 13 | 13 | all Ran, 0 Pending |

---

## Integrity checks

Checks 1–15 are structural (orphans, scope mismatch, duplicates). Check 16 was **added during this
audit** after the report/attendance defect was found — it is the check that would have caught it.

| # | Check | Rows (audit) | Rows (re-verify) | Result |
|---|---|---|---|---|
| 01 | Class with missing school | 0 | 0 | PASS |
| 02 | Student with missing class | 0 | 0 | PASS |
| 03 | Report with missing school | 0 | 0 | PASS |
| 04 | Report with missing class | 0 | 0 | PASS |
| 05 | Report with missing coach | 0 | 0 | PASS |
| 06 | Report `school_id` ≠ its class's `school_id` | 0 | 0 | PASS |
| 07 | Attendance with missing report | 0 | 0 | PASS |
| 08 | Attendance with missing student | 0 | 0 | PASS |
| 09 | Attendance student outside the report's class | 0 | 0 | PASS |
| 10 | Duplicate attendance per (report, student) | 0 | 0 | PASS |
| 11 | `coach_classes` row with missing coach or class | 0 | 0 | PASS |
| 12 | Duplicate coach assignment | 0 | 0 | PASS |
| 13 | `school_user` dangling pivot | 0 | 0 | PASS |
| 14 | School-scoped account (`school_pic`/`finance`) with no scope at all | 0 | 0 | PASS |
| 15 | `program_classes` dangling pivot | 0 | 0 | PASS |
| **16** | **Report with ZERO attendance rows** | **2 — FAIL** | **0** | **PASS** |

```text
FAILING CHECKS: 0 of 16
```

Check 07 confirms the removal of reports 4 and 5 left **no orphaned attendance rows** — the
`report_attendances.report_id` cascade behaved correctly, and the surviving 18 rows are exactly
3 reports × 6 students.

Check 09 is the one that proves the BUG-004 fix on live data: no attendance row references a student
outside its report's class.

Check 14 is load-bearing for isolation: a `school_pic` or `finance` account with no scope must exist as
`[]` (sees nothing), never as "unscoped = sees everything". Zero such accounts exist, and
`accessibleSchoolIds()` returns `[]` rather than `null` for them by design.

---

## Per-report detail (re-verified)

```text
report 1 | approved | 2026-08-13 | created 10:39:03 | coach 3 | school 1 | class 1 | att=6 med=0 | students_in_class=6
report 2 | approved | 2026-08-13 | created 10:48:20 | coach 3 | school 1 | class 1 | att=6 med=0 | students_in_class=6
report 3 | approved | 2026-08-13 | created 11:00:12 | coach 3 | school 1 | class 1 | att=6 med=0 | students_in_class=6
```

Attendance value distribution: report 1 → 6 present; report 2 → 5 present + 1 absent; report 3 → 6
present. Every report is complete (6 of 6 students recorded).

Accident notes are present and intact on all three, including the multi-line case that the newline
rendering fix from the most recent commit exists for:

```text
report 3 notes = "WIFI LEMOT\nANAK BERANTEM"
```

---

## DATA-001 — Two reports stored without any attendance — RESOLVED (with a caveat)

**Data Integrity Issue (as found)**
`reports` id **4** and id **5** existed with `status = submitted` but **zero** `report_attendances`
rows, even though `attendance` is `required|array` on the submit form and their class has 6 students.
A "submitted" report with no attendance is invisible in every attendance list and every CSV export
(both join through `report_attendances`), so the lesson looked unreported to PIC, Finance and SPV Coach.

**Cause**
`Coach\ReportController@store` wrote the report, then the media, then the attendance — **without a
transaction**. `CloudinaryHelper::upload()` returns the decoded JSON body, so a failed upload comes back
without a `secure_url` key; `$result['secure_url']` then raised
`ErrorException: Undefined array key "secure_url"`, aborting the request **after** `Report::create()`
had already committed and **before** the attendance loop ran.

Proven by matching the timestamps in `storage/logs/laravel.log` to the two rows:

```text
report 4 created 2026-08-17 07:17:27  →  [2026-08-17 07:17:31] local.ERROR: Undefined array key "secure_url" {"userId":3, ...}
report 5 created 2026-08-17 07:19:18  →  [2026-08-17 07:19:25] local.ERROR: Undefined array key "secure_url" {"userId":3, ...}
```

The upload failed because the Cloudinary credentials were blank at the time (CONFIG-001, state 1).

**Current state — check 16 returns 0**

The integrity failure is cleared: `reports` holds ids 1–3 only, every one with complete attendance, and
no orphaned rows were left behind (check 07 = 0).

The rows were **removed**, not repaired. This is worth recording precisely, because the outcome differs
from the recommended path:

| | Recommended path | What happened |
|---|---|---|
| Method | SuperAdmin **Reject** → coach re-opens in **Edit** → re-enters attendance for 6 students → resubmit | Rows deleted outright |
| Report text / accident notes | Preserved | **Lost** |
| Integrity check 16 | 0 | 0 |
| Orphans | none | none |

Two observations, offered as fact rather than objection — this is the owner's database and the call was
theirs to make:

1. **No application route can delete a report.** `php artisan route:list --except-vendor` contains no
   report `destroy` action for any role, and a search of `routes/web.php` for a report delete finds
   nothing. The deletion therefore happened outside the application, against the database directly.
2. **The lost content is not recoverable from the backups in the repo.** Both
   `database.sqlite.bak-20260817` and `database.sqlite.bak-preseed-20260817` were taken *before* reports
   4 and 5 were created (07:17 and 07:19 on 2026-08-17) — each backup contains only reports 1–3. The
   `lesson_material`, `activity_summary` and `notes` written by coach `renaldy` for those two 2026-08-17
   lessons are gone.

Nothing further is outstanding for DATA-001. The recurrence is prevented in code (BUG-012: transaction +
response validation) and is covered by `tests/Feature/CoachReportAtomicityTest.php`, 5 tests.

---

## CONFIG-001 — Cloudinary credentials present but rejected

Not a data defect and not a code defect — an environment gap. It was the trigger for DATA-001 and it is
**still open**, in a changed form.

| Key | State at audit | State now | Length | Verdict |
|---|---|---|---|---|
| `CLOUDINARY_CLOUD_NAME` | empty | non-empty | 47 | **Wrong value** — a MediaFlows identifier (`mediaflows_<uuid>`), not a Programmable Media cloud name |
| `CLOUDINARY_API_KEY` | empty | non-empty | 15 | Plausible (15 digits); unconfirmable until the cloud name is right |
| `CLOUDINARY_API_SECRET` | empty | non-empty | 27 | Plausible (27 chars); same caveat |

Verified live on 2026-08-17, credential values never printed:

```text
php artisan config:clear                             → Configuration cache cleared
config('services.cloudinary.*')                      → all three strings, blank() = false
OS env shadow (checked BEFORE Dotenv loads)          → unset for all three
bootstrap/cache/config.php                           → absent
GET api.cloudinary.com/v1_1/<cloud_name>/ping        → HTTP 401 {"error":{"message":"cloud_name mismatch"}}
CloudinaryHelper::upload(<1x1 png>, 'lrs/_probe')    → ['error' => ['message' => 'Invalid cloud_name mediaflows_2f82a1be-...']]
```

A cloud name is a short public identifier (typically 6–20 chars of `[a-z0-9_-]`) that appears in every
Cloudinary delivery URL — it is not a secret, so naming it here exposes nothing. The API key and secret
were never printed, only measured.

**Consequence today:** every report submitted with a photo or video is refused, cleanly —
`Foto "…" gagal diunggah. Laporan TIDAK tersimpan — silakan coba kirim ulang.` — with Cloudinary's own
`cloud_name mismatch` written to `storage/logs/laravel.log` for diagnosis. Reports without media save
normally, proved by
`CoachReportAtomicityTest::a_report_without_media_still_saves_with_its_attendance`.

**Owner action:** Cloudinary Console → Programmable Media → Dashboard → Product Environment Credentials
→ copy the **Cloud name** field, and take the API key/secret from that same product environment so all
three belong together. Then `php artisan config:clear` and re-run the ping; expect
`200 {"status":"ok"}`.

No credential was invented, guessed or written by this audit (rule 8, rule 14). A cloud name cannot be
derived from a key pair, so there is nothing technically fixable here without the owner.

**Silver lining, verified:** the real API's failure response has exactly the shape
`CoachReportAtomicityTest` injects through its test double — an array with `error.message` and no
`secure_url`. The 5 atomicity tests therefore exercise the genuine failure mode, and the misconfiguration
that once destroyed two reports now produces a validation error and no write at all.

---

## MEDIA-001 — Deleted media is never removed from Cloudinary

Latent, no data corruption, no current impact (`report_media` is empty).

`Coach\ReportController@update` calls `CloudinaryHelper::delete($media->cloudinary_public_id)`, but the
`report_media` table has **no `cloudinary_public_id` column**
(`2026_03_01_215607_create_report_media_table.php` creates `report_id, type, path, original_name` only)
and the attribute is not in `ReportMedia::$fillable`. The expression is therefore always `null`,
`!empty(null)` is false, and the remote asset is never deleted — deleting a photo in the app would leave
the file in Cloudinary forever.

**Not fixed.** Storing the `public_id` means an additive migration plus capturing the field from the
upload response, i.e. a schema + behaviour change beyond a stabilization audit (rule 2, rule 4).
Documented for the owner. The atomicity fix did leave the remote-delete loop correct for the day the
column exists: public ids are collected inside the transaction and the Cloudinary calls run only
**after** commit, so a rollback can no longer delete a remote file whose row was restored.

This becomes reachable the moment CONFIG-001 is fixed and media starts uploading successfully.

---

## New data shapes introduced since the audit

The owner added a coach and two assignments between the audit and this re-verification. Both were
re-checked because they are shapes the original audit never exercised:

| Observation | Verdict |
|---|---|
| `users` 7 → 8: new `coach` account `gilbran@lrs.com` (id 8) | Fine. Coaches are not school-scoped; check 14 only governs `school_pic`/`finance`. |
| Class 2 (`Grade 3-5 Coding`) now has **two** coaches assigned (ids 7 and 8) | Fine and not a duplicate — check 12 counts duplicate `(coach_id, class_id)` pairs, and these are distinct coaches on one class. |
| Coach 8 spans **two schools** — class 2 (school 1) and class 3 (school 2) | Fine by design. Coach scope is per-assignment, not per-school: `assignedClassOrFail()` resolves from `coach_classes`, and `scopeReports()` scopes a coach by `coach_id = $user->id` rather than by school, so a multi-school coach cannot see anything beyond their own reports. |

---

## Verdict

```text
Structural integrity (checks 01–15)   PASS   — 0 rows on every query
Report/attendance completeness (16)   PASS   — 0 rows (was 2)
Orphans after row removal             PASS   — cascade clean, 18 = 3 × 6
```

**16 of 16 checks pass.** No orphan, no cross-school mismatch, no duplicate, no unscoped account, no
report without attendance. One environment item remains open (CONFIG-001) and one latent item
(MEDIA-001); neither is a data-integrity failure.
