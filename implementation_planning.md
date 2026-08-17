# Implementation Planning --- Learning Report System

**Version:** 1.0\
**Status:** Ready for AI Agent Planning/Implementation\
**Date:** 2026-08-14\
**Project:** Learning Report System

## 1. Purpose

Dokumen ini menerjemahkan PRD Learning Report System Update v2 menjadi
rencana implementasi teknis yang dapat dijalankan oleh AI Agent secara
bertahap.

Dokumen ini **bukan instruksi untuk rewrite project dari nol**. Agent
wajib melakukan audit terhadap implementasi existing terlebih dahulu,
kemudian memperluas sistem dengan perubahan sekecil mungkin.

### Source of Truth

1.  `contextproject.md` --- baseline teknis, arsitektur, stack, dan
    setup project.
2.  `NoteTambahan.md` --- requirement tambahan.
3.  PRD Learning Report System Update v2 --- requirement produk yang
    telah diselaraskan dengan update role `Admin → Relation`.

Jika implementasi existing berbeda dengan asumsi pada planning ini,
**implementasi existing harus diperiksa dan planning harus disesuaikan
sebelum melakukan perubahan destruktif**.

------------------------------------------------------------------------

# 2. Project Baseline

Project menggunakan:

-   PHP 8.2 / 8.3
-   Laravel 12
-   JavaScript
-   Blade
-   SQLite pada local environment
-   dukungan MySQL/PostgreSQL
-   Cloudinary
-   Fast-Excel
-   Vite
-   Docker
-   PHPUnit
-   MVC Laravel

Struktur utama yang wajib diaudit:

``` text
app/
├── Controllers/
├── Models/
├── Middleware/
└── Policies/ / Services/ jika tersedia

routes/
├── web.php
└── api.php

database/
├── migrations/
├── seeders/
└── factories/

resources/
├── views/
└── js/css/assets

config/
public/
tests/
```

------------------------------------------------------------------------

# 3. Core Implementation Principle

Gunakan prinsip:

``` text
AUDIT
  ↓
MAP
  ↓
PLAN
  ↓
IMPLEMENT
  ↓
TEST
  ↓
REGRESSION
```

Agent **dilarang** langsung:

-   membuat ulang authentication;
-   membuat ulang attendance;
-   membuat ulang program;
-   membuat ulang database;
-   membuat role system baru;
-   membuat tabel yang kemungkinan sudah tersedia;
-   mengganti framework;
-   mengganti library;
-   menghapus fitur existing.

Sebelum membuat sesuatu, agent harus mencari apakah functionality
tersebut sudah ada.

------------------------------------------------------------------------

# 4. Target Role Architecture

Role final:

``` text
SUPERADMIN
RELATION
SPV_COACH
COACH
PIC_SEKOLAH
FINANCE
```

## 4.1 SuperAdmin

Full access:

-   user management;
-   role management;
-   school management;
-   student management;
-   program class management;
-   coach management;
-   coach assignment;
-   program;
-   attendance;
-   export;
-   Accident Notes;
-   school plotting;
-   notification/configuration.

## 4.2 Relation

Requirement confirmed:

-   input data sekolah;
-   input data murid;
-   input program kelas;
-   input program;
-   export absensi.

Hak edit/delete belum ditentukan. Jangan menambah permission edit/delete
tanpa bukti dari existing system atau keputusan bisnis.

## 4.3 SPV Coach

-   input data Coach;
-   edit Coach jika existing permission mendukung;
-   assign Coach;
-   reassign Coach;
-   melihat assignment;
-   export absensi.

## 4.4 Coach

-   melihat assignment;
-   menjalankan program/labor pembelajaran yang tersedia;
-   melihat Accident Notes.

Hak input program untuk Coach belum dianggap final karena requirement
terbaru menegaskan Relation sebagai pihak yang dapat input program.

## 4.5 PIC Sekolah

-   melihat attendance sekolah yang diplot;
-   filter attendance;
-   export attendance.

## 4.6 Finance

-   melihat attendance;
-   filter berdasarkan sekolah;
-   export/download CSV.

