# Implementation Notes

## Phase 2 — Admin to Relation

Status: implemented; permission refinement continues in Phase 3.

### Summary

- Existing role value `admin` is migrated to `relation`.
- New `superadmin` role is available for full-access compatibility routes.
- Existing `/admin` URL and `admin.*` route names are preserved temporarily.
- Login redirect and navigation recognize Relation and SuperAdmin; Relation lands on School Management while SuperAdmin lands on the full dashboard.
- Role migration changes the users role column from a fixed enum to an extensible string without deleting user data.

### Files Changed

- `database/migrations/2026_08_14_000000_migrate_admin_role_to_relation_and_expand_roles.php`
- `app/Models/User.php`
- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Controllers/StudentController.php`
- `app/Http/Middleware/RoleMiddleware.php`
- `routes/web.php`
- `database/seeders/DatabaseSeeder.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/auth/login.blade.php`
- `README.md`
- `DEVELOPER_GUIDE.md`
- `tests/Feature/ExampleTest.php`
- `tests/Feature/RoleRedirectTest.php`

### Database Changes

The new migration:

1. changes `users.role` to an extensible string column;
2. updates every existing `admin` record to `relation`;
3. guards rollback when expanded roles are already in use.

No user or report rows are deleted.

### Routes and Authorization

The technical `admin.*` route names remain as a compatibility layer. Capability restrictions are now enforced through the Phase 3 permission middleware and AuthorizationService.

The Student controller now recognizes both Relation and SuperAdmin for the existing administrative class scope.

### Tests

Added coverage for:

- guest root redirect to login;
- Relation login redirect;
- SuperAdmin login redirect.

PHPUnit execution remains pending because PHP CLI is not available in the current environment.

### Known Risks / Follow-up

- Relation temporarily shares the compatibility route group with SuperAdmin until Phase 3 authorization is implemented.
- Existing controller namespaces and route names still contain `Admin` by design; they are technical compatibility names, not role values.
- Multi-school PIC plotting and new operational roles remain later phases.

## Phase 3 — Authorization & Permission

Status: implemented; domain-specific modules will consume the same authorization layer in later phases.

### Summary

- Added `AuthorizationService` with centralized role-to-permission mapping.
- Added `permission` middleware alias.
- Protected current routes by capability rather than one broad admin role.
- SuperAdmin has wildcard access.
- Relation has operational School, Student, Program Kelas, Program, and attendance export capabilities.
- User management and report review are SuperAdmin-only at this stage.
- Coach assignment capabilities are mapped for the future `spv_coach` role.
- Finance CSV capabilities are mapped for the future `finance` role.
- Student class access and the AJAX student endpoint now enforce backend class scope.

### Tests

Added unit coverage for SuperAdmin, Relation, and Finance permission behavior. Runtime PHPUnit execution remains pending because PHP CLI is unavailable.

### Known Follow-up

- Phase 4–11 will attach the mapped permissions to new School, Student, Program, Coach, plotting, attendance, and Finance features.
- PIC currently uses the existing single `users.school_id` scope until the Phase 9 `school_user` pivot is implemented.

## Phase 4 - School Management

Status: implemented; School Management is ready for the current Relation and SuperAdmin workflow.

### Summary

- Relation can view and create School records through the existing `/admin/schools` compatibility route.
- SuperAdmin retains wildcard access, including update and delete operations.
- School controller methods now enforce the same permissions at the controller boundary in addition to route middleware.
- School input is persisted only from validated fields and validation errors are shown in the School Management UI.
- Edit and delete controls are hidden for roles that do not have the corresponding permission; Relation therefore has a read/create-only interface.

### Files Changed

- `app/Http/Controllers/Admin/SchoolController.php`
- `resources/views/admin/master/schools.blade.php`
- `tests/Feature/SchoolManagementTest.php`

### Database Changes

No migration was required. The existing `schools` table already supports the current School Management scope: name, address, PIC display name, and timestamps. PIC-to-multiple-school assignment remains a later pivot-table concern.

### Tests

Added feature coverage for Relation and SuperAdmin access, Relation school creation, required-name validation, and denial of Coach plus unauthorized Relation update/delete requests. PHPUnit execution remains pending because PHP CLI is unavailable in the current environment.

### Known Follow-up

- School PIC assignment remains represented by the existing `users.school_id` field until the Phase 9 `school_user` pivot is implemented.
- School update/delete permissions are intentionally reserved for SuperAdmin until a later role/permission decision expands them.

## Phase 5 - Student Management

Status: implemented; Student Management now supports Relation input with school association through the selected class.

### Summary

- Reused the existing `students` table and `Student` model; no new Student table was introduced.
- Added the `School -> students` relation through `classes`, preserving the existing data model where a student belongs to a class and a class belongs to a school.
- Relation can create students in an authorized class and invalid student names are rejected by validation.
- Student routes continue to enforce both capability permission and class scope authorization at the backend.
- Student UI now hides create/import controls and delete actions when the current role lacks the corresponding permission.

### Files Changed

- `app/Models/School.php`
- `app/Http/Controllers/StudentController.php`
- `resources/views/students/index.blade.php`
- `tests/Feature/StudentManagementTest.php`

### Database Changes

No migration was required. Student-to-school association is derived through `students.class_id -> classes.school_id`, which matches the existing schema and avoids duplicating `school_id` on students.

### Tests

Added feature coverage for Relation student creation and school association, required-name validation, users without student permission, and coaches without class assignment.

### Known Follow-up

- Student import remains available to roles with `students.create`; further import-row validation can be expanded if the spreadsheet format gains more fields.
- School-specific multi-school PIC scope remains a later Phase 9 pivot concern.

## Phase 6 - Program Kelas

Status: implemented; Program Kelas uses the existing `SchoolClass` entity and `classes` table.

### Summary

- Reused `SchoolClass` rather than introducing a duplicate Program Class table.
- Program Kelas is associated with a School through `classes.school_id` and remains the parent entity for Student records.
- Relation can view and create Program Kelas records with validated School association and name fields.
- SuperAdmin retains wildcard access; delete remains restricted because Relation has no `program_classes.delete` permission.
- Class controller methods enforce permissions at both route and controller boundaries.
- The Program Kelas UI now has permission-aware actions, validation feedback, pagination numbering, and a single consistent action column.

### Files Changed

- `app/Http/Controllers/Admin/ClassController.php`
- `resources/views/admin/master/classes.blade.php`

### Database Changes

No migration was required. The existing `classes` table already models the required relationship to School and supports the existing Student and Coach assignment flows.

### Tests

Automated tests are intentionally deferred until the end of the remaining implementation phases, as requested. Phase 6 acceptance scenarios will be included in the final test pass.

### Known Follow-up

- A separate reusable Program entity and program-specific attributes remain in Phase 8; this phase only manages the existing SchoolClass/Program Kelas entity.
- Delete and update capabilities can be expanded later if the role-permission decision changes.

## Phase 7 - Coach Management & Assignment

Status: implemented; Coach assignment uses the existing `coach_classes` relationship to `SchoolClass`.

### Summary

- Reused `User` with role `coach` for Coach accounts and `CoachClass` for class assignments.
- SPV Coach and SuperAdmin can view Coach records according to the centralized permission map.
- Added Coach creation with password confirmation and unique email validation.
- Added Coach profile update for name and email without allowing the role to be changed through this flow.
- Assignment validates the target Coach, target Program Kelas, School relationship, and duplicate assignment.
- Reassignment/removal validates that the assignment belongs to the Coach in the route before deleting it.
- Added controller-level permission checks in addition to route middleware and permission-aware assignment UI controls.
- Corrected `User::isRelation()` so the existing authorization scope call works without a required argument.

### Files Changed

- `app/Http/Controllers/Admin/CoachController.php`
- `app/Models/User.php`
- `routes/web.php`
- `resources/views/admin/master/coaches.blade.php`
- `resources/views/admin/master/coach_show.blade.php`

### Database Changes

No migration was required. The existing `coach_classes` table already models the required Coach-to-Program-Kelas assignment and has a unique `(coach_id, class_id)` constraint.

### Tests

Automated tests are intentionally deferred until the end of the remaining implementation phases. Phase 7 acceptance scenarios will be included in the final regression pass.

### Known Follow-up

- Coach password reset remains outside this Phase 7 flow; existing account-management reset functionality remains separate.
- Coach assignment is currently class-based, matching the existing schema and the confirmed architecture audit.

## Phase 8 - Program Input

Status: implemented; Program is now a reusable entity associated to one or more existing SchoolClass records through ProgramClass.

### Summary

- Audit confirmed there was no existing Program model, table, controller, route, or view to reuse.
- Added reusable `Program` entity with name, optional code, optional description, status, and timestamps.
- Added `ProgramClass` association entity between `Program` and existing `SchoolClass`.
- A single Program can be associated with multiple classes and schools; a different Program creates a separate Program row and association set.
- Relation and SuperAdmin can view and create Programs through the new `admin.programs.*` routes.
- Program creation requires at least one valid Program Kelas association and uses a transaction so the Program and associations are stored atomically.
- Coach is not granted Program input permission; no Coach Program route or permission was added.

### Files Changed

- `app/Models/Program.php`
- `app/Models/ProgramClass.php`
- `app/Models/SchoolClass.php`
- `app/Http/Controllers/Admin/ProgramController.php`
- `database/migrations/2026_08_17_000000_create_programs_table.php`
- `database/migrations/2026_08_17_000001_create_program_classes_table.php`
- `routes/web.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/admin/master/programs.blade.php`

### Database Changes

Added `programs` and `program_classes`. `program_classes` has foreign keys to `programs` and `classes`, cascade deletion, timestamps, and a unique `(program_id, class_id)` constraint.

### Tests

Automated tests are intentionally deferred until the end of the remaining implementation phases. Phase 8 acceptance scenarios will be included in the final regression pass.

### Known Follow-up

- Program update/delete permissions remain unimplemented because the current confirmed permission map only grants Relation `programs.view/create`.
- Program-to-Coach remains indirect through the existing Coach-to-SchoolClass assignment; no direct Coach-to-Program relationship was assumed.

## Phase 9 - School Plotting & PIC

Status: implemented; PIC school scope now supports multiple schools through `school_user`.

### Summary

- Added the `school_user` many-to-many pivot between User and School.
- Existing non-null `users.school_id` assignments are copied into the pivot during migration.
- `User::assignedSchoolIds()` combines pivot assignments with the legacy column for backward compatibility.
- SuperAdmin account management now supports selecting multiple schools for School PIC creation and update.
- PIC dashboard and class/report access use all assigned school IDs rather than a single school.
- Authorization class scope uses the multi-school assignment instead of trusting only the legacy `school_id` value.

### Files Changed

- `database/migrations/2026_08_17_000002_create_school_user_table.php`
- `app/Models/User.php`
- `app/Models/School.php`
- `app/Services/AuthorizationService.php`
- `app/Http/Controllers/Admin/UserController.php`
- `app/Http/Controllers/SchoolPic/DashboardController.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/school_pic/dashboard.blade.php`

### Database Changes

Added `school_user` with foreign keys, timestamps, and a unique `(school_id, user_id)` constraint. Legacy `users.school_id` is retained for compatibility and stores the first assigned school for existing code paths.

## Phase 10 - Attendance Scope

Status: implemented; attendance queries now apply role and school scope in the backend.

### Summary

- Added `AttendanceScopeService` as the shared query boundary for attendance listing and export.
- SuperAdmin and Relation use operational-global scope.
- SPV Coach is limited to reports from classes with Coach assignments.
- School PIC and Finance are limited to assigned schools; their report scope is approved attendance.
- School and class filters are applied after authorization scope, so frontend-hidden options cannot broaden access.
- Added Attendance listing route and UI with school, class, date, and attendance-status filters.
- Added permission-aware navigation for Relation, SuperAdmin, SPV Coach, School PIC, and Finance.

### Files Changed

- `app/Services/AuthorizationService.php`
- `app/Services/AttendanceScopeService.php`
- `app/Http/Controllers/AttendanceController.php`
- `resources/views/attendance/index.blade.php`
- `routes/web.php`
- `bootstrap/app.php`
- `app/Http/Middleware/PermissionAnyMiddleware.php`

## Phase 10.A - Attendance Export

Status: implemented; export reuses the same authorized AttendanceScopeService query.

### Summary

- Added `AttendanceExportService` with streamed CSV output.
- Relation, SPV Coach, School PIC, and SuperAdmin use `attendance.export`.
- Finance uses `attendance.export_csv`; the shared export route accepts either permission.
- Export applies the same backend school/class/date/status filters as the Attendance listing.
- School PIC dashboard now exposes the scoped Attendance CSV export.

### Files Changed

- `app/Services/AttendanceExportService.php`
- `app/Http/Controllers/AttendanceController.php`
- `resources/views/school_pic/dashboard.blade.php`

### Tests

Automated tests are intentionally deferred until the end of the remaining implementation phases. Phase 9, 10, and 10.A acceptance scenarios will be included in the final regression pass.

### Known Follow-up

- Finance school plotting is currently empty unless the Finance user receives `school_id` or pivot assignments; this fails closed rather than exposing all schools.
- Existing Admin Report review remains separate from the new scoped Attendance listing.

## Phase 11 - Finance CSV Export

Status: implemented; Finance uses the shared scoped Attendance CSV flow.

### Summary

- Finance role is enabled in login redirect and SuperAdmin account management.
- Finance receives `attendance.view` and `attendance.export_csv` through the existing centralized permission map.
- Finance school scope uses the same `school_user` multi-school assignment as School PIC.
- Finance account creation/update requires at least one assigned school and syncs all selected schools to the pivot.
- Finance Attendance listing and CSV export reuse `AttendanceScopeService` and `AttendanceExportService`; no duplicate exporter was created.
- Backend scope is applied before school filters and before CSV generation, so a Finance user cannot export another school's rows by changing request parameters.
- Finance scope is approved-report-only and fails closed when no school assignment exists.

### Files Changed

- `app/Http/Controllers/Auth/LoginController.php`
- `app/Http/Controllers/Admin/UserController.php`
- `resources/views/admin/users/index.blade.php`
- `resources/views/attendance/index.blade.php`
- `app/Services/AttendanceScopeService.php`
- `app/Services/AttendanceExportService.php`

### Tests

Automated tests are intentionally deferred until the end of the remaining implementation phases. The critical Finance → assigned School → CSV scenario will be included in the final regression pass.

### Known Follow-up

- Finance currently receives school scope through SuperAdmin plotting; there is no global Finance fallback.

## Phase 12 - Accident Notes UI

Status: implemented; Accident Notes now use a reusable urgent red presentation.

### Summary

- Reused the existing `reports.notes` field without changing the data model.
- Added a responsive Bootstrap partial with red border/header, urgent badge, warning icon, and preserved line breaks.
- Displayed the block on Admin report detail, School PIC approved report detail, and Coach report listing when notes are present.
- Removed the old unlabelled duplicate note output from the Admin and School PIC detail views.
- Empty notes do not render an empty alert block, so the existing layout remains compact.

### Files Changed

- `resources/views/partials/accident-notes.blade.php`
- `resources/views/admin/reports/show.blade.php`
- `resources/views/school_pic/reports/show.blade.php`
- `resources/views/coach/reports/index.blade.php`

### Tests

Automated tests and responsive screenshot checks remain deferred until Phase 14, as requested. The manual UI check should confirm the red block wraps correctly on narrow screens and is absent when `reports.notes` is empty.
