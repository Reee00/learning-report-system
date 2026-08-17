# Role and Permission Map

Tanggal: 14 Agustus 2026  
Phase: 1 — Architecture & Data Mapping  
Status: current-state mapping dan target authorization design; belum diimplementasikan.

## Authorization Baseline Saat Ini

Sistem belum memakai permission package, role table, Gate, atau Policy. Enforcement aktual:

- RoleMiddleware membandingkan Auth::user()->role dengan role string;
- route group memakai role:admin, role:coach, atau role:school_pic;
- beberapa controller memiliki ownership/school checks manual;
- Blade menyembunyikan menu berdasarkan auth()->user()->role.

Visibility UI bukan sumber keamanan utama; backend route/controller tetap menjadi boundary.

## Current Role Matrix

| Role aktual | Storage | Route access | Data scope | Catatan |
|---|---|---|---|---|
| admin | users.role enum | seluruh /admin/* | global | full admin flow saat ini |
| coach | users.role enum | /coach/* | report sendiri; class assignment pada sebagian flow | input report/attendance |
| school_pic | users.role enum | /pic/* | satu users.school_id, report approved | belum ada export |

Student routes memakai auth lalu pengecekan manual, bukan role middleware khusus.

## Canonical Target Role Keys

Canonical storage key yang dipakai untuk mapping Phase 1:

| Display label | Proposed key | Existing compatibility |
|---|---|---|
| SuperAdmin | superadmin | role baru; akses penuh |
| Relation | relation | hasil migrasi seluruh existing admin |
| SPV Coach | spv_coach | belum ada |
| Coach | coach | sudah ada |
| PIC Sekolah | school_pic | sudah ada; school scope berpindah ke many-to-many pivot |
| Finance | finance | belum ada |

Keputusan Phase 1: seluruh record dengan role admin dimigrasikan menjadi relation. Role superadmin ditambahkan sebagai role baru dengan akses penuh. Implementasi migration tetap harus idempotent dan mempertahankan akses login existing selama proses transisi.

## Permission Vocabulary

Nama permission konseptual:

- users.manage
- schools.view, schools.create, schools.update, schools.delete
- students.view, students.create, students.update, students.delete
- program_classes.view, program_classes.create, program_classes.update, program_classes.delete
- programs.view, programs.create, programs.update, programs.delete
- coaches.view, coaches.create, coaches.update
- coaches.assign, coaches.reassign
- attendance.view, attendance.export, attendance.export_csv
- accident_notes.view
- notifications.send
- school_plotting.manage

update/delete untuk Relation dan permission input Coach masih mengikuti keputusan bisnis; daftar ini tidak otomatis memberikan hak yang belum disetujui.

## Target Permission Matrix

Legend: G = global/all data, S = restricted school/assignment scope, — = tidak diberikan, TBD = belum final.

| Permission | SuperAdmin | Relation | SPV Coach | Coach | PIC Sekolah | Finance |
|---|---:|---:|---:|---:|---:|---:|
| users.manage | G | — | — | — | — | — |
| school_plotting.manage | G | — | — | — | — | — |
| schools.view | G | G | — | — | S plotted/TBD | G filter-only |
| schools.create | G | G | — | — | — | — |
| schools.update | G | TBD | — | — | — | — |
| schools.delete | G | TBD | — | — | — | — |
| students.view | G | G | — | S assignment | — | — |
| students.create | G | G | — | — | — | — |
| students.update | G | TBD | — | — | — | — |
| students.delete | G | TBD | — | — | — | — |
| program_classes.view | G | G | — | S assignment | — | — |
| program_classes.create | G | G | — | — | — | — |
| program_classes.update | G | TBD | — | — | — | — |
| program_classes.delete | G | TBD | — | — | — | — |
| programs.view | G | G | TBD | TBD | — | — |
| programs.create | G | G | TBD | TBD | — | — |
| programs.update | G | TBD | TBD | TBD | — | — |
| programs.delete | G | TBD | TBD | TBD | — | — |
| coaches.view | G | — | G/S | — | — | — |
| coaches.create | G | — | G/S | — | — | — |
| coaches.update | G | — | TBD | — | — | — |
| coaches.assign | G | — | G/S | — | — | — |
| coaches.reassign | G | — | G/S | — | — | — |
| attendance.view | G | G | G/S policy scope | S assignment | S plotted schools | G filtered by school |
| attendance.export | G | G | G/S policy scope | TBD | S plotted schools | G filtered by school |
| attendance.export_csv | G | TBD | TBD | — | TBD | G filtered by school |
| accident_notes.view | G | TBD | TBD | S assignment | S plotted school | TBD |
| notifications.send | G | TBD | TBD | TBD | TBD | TBD |

Planning yang sudah jelas:

- SuperAdmin full access.
- Relation dapat input School, Student, Program Kelas, Program, dan export attendance pada operational scope global.
- SPV Coach dapat input/manage Coach, assign/reassign Coach, dan export attendance.
- Coach melihat assignment, menjalankan learning flow, dan melihat Accident Notes; input Program belum final.
- PIC hanya melihat/export attendance pada school scope.
- Finance melihat dan export CSV attendance berdasarkan school filter; Finance tidak mendapat hak master-data management.

## Backend Scope Rules

Permission harus diikuti query scope:

    authenticated user
      → role/permission check
      → allowed school/class scope
      → filtered Eloquent query
      → response/export

| Role | Scope source |
|---|---|
| SuperAdmin | global |
| Relation | global operational input/read; edit/delete tetap mengikuti permission final |
| SPV Coach | coach/assignment operational scope sesuai keputusan |
| Coach | coach_classes dan ownership report |
| PIC Sekolah | many-to-many school_user pivot; users.school_id hanya legacy/backfill |
| Finance | global attendance query dengan school filter; query diterapkan sebelum export |

## Current Authorization Gaps

1. admin menggabungkan full system access dengan operational master-data access.
2. Admin route names, controller namespaces, Blade conditions, seeder, dan login redirect hardcode admin.
3. Coach ReportController@store belum memastikan class yang dikirim assigned ke Coach.
4. Attendance input belum memastikan student berasal dari class report.
5. Endpoint student AJAX hanya auth, tanpa role/assignment/school check.
6. Tidak ada reusable Policy/query scope.
7. Tidak ada authorization boundary untuk export karena export belum tersedia.

## Migration Strategy for Roles

Migration role tidak boleh berupa blind global replace.

Urutan:

1. putuskan canonical keys dan mapping data existing;
2. inventaris semua admin reference;
3. buat compatibility strategy agar login existing tidak rusak;
4. pisahkan full access SuperAdmin dari Relation;
5. ubah backend authorization terlebih dahulu;
6. migrasikan data role secara terkontrol;
7. bersihkan label/namespace lama jika aman;
8. tambah feature/security tests.

Mapping admin → relation sudah diputuskan. Karena admin saat ini melakukan user management, report approval, dan seluruh master data, migration authorization harus memastikan privilege tersebut berpindah ke SuperAdmin dan hanya capability yang disetujui tetap tersedia untuk Relation.

## Permission Implementation Decision

Keputusan Phase 1: tidak menambahkan permission package baru. Authorization akan memakai role keys yang terpusat, ditambah Policy/service/query scope untuk backend. Middleware role tetap dapat dipakai sebagai coarse route gate, tetapi object ownership dan school scope wajib diperiksa di backend query/controller.

## Resolved Decisions / Remaining TBD

- RESOLVED: existing admin → relation.
- RESOLVED: role superadmin baru dengan full access.
- RESOLVED: PIC multi-school melalui school_user pivot.
- RESOLVED: canonical keys superadmin, relation, spv_coach, coach, school_pic, finance.
- RESOLVED: Policy/service/query scope tanpa permission package baru.
- TBD: Relation edit/delete permissions.
- TBD: Coach input Program.
- TBD: Finance melihat master School atau hanya filter attendance.
- TBD: role mana yang boleh mengirim notification.
- TBD: permission storage/package.

## Phase 1 Recommendation

Phase 1 sudah cukup untuk memulai Phase 2. Implementasi berikutnya harus menempatkan backend authorization lebih dahulu, memigrasikan admin → relation secara aman, menambahkan SuperAdmin, kemudian menyesuaikan navigation/UI. Permission edit/delete Relation tetap dapat ditetapkan sebagai keputusan lanjutan tanpa menghambat role migration awal.