------------------------------------------------------------------------

# 5. Implementation Phases

Urutan wajib:

``` text
Phase 0  Existing Project Audit
Phase 1  Architecture & Data Mapping
Phase 2  Role Migration Admin → Relation
Phase 3  Authorization & Permission
Phase 4  Master Data: School
Phase 5  Master Data: Student
Phase 6  Master Data: Program Kelas
Phase 7  Coach Management & Assignment
Phase 8  Program Input
Phase 9  School Plotting & PIC
Phase 10 Attendance Scope & Export
Phase 11 Finance CSV Export
Phase 12 Accident Notes UI
Phase 13 WhatsApp / WaHa
Phase 14 Testing & Regression
Phase 15 Final Verification
```

Agent tidak boleh melompati Phase 0 dan Phase 1.

------------------------------------------------------------------------

# 6. Phase 0 --- Existing Project Audit

## Objective

Memahami kondisi nyata project sebelum perubahan.

## Audit Files

Cari dan baca:

``` text
composer.json
package.json
.env.example
routes/web.php
routes/api.php
app/Models/*
app/Http/Controllers/*
app/Http/Middleware/*
app/Policies/*
app/Services/*
database/migrations/*
database/seeders/*
database/factories/*
resources/views/*
resources/js/*
resources/css/*
tests/*
Dockerfile
docker-compose* jika tersedia
```

## Audit Questions

Agent harus menjawab:

1.  Bagaimana authentication saat ini?
2.  Apakah role system sudah tersedia?
3.  Apakah `Admin` sudah ada?
4.  Di mana role disimpan?
5.  Apakah permission package digunakan?
6.  Apakah School model sudah ada?
7.  Apakah Student model sudah ada?
8.  Apakah Program/Class model sudah ada?
9.  Apakah Coach model sudah ada?
10. Apakah Attendance sudah ada?
11. Bagaimana attendance diekspor?
12. Apakah Relation sebelumnya sudah ada?
13. Bagaimana PIC direpresentasikan?
14. Bagaimana user-school relationship saat ini?
15. Bagaimana Accident Notes disimpan?
16. Apakah notification system sudah ada?
17. Apakah WaHa integration sudah ada?
18. Apakah existing tests tersedia?

## Output

Agent harus membuat:

``` text
docs/audit/existing-system-audit.md
```

Isi minimal:

``` text
Current Architecture
Current Roles
Current Permissions
Current Models
Current Relationships
Current Routes
Current Attendance Flow
Current Export Flow
Current Program Flow
Current Notification Flow
Existing Tests
Potential Conflicts
Recommended Extension Points
```

------------------------------------------------------------------------

# 7. Phase 1 --- Architecture & Data Mapping

Setelah audit, buat mapping:

``` text
docs/audit/data-model-map.md
docs/audit/role-permission-map.md
docs/audit/route-map.md
```

## Data Mapping

Petakan:

``` text
User
Role
School
Student
Program Class
Program
Coach
Coach Assignment
Attendance
Accident Notes
Notification
```

Untuk setiap entity catat:

-   existing table;
-   existing model;
-   primary key;
-   foreign keys;
-   relationships;
-   migration;
-   controller;
-   route;
-   view;
-   apakah perlu perubahan.

## Important Rule

Jika entity sudah tersedia:

> EXTEND EXISTING ENTITY

Jangan membuat duplicate entity.

------------------------------------------------------------------------

# 8. Phase 2 --- Admin → Relation

## Objective

Mengubah konsep role `Admin` menjadi `Relation` tanpa merusak existing
system.

## Search Targets

Agent harus mencari seluruh reference:

``` text
Admin
admin
ADMIN
role_id
role
permission
middleware
@can
can(
Gate
Policy
```

Search juga:

-   navigation;
-   Blade condition;
-   controller authorization;
-   seeder;
-   factory;
-   tests;
-   route middleware.

## Strategy

Jika `Admin` hanya nama role:

``` text
Admin → Relation
```

Jika `Admin` memiliki business logic khusus:

