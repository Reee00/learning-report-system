# Learning Report System - Current Project Context

Status audit: 2026-09-03. Root codebase is the source of truth. Statements about runtime values that are not present in the repository are marked `Need Verification`.

## Project Overview
LRS is a Laravel web application for managing schools, classes, programs, students, attendance, coach reports, report review, and report media.

## Current Tech Stack
- PHP `^8.4`; Laravel `^12.0`; PHPUnit `^11.5.3`.
- Blade, Bootstrap 5.3 and Bootstrap Icons via CDN, with Vite/package scripts present.
- MySQL is the intended production database. Repository config supports MySQL and other Laravel drivers and defaults to SQLite when `DB_CONNECTION` is absent. Active environment value: `Need Verification` because `.env` is not in the repository.
- FastExcel handles student import. Attendance export supports CSV and PDF. Dompdf handles PDF generation.
- Cloudinary dependency/config/helper remain for legacy compatibility, but new report media is written through local Laravel storage.

## Architecture
Standard Laravel MVC with Blade views, Eloquent models, middleware, and service classes. `AuthorizationService` centralizes role capabilities and class/school access checks. `AttendanceScopeService` centralizes attendance/report filtering. `MediaStorageService` abstracts report media storage. There is no separate API application or `routes/api.php`.

## Database
Fourteen migration files currently exist. Main tables are `users`, `schools`, `classes`, `students`, `coach_classes`, `reports`, `report_attendances`, `report_media`, `programs`, `program_classes`, `school_user`, and Laravel sessions. The initial user migration contains legacy role values, then a later migration changes `admin` to `relation` and expands runtime roles. This historical detail must not be interpreted as a valid current `admin` role.

## Authentication & Authorization
Login/logout are handled by `LoginController` and Laravel session authentication. Routes use `auth`, `role`, `permission`, or `permission_any` middleware. `AuthorizationService` grants SuperAdmin wildcard capability and explicit capabilities to other roles. Backend checks also enforce school/class scope; UI visibility is not the security boundary.

## Roles & Permissions
Runtime roles are exactly:
- `superadmin` / SuperAdmin: wildcard capability and global access.
- `relation` / Relation: school, class, program, student management; attendance view/export; report browse/review.
- `spv_coach` / SPV Coach: dashboard, coach CRUD and assignment/reassignment; attendance view/export; report browse.
- `coach` / Coach: own reports create/view/update; students in assigned classes; `accident_notes.view` capability. There is no separate accident-notes table.
- `school_pic` / PIC DK SCHOOL: plotted-school students/reports and approved attendance; attendance export.
- `teacher_school` / TEACHER SCHOOL: plotted-school report browse and approved attendance/export.
- `finance` / Finance: plotted-school approved attendance and CSV export.

`admin` and `PIC Sekolah` are not current runtime role names. The URL/controller namespace `admin/*` is a compatibility namespace used by Relation and other users according to capability.

## Business Logic
Relation and SuperAdmin manage operational master data. SPV Coach manages Coach accounts and class assignments. Coach access to classes is based on `coach_classes`. School-scoped users are assigned through `school_user`; legacy `users.school_id` is also included for compatibility. Scope is enforced in services/controllers.

## Master Data
Implemented entities and operations include School, SchoolClass, Program, ProgramClass, User/Coach, Student, and CoachClass assignment. Student import accepts XLSX, XLS, and CSV through FastExcel. Student template is streamed CSV.

## Attendance
Coach submits student attendance as part of a report. Statuses are `present`, `absent`, `sick`, and `permission`. Attendance queries can filter school, class, date range, attendance status, and report status. Relation, SuperAdmin and SPV Coach have operational-global scope. Coach sees own reports. PIC, TEACHER SCHOOL, and Finance see only plotted schools and approved reports. PIC, TEACHER SCHOOL, and Finance export according to their permissions; Finance has CSV export capability only.

