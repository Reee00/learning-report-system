# Route Map

Tanggal: 14 Agustus 2026  
Phase: 1 — Architecture & Data Mapping  
Status: current route inventory dan target extension map; belum ada route code yang diubah.

## Route Registration

Laravel mendaftarkan routes/web.php dan routes/console.php, serta health endpoint /up melalui bootstrap/app.php.

routes/api.php tidak tersedia. Endpoint AJAX student saat ini didefinisikan di web.php.

Middleware alias:

- auth → Illuminate\Auth\Middleware\Authenticate;
- role → App\Http\Middleware\RoleMiddleware.

## Current Route Inventory

### Public and authentication

| Method | URI | Name | Middleware | Handler |
|---|---|---|---|---|
| GET | / | — | none | redirect ke login |
| GET | /login | login | none | Auth\LoginController@showForm |
| POST | /login | — | none | Auth\LoginController@login |
| POST | /logout | logout | none (no explicit route middleware) | Auth\LoginController@logout |

Login redirect saat ini:

- admin → admin.dashboard;
- coach → coach.reports.index;
- school_pic → pic.dashboard;
- role lain → /.

Target setelah role migration:

- relation → compatibility route admin.dashboard atau landing operasional Relation;
- superadmin → admin.dashboard;
- coach → coach.reports.index;
- school_pic → pic.dashboard;
- spv_coach dan finance → landing route masing-masing setelah capability route dibuat.

### Student/class routes

Group middleware: auth.

| Method | URI | Name | Handler | Backend scope |
|---|---|---|---|---|
| GET | /classes/{class}/students | students.show | StudentController@show | manual authorizeAccess() |
| POST | /classes/{class}/students | students.store | StudentController@store | manual authorizeAccess() |
| POST | /classes/{class}/students/import | students.import | StudentController@import | manual authorizeAccess() |
| DELETE | /classes/{class}/students/{student} | students.destroy | StudentController@destroy | class ownership check |
| GET | /students/template | students.template | StudentController@template | auth-only |
| GET | /api/classes/{class}/students | — | closure in web.php | auth-only; no school/assignment check |

authorizeAccess() saat ini mengenali admin, coach, dan school_pic. Target roles belum masuk flow ini.

### Coach routes

Group: prefix /coach, name prefix coach., middleware auth + role:coach.

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | /coach/reports | coach.reports.index | Coach\ReportController@index |
| GET | /coach/reports/create | coach.reports.create | Coach\ReportController@create |
| POST | /coach/reports | coach.reports.store | Coach\ReportController@store |
| GET | /coach/reports/{report}/edit | coach.reports.edit | Coach\ReportController@edit |
| PUT/PATCH | /coach/reports/{report} | coach.reports.update | Coach\ReportController@update |

### Admin routes

Current group: prefix /admin, name prefix admin., middleware auth + role:admin.

Target compatibility strategy: URL dan route names admin.* dipertahankan sementara agar link existing tidak rusak. Middleware dan controller authorization akan mengizinkan superadmin/relation hanya pada capability masing-masing; role admin tidak lagi menjadi target role setelah migration.

Dashboard and user management:

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | /admin/dashboard | admin.dashboard | Admin\DashboardController@index |
| GET | /admin/users | admin.users.index | Admin\UserController@index |
| POST | /admin/users | admin.users.store | Admin\UserController@store |
| PUT | /admin/users/{user} | admin.users.update | Admin\UserController@update |
| PATCH | /admin/users/{user}/reset-password | admin.users.reset-password | Admin\UserController@resetPassword |
| DELETE | /admin/users/{user} | admin.users.destroy | Admin\UserController@destroy |

Report review:

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | /admin/reports | admin.reports.index | Admin\ReportController@index |
| GET | /admin/reports/{report} | admin.reports.show | Admin\ReportController@show |
| PATCH | /admin/reports/{report}/approve | admin.reports.approve | Admin\ReportController@approve |
| PATCH | /admin/reports/{report}/reject | admin.reports.reject | Admin\ReportController@reject |

Master data:

Route::resource dideklarasikan untuk /admin/schools dan /admin/classes.

| Resource | Implemented | Generated but not implemented |
|---|---|---|
| schools | index, store, update, destroy | create, show, edit |
| classes | index, store, destroy | create, show, edit |

Coach assignment:

| Method | URI | Name | Handler |
|---|---|---|---|
| GET | /admin/coaches | admin.coaches.index | Admin\CoachController@index |
| GET | /admin/coaches/{coach} | admin.coaches.show | Admin\CoachController@show |
| POST | /admin/coaches/{coach}/assign | admin.coaches.assign | Admin\CoachController@assign |
| DELETE | /admin/coaches/{coach}/assignments/{assignment} | admin.coaches.unassign | Admin\CoachController@unassign |

### School PIC routes

Group: prefix /pic, name prefix pic., middleware auth + role:school_pic.