``` text
Admin
 ↓
Audit usages
 ↓
Separate generic admin privileges
and Relation business privileges
```

Jangan blind global replace.

## Acceptance

-   [ ] Tidak ada workflow baru yang masih bergantung pada nama Admin
    jika seharusnya Relation.
-   [ ] Relation dapat login.
-   [ ] Existing SuperAdmin tidak rusak.
-   [ ] Existing authentication tidak rusak.
-   [ ] Existing authorization test tetap lulus.

------------------------------------------------------------------------

# 9. Phase 3 --- Authorization & Permission

## Objective

Membuat permission berdasarkan role.

Gunakan mechanism existing project jika sudah tersedia.

Priority:

``` text
Backend Authorization > UI Visibility
```

## Permission Concept

Minimal:

``` text
users.manage
schools.view
schools.create
students.view
students.create
program_classes.view
program_classes.create
programs.view
programs.create
coaches.view
coaches.create
coaches.assign
attendance.view
attendance.export
attendance.export_csv
accident_notes.view
notifications.send
```

Nama permission final harus mengikuti authorization system existing.

## Role Mapping

### SuperAdmin

All relevant permissions.

### Relation

``` text
schools.view/create
students.view/create
program_classes.view/create
programs.view/create
attendance.view/export
```

### SPV Coach

``` text
coaches.view/create
coaches.assign
attendance.view/export
```

### Coach

Scope-specific learning access + Accident Notes.

### PIC

``` text
attendance.view
attendance.export
```

dengan school scope.

### Finance

``` text
attendance.view
attendance.export_csv
```

------------------------------------------------------------------------

# 10. Phase 4 --- School Management

## Objective

Relation dapat input sekolah.

## Agent Tasks

1.  Audit existing School model/table.
2.  Reuse existing entity jika tersedia.
3.  Tambahkan field hanya jika requirement benar-benar membutuhkan.
4.  Buat validation.
5.  Buat controller/service sesuai architecture existing.
6.  Buat route.
7.  Buat Blade/view.
8.  Tambahkan permission.
9.  Tambahkan test.

## Acceptance

-   Relation dapat membuka School Management.
-   Relation dapat create school.
-   Data tersimpan.
-   Validation bekerja.
-   SuperAdmin tetap dapat mengakses.
-   Unauthorized role ditolak.

------------------------------------------------------------------------

# 11. Phase 5 --- Student Management

## Objective

Relation dapat input murid dan menghubungkan murid dengan sekolah.

## Relationship

Target konseptual:

``` text
School
  └── Students
```

## Agent Tasks

1.  Audit Student model/table.
2.  Audit School ↔ Student relationship.
3.  Reuse existing relation jika tersedia.
4.  Implement create student.
5.  Validate school association.
6.  Add authorization.
7.  Add tests.

## Acceptance

-   Relation dapat input student.
-   Student terhubung ke School.
-   Data invalid ditolak.
-   Unauthorized user ditolak.

------------------------------------------------------------------------

# 12. Phase 6 --- Program Kelas

## Objective

Relation dapat input Program Kelas.

## Audit

Cari entity dengan kemungkinan nama:

``` text
Class
ProgramClass
ClassProgram
Kelas
Program Kelas
```

Jangan membuat tabel baru sebelum audit selesai.

## Target Relationship

Konseptual:

``` text
School
  ↓
Program Kelas
  ↓
Student / Program
```

Relationship final harus mengikuti database existing.

## Acceptance

-   Relation dapat input Program Kelas.
-   Program Kelas dapat dikaitkan dengan entity yang benar.
-   Validation tersedia.
-   Authorization tersedia.
-   Existing program flow tidak rusak.

------------------------------------------------------------------------

# 13. Phase 7 --- Coach Management & Assignment

## Objective

SPV Coach dapat mengelola dan assign Coach.

## Tasks

-   audit Coach model;
-   implement create/edit jika belum ada;
-   implement assignment;
-   validate assignment;
-   prevent invalid assignment;
-   implement authorization;
-   test.

## Assignment

Agent harus menentukan entity assignment berdasarkan existing model.