## Coach Report
Statuses are `draft`, `submitted`, `approved`, and `rejected`. Workflow: Coach creates and submits -> Relation or SuperAdmin reviews -> approve or reject. Reject requires `admin_notes`; Coach may edit rejected/draft reports and submit again. Approval records `approved_by` and `approved_at`. SPV Coach can browse but cannot approve/reject.

## Media Storage
New files use `MediaStorageService` and the configured `filesystems.report_media_disk`, default `report_media`, rooted at `storage/app/report-media`. Files are outside the public symlink and served through authorized `/media/{media}`. MySQL stores `report_media` metadata/reference (`path`, type, original name, disk, file size), not binary media. HTTP paths are recognized as legacy external URLs for compatibility. Cloudinary is not the active new-upload storage. `media:migrate-cloudinary` exists for legacy URL migration; verify operational use before running it.

## Export
`/attendance/export` supports CSV and PDF. CSV is a school/class/student matrix with date columns. The current exporter loads the query with `get()`; documentation must not claim chunked streaming. Student import is separate from attendance export.

## UI/UX
The main Blade layout uses a left sidebar application layout, top bar, mobile drawer/overlay, Bootstrap grid, responsive tables, and CSS media queries. Responsive/mobile improvements are implemented, but the codebase does not establish PWA support or a claim of full responsive coverage. Finance, TEACHER SCHOOL, and Relation reuse shared views; there are no separate role-specific portals for each.

## Routes/Modules
Public/auth: `/`, `/login`, `/logout`. Attendance: `/attendance`, `/attendance/export`. Students: `/classes/{class}/students`, import/delete/template, and `/api/classes/{class}/students`. Coach: `/coach/reports*`, `/coach/students`. Compatibility admin namespace: `/admin/dashboard`, users, reports, schools, classes, programs, coaches and assignments. School PIC: `/pic/dashboard`, `/pic/reports/{report}`. Authorized media: `/media/{media}`. Laravel health route `/up` is configured in bootstrap.

## Important Relationships
School has many SchoolClass; SchoolClass has many Student. User has many CoachClass and Reports. CoachClass joins User and SchoolClass. Program and SchoolClass are many-to-many through ProgramClass. User and School are many-to-many through SchoolUser (`school_user`). Report belongs to Coach, School, and SchoolClass and has many ReportAttendance and ReportMedia. Restrictive report foreign keys and cascading child relationships are defined by migrations.

## Current Environment
Repository `.env` is absent, so active DB host, credentials, `APP_ENV`, filesystem override, and service credentials are `Need Verification`. `config/filesystems.php` defaults to local private report storage. `composer setup` creates `.env`, migrates, installs NPM dependencies, and runs the current no-op `npm run build`. Docker currently declares PHP 8.3 while Composer requires PHP 8.4; deployment compatibility is `Need Verification`.

## Implemented Features
Authentication; seven-role RBAC; school plotting; master data CRUD; Coach assignment; student CRUD/import/template; report draft/edit/submit; Relation/SuperAdmin review; rejection/resubmission; attendance scope and CSV/PDF export; private local report media; authorized media serving; AJAX student list; responsive sidebar layout.

## Known Limitations
- Active `.env` and deployed database/storage values cannot be verified from the repository.
- Legacy Cloudinary helper, config, dependency, and URL compatibility remain; no active new-upload path uses Cloudinary.
- No separate permissions/roles tables or Policy classes; permissions are code-defined.
- Attendance export materializes the result with `get()`, so large exports may use substantial memory.
- Docker PHP version conflicts with Composer's PHP requirement until deployment is verified.
- No PWA claim and no separate Finance/Teacher UI.

## Planned / Not Implemented
Telegram integration is not implemented. A standalone Accident Notes module/entity is not implemented; the Coach capability currently relates to report notes/partial only. Any other roadmap item is `Need Verification` unless represented in code.

## Development Rules
Do not treat legacy migrations, Cloudinary compatibility, or the `admin/*` namespace as current role/storage architecture. Enforce authorization through middleware and `AuthorizationService`; preserve school/class scopes server-side. Keep media private and store metadata/reference in the database. Do not commit `.env` or credentials. Run the documented tests before release and verify deployment PHP/database/storage configuration.