| Method | URI | Name | Handler | Scope |
|---|---|---|---|---|
| GET | /pic/dashboard | pic.dashboard | SchoolPic\DashboardController@index | reports.school_id = user.school_id |
| GET | /pic/reports/{report} | pic.reports.show | SchoolPic\DashboardController@show | report school equals user school |

## Route-to-Feature Map

| Feature | Existing route | Current owner | Target owner | Phase |
|---|---|---|---|---|
| Login | login, logout | all authenticated users | all roles | preserve |
| School management | admin.schools.* | admin | SuperAdmin + Relation per permission | Phase 2–4 |
| Student management | students.* | manual admin/coach/PIC | SuperAdmin + Relation; Coach scope TBD | Phase 5 |
| Program Kelas | admin.classes.* | admin | SuperAdmin + Relation | Phase 6 |
| Coach management | admin.coaches.* | admin | SuperAdmin + SPV Coach | Phase 7 |
| Coach assignment | admin.coaches.assign/unassign | admin | SuperAdmin + SPV Coach | Phase 7 |
| Program input | absent | — | SuperAdmin + Relation; Coach TBD | Phase 8 |
| Attendance view | report detail only | admin/PIC | role-scoped roles | Phase 10 |
| Attendance export | absent | — | SuperAdmin, Relation, SPV, PIC, Finance per scope | Phase 10–11 |
| School plotting | absent | — | SuperAdmin | Phase 9 |
| Accident Notes | absent | — | display/read by relevant roles | Phase 12 |
| Finance CSV | absent | — | Finance | Phase 11 |
| WaHa notification | absent | — | TBD | Phase 13 |

Aturan Program Kelas: route existing admin.classes.* tetap dipakai untuk SchoolClass yang dapat direuse. Route/model ProgramClass hanya digunakan untuk association program-specific ketika konteks program berbeda.

## Proposed Route Extension Points

| Feature | Proposed route family | Required backend boundary |
|---|---|---|
| Relation/SuperAdmin school | /schools atau existing admin.schools.* compatibility alias | permission + school scope |
| Relation student | /students atau existing class student routes | permission + school/class scope |
| Program Kelas | existing admin.classes.* untuk SchoolClass; ProgramClass association route bila diperlukan | permission + relationship validation |
| Coach management | existing admin.coaches.* atau role-neutral rename | coaches.manage/coaches.assign |
| Program | /programs | finalized Program relationship |
| Attendance list | /attendance | scoped attendance query before response |
| Attendance export | /attendance/export | authorization before file generation |
| Finance CSV | /attendance/export.csv atau format parameter | Finance + school filter validation |
| PIC plotting | /users/{user}/schools atau dedicated plotting route | SuperAdmin only; many-to-many school_user pivot |
| Accident Notes | existing report detail or dedicated endpoint | read permission + report scope |
| Notifications | internal service/event, not public route by default | provider credentials + trigger |

## Route Security Rules

1. Route middleware harus menolak role yang tidak memiliki capability.
2. Controller/service harus mengulang authorization untuk object ownership/scope.
3. School filter diterapkan pada query sebelum pagination atau export.
4. Export tidak boleh mengambil semua rows lalu memfilter di PHP.
5. Route parameter binding harus divalidasi terhadap parent relationship pada nested route.
6. Endpoint student AJAX harus memakai scope yang sama dengan halaman student.
7. Role migration tidak boleh bergantung pada nama URL admin saja; admin.* adalah compatibility naming, bukan role authorization.
8. API/public route baru tidak boleh dibuat tanpa authentication contract.

## Route Conflicts and Risks

- Resource routes schools/classes memiliki action yang controller-nya belum ada.
- Namespace dan route prefix admin melekat pada fungsi yang nanti harus dibagi antara SuperAdmin dan Relation.
- admin.reports.* saat ini menjadi review flow; Relation tidak otomatis boleh approve/reject.
- Student routes dapat menjadi privilege escalation jika role/scope manual tidak diperbarui.
- AJAX student endpoint saat ini auth-only.
- Belum ada route naming convention untuk export; pilih satu shared attendance flow agar tidak membuat duplicate exporter.

## Phase 1 Decisions / Remaining TBD

Keputusan Phase 1:

- RESOLVED: URL/prefix admin dipertahankan sementara sebagai compatibility layer.
- RESOLVED: role admin digantikan oleh relation; superadmin memakai route compatibility yang sama dengan authorization full access.
- RESOLVED: PIC memakai many-to-many school_user pivot.
- RESOLVED: SchoolClass direuse; ProgramClass association route/model dipakai untuk konteks program berbeda.
- RESOLVED: backend authorization memakai Policy/service/query scope, bukan permission package baru.

Remaining TBD non-blocking:

- format endpoint export;
- apakah PIC dan Relation memakai shared attendance route dengan permission atau route group terpisah.

## Phase 1 Recommendation

Pertahankan route existing selama migration authorization berlangsung, tambahkan route baru hanya untuk capability yang benar-benar belum ada, dan gunakan shared service untuk school-scoped attendance. Bersihkan resource action yang tidak diimplementasikan sebagai hygiene change terpisah setelah ada test route coverage.