Jangan mengasumsikan Coach selalu langsung di-assign ke School.

Kemungkinan:

``` text
Coach → School
Coach → Program Class
Coach → Program
Coach → Schedule
```

Harus dipilih berdasarkan existing architecture/business rule.

------------------------------------------------------------------------

# 14. Phase 8 --- Program Input

## Objective

Relation dapat input Program.

## Audit

Cari:

``` text
Program
programs
program controller
program routes
program views
```

## Tasks

-   reuse existing Program entity;
-   map Program ↔ School/Class/Coach sesuai existing;
-   implement Relation permission;
-   validate input;
-   add tests.

## Important

Jangan otomatis memberikan permission input Program kepada Coach jika
belum dikonfirmasi.

------------------------------------------------------------------------

# 15. Phase 9 --- School Plotting & PIC

## Objective

SuperAdmin dapat membuat PIC dan menentukan school scope.

## Target

``` text
User
  ↓
School Assignment
  ↓
School
```

## Tasks

1.  Audit existing user-school relationship.
2.  Jika sudah ada, reuse.
3.  Jika belum ada, design relationship.
4.  Add PIC creation.
5.  Add school assignment.
6.  Add edit assignment.
7.  Add revoke assignment.
8.  Add authorization policy.

## Multi-School

Jangan menentukan apakah PIC dapat memiliki satu atau banyak sekolah
sebelum audit/confirmation.

Jika existing design mendukung many-to-many, gunakan design tersebut.

------------------------------------------------------------------------

# 16. Phase 10 --- Attendance Scope & Export

## Objective

Attendance mengikuti role dan school scope.

## Required Scope

### SuperAdmin

All.

### Relation

Permission-defined operational scope.

### SPV Coach

Coach/operational scope.

### PIC

Assigned school(s) only.

### Finance

School-filtered attendance.

## Critical Security Rule

School filtering harus terjadi di backend query.

Tidak cukup:

``` text
Hide school option in frontend
```

Harus:

``` text
Request
 ↓
Authorization
 ↓
School Scope
 ↓
Query
 ↓
Result
```

------------------------------------------------------------------------

# 17. Phase 10.A Attendance Export

## Flow

``` text
Attendance
 ↓
Authorization
 ↓
School Scope
 ↓
Filters
 ↓
Export Service
 ↓
File
```

## Existing Export

Audit Fast-Excel/export implementation terlebih dahulu.

Reuse existing export service jika memungkinkan.

Jangan membuat export implementation kedua jika functionality existing
dapat diperluas.

------------------------------------------------------------------------

# 18. Phase 11 --- Finance CSV Export

## Objective

Finance dapat export attendance ke CSV.

## Tasks

1.  Add Finance role.
2.  Add attendance permission.
3.  Add school filter.
4.  Add CSV export.
5.  Reuse attendance query.
6.  Ensure authorization is applied before export.
7.  Add test.

## Critical Test

``` text
Finance
 ↓
School A filter
 ↓
CSV
 ↓
Only School A rows
```

Jika Finance memang memiliki global school access.

------------------------------------------------------------------------

# 19. Phase 12 --- Accident Notes

## Objective

Meningkatkan visibility Accident Notes.

## UI Requirement

Accident Notes harus:

-   terlihat urgent;
-   menggunakan red visual treatment;
-   tetap mengikuti design system existing;
-   tidak mengganggu layout.

## Tasks

1.  Locate Accident Notes component/view.
2.  Update visual treatment.
3.  Add regression screenshot/manual check if project supports it.
4.  Test responsive layout.

Jangan mengubah data model Accident Notes jika requirement hanya
membutuhkan perubahan visual.

------------------------------------------------------------------------

# 20. Phase 13 --- WaHa Integration

## Objective

Menyediakan WhatsApp notification melalui WaHa.

## First Step

Agent harus audit apakah integration sudah tersedia.

Search:

``` text
WAHA
WaHa
WhatsApp
whatsapp
notification
webhook
API client
HTTP client
```

## Do Not Code Until API Is Known

Diperlukan:

-   base URL;
-   API authentication;
-   session;
-   endpoint;
-   request format;
-   response format;
-   recipient format.

Jika informasi tersebut belum tersedia:

> STOP integration implementation and produce a blocker report.

## Recommended Architecture

``` text
Application Event
      ↓
Notification Service
      ↓
WaHa Adapter
      ↓
WaHa API
      ↓
WhatsApp
```

Credential:

``` text
.env
```

Tidak boleh hard-code.

------------------------------------------------------------------------

# 21. Notification Event

Trigger belum final.

Possible future events:

``` text
Accident
Attendance
Program
Report
```

Agent tidak boleh mengaktifkan notification trigger tanpa requirement
yang jelas.

------------------------------------------------------------------------

# 22. Testing Strategy

Testing dilakukan pada:

## Unit Test

-   permission;
-   policy;
-   service;
-   export;
-   school scope.

## Feature Test

-   login;
-   role access;
-   CRUD;
-   attendance;
-   export.

## Security Test

Cross-school access.

## Regression Test

Existing features.

------------------------------------------------------------------------

# 23. Required Test Matrix

  Scenario                                    Expected
  ------------------------------------------- --------------------
  SuperAdmin opens all schools                Allowed
  Relation creates school                     Allowed
  Relation creates student                    Allowed
  Relation creates Program Kelas              Allowed
  Relation exports attendance                 Allowed
  SPV creates Coach                           Allowed
  SPV assigns Coach                           Allowed
  SPV exports attendance                      Allowed
  PIC opens assigned school                   Allowed
  PIC opens unassigned school                 Denied
  PIC exports assigned school                 Allowed
  PIC exports unassigned school               Denied
  Finance views attendance                    Allowed
  Finance exports CSV                         Allowed
  Unauthorized role accesses Relation route   Denied
  Accident Notes visible                      Red/urgent styling
  Existing login                              Still works
  Existing attendance                         Still works
  Existing export                             Still works

------------------------------------------------------------------------

# 24. Migration Strategy

Before migration:

``` text
1. Inspect existing schema
2. Backup database
3. Determine required changes
4. Create migration
5. Run migration
6. Verify schema
7. Run tests
```

Never drop production data as part of an ordinary feature
implementation.

If role migration is required:

``` text
Admin
 ↓
Data migration
 ↓
Relation
```

Do not simply rename records if role IDs/permissions have dependencies
without checking foreign keys.

------------------------------------------------------------------------

# 25. Seeders

Update seeders only if needed.

Potential roles:

``` text
SuperAdmin
Relation
SPV Coach
Coach
PIC Sekolah
Finance
```

Seeder must be idempotent where possible.

Avoid creating duplicate users/roles every execution.

------------------------------------------------------------------------

# 26. Route Strategy

Audit existing route naming conventions.

Prefer existing conventions.

Potential conceptual routes:

``` text
/schools
/students
/program-classes
/programs
/coaches
/attendance
/attendance/export
/users
```

Do not create duplicate routes for existing functionality.

Use middleware/policies for access control.

------------------------------------------------------------------------

# 27. Controller Strategy

Controllers should remain thin.

Preferred flow:

``` text
Controller
   ↓
Authorization
   ↓
Validation
   ↓
Service / Domain Logic
   ↓
Model / Query
   ↓
Response
```

Do not place large business logic blocks directly in Blade or routes.

Follow existing architecture if the project already uses a different
service pattern.

------------------------------------------------------------------------

# 28. Query Scope Strategy

For school-based data:

``` text
User
 ↓
resolve allowed schools
 ↓
apply query scope
 ↓
fetch data
```

Never:

``` text
fetch all
 ↓
filter in PHP
```

for sensitive school-level data unless the existing architecture
explicitly requires it and security is preserved.

------------------------------------------------------------------------

# 29. UI Strategy

Use existing:

-   Blade layout;
-   navigation;
-   components;
-   typography;
-   colors;
-   forms;
-   table patterns;
-   modal patterns.

Do not introduce a new UI framework unless required.

Role-specific navigation:

``` text
SuperAdmin → full navigation
Relation → school/student/program navigation
SPV Coach → coach/assignment navigation
Coach → program/learning navigation
PIC → attendance/export
Finance → attendance/CSV
```

------------------------------------------------------------------------

# 30. Error Handling

Implement consistent handling for:

-   unauthorized;
-   forbidden;
-   validation errors;
-   missing school;
-   invalid assignment;
-   export failure;
-   WaHa failure.

Do not expose:

-   API keys;
-   tokens;
-   internal stack traces;
-   sensitive database details.

------------------------------------------------------------------------

# 31. Logging

Important operations should be logged if the existing project has
logging/audit infrastructure:

-   role change;
-   school plotting;
-   coach assignment;
-   export;
-   notification attempt;
-   notification failure.

Do not introduce an entire audit system unless justified by existing
architecture or requirement.

------------------------------------------------------------------------

# 32. Documentation Deliverables

Agent should produce/update:

``` text
docs/audit/existing-system-audit.md
docs/audit/data-model-map.md
docs/audit/role-permission-map.md
docs/audit/route-map.md
docs/implementation/implementation-notes.md
docs/implementation/test-results.md
```

If the project already has a documentation convention, follow it
instead.

------------------------------------------------------------------------

# 33. AI Agent Operating Rules

## Rule 1 --- Inspect Before Edit

Always read the relevant files before modifying them.

## Rule 2 --- Search Before Create

Search for existing:

-   model;
-   table;
-   controller;
-   route;
-   view;
-   permission;
-   service.

## Rule 3 --- Minimal Change

Change only what is required.

## Rule 4 --- Preserve Existing

Do not break working functionality.

## Rule 5 --- No Assumptions

If requirement is unclear, mark it:

``` text
BLOCKER / TBD
```

rather than inventing behavior.

## Rule 6 --- Security First

Backend authorization is mandatory.

## Rule 7 --- Test After Each Phase

Do not wait until the end to discover regressions.

## Rule 8 --- Explain Changes

After each phase, report:

``` text
Files Changed
Database Changes
Routes Added/Changed
Permissions Changed
Tests Added
Tests Passed
Known Risks
```

------------------------------------------------------------------------

# 34. Agent Execution Protocol

Untuk setiap phase:

``` text
STEP 1
Read requirements

STEP 2
Inspect relevant project files

STEP 3
Report findings

STEP 4
Determine reuse vs new implementation

STEP 5
Implement minimal change

STEP 6
Run tests

STEP 7
Run relevant manual verification

STEP 8
Review diff

STEP 9
Report result

STEP 10
Proceed to next phase only if current phase is stable
```

------------------------------------------------------------------------

# 35. Stop Conditions

Agent wajib berhenti dan meminta clarification jika menemukan:

1.  Existing architecture bertentangan dengan PRD.
2.  Database relationship tidak jelas.
3.  Role Admin memiliki fungsi yang tidak dapat dipetakan ke Relation.
4.  Coach assignment tidak jelas.
5.  PIC school scope tidak jelas.
6.  Finance scope tidak jelas.
7.  WaHa API tidak tersedia.
8.  Migration berpotensi menghapus/merusak data.
9.  Existing feature akan rusak jika requirement diterapkan.
10. Requirement baru membutuhkan keputusan bisnis yang belum tersedia.

------------------------------------------------------------------------

# 36. Final Verification Checklist

## Architecture

-   [ ] Existing architecture tetap digunakan.
-   [ ] Tidak ada duplicate architecture.
-   [ ] Tidak ada unnecessary rewrite.

## Roles

-   [ ] SuperAdmin
-   [ ] Relation
-   [ ] SPV Coach
-   [ ] Coach
-   [ ] PIC Sekolah
-   [ ] Finance

## Master Data

-   [ ] School
-   [ ] Student
-   [ ] Program Kelas
-   [ ] Coach

## Operational

-   [ ] Program
-   [ ] Attendance
-   [ ] Accident Notes

## Access

-   [ ] Role authorization
-   [ ] School plotting
-   [ ] Backend query scope
-   [ ] Cross-school security

## Export

-   [ ] Relation export
-   [ ] SPV export
-   [ ] PIC export
-   [ ] Finance CSV
-   [ ] SuperAdmin export

## Notification

-   [ ] WaHa audit
-   [ ] API credentials
-   [ ] Notification service
-   [ ] Error handling
-   [ ] Logging

## Regression

-   [ ] Existing authentication
-   [ ] Existing learning report
-   [ ] Existing program
-   [ ] Existing attendance
-   [ ] Existing export
-   [ ] Existing media
-   [ ] Existing deployment

------------------------------------------------------------------------

# 37. Final Implementation Order

``` text
PHASE 0
Existing Project Audit
        ↓
PHASE 1
Architecture + Database + Role Mapping
        ↓
PHASE 2
Admin → Relation
        ↓
PHASE 3
Authorization
        ↓
PHASE 4
School
        ↓
PHASE 5
Student
        ↓
PHASE 6
Program Kelas
        ↓
PHASE 7
Coach + Assignment
        ↓
PHASE 8
Program
        ↓
PHASE 9
PIC + School Plotting
        ↓
PHASE 10
Attendance + Export
        ↓
PHASE 11
Finance CSV
        ↓
PHASE 12
Accident Notes UI
        ↓
PHASE 13
WaHa
        ↓
PHASE 14
Testing + Regression
        ↓
PHASE 15
Final Verification
```

------------------------------------------------------------------------

# 38. Final Success Criteria

Implementation dianggap berhasil apabila:

1.  `Admin` telah dipetakan menjadi `Relation` secara konsisten.
2.  Relation dapat input Sekolah.
3.  Relation dapat input Murid.
4.  Relation dapat input Program Kelas.
5.  Relation dapat input Program.
6.  Relation dapat export Attendance.
7.  SPV Coach dapat input dan assign Coach.
8.  PIC hanya dapat mengakses sekolah yang menjadi scope-nya.
9.  Finance dapat melihat attendance berdasarkan sekolah.
10. Finance dapat export CSV.
11. SuperAdmin mempunyai full access.
12. SuperAdmin dapat membuat PIC dan menentukan school plotting.
13. Accident Notes memiliki visual urgent berwarna merah.
14. WaHa siap/integrated setelah API dan trigger tersedia.
15. Tidak ada privilege escalation.
16. Existing features tetap berjalan.
17. Automated tests dan regression checks lulus.

------------------------------------------------------------------------

# 39. Agent Completion Report Template

Setelah seluruh implementasi selesai, agent harus memberikan laporan:

``` text
# Implementation Completion Report

## Summary
[Ringkasan]

## Completed Phases
- [x] Phase 0
- [x] Phase 1
...

## Files Changed
- path/to/file

## Database Changes
- migration

## Roles / Permissions
- changes

## Routes
- changes

## Tests
- passed
- failed

## Security Verification
- cross-school access: PASS/FAIL

## Existing Feature Regression
- PASS/FAIL

## WaHa
- configured / blocked / not implemented

## Known Limitations
- ...

## Remaining Decisions
- ...

## Recommendation
- ...
```

------------------------------------------------------------------------

# 40. Final Instruction to AI Agent

Gunakan dokumen ini sebagai implementation planning baseline.

**Jangan langsung coding seluruh phase dalam satu langkah.**

Mulai dari:

> **Phase 0 --- Existing Project Audit**

Setelah audit selesai, tampilkan findings dan mapping terlebih dahulu.

Baru lanjut ke Phase 1.

Jika terdapat perbedaan antara dokumen planning dan codebase actual:

> **Codebase actual harus diaudit, bukan langsung dianggap salah.**

Jika terdapat requirement yang belum jelas:

> **Stop → dokumentasikan blocker → minta keputusan.**

Jika fitur existing sudah berjalan:

> **Extend, jangan rewrite.**

Jika security dan convenience bertentangan:

> **Prioritaskan security.**

Target akhir adalah mengembangkan Learning Report System secara
incremental, terukur, aman, dan tanpa merusak functionality yang sudah
berjalan.
