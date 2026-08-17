# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## Learning Report System — Role, Master Data, Attendance & Notification Update

**Project:** Learning Report System
**Document Type:** Product Requirements Document
**Version:** 2.0
**Status:** Draft — Development Baseline
**Tanggal:** 14 Agustus 2026

### Source of Truth

1. `contextproject.md` — baseline project, technology, architecture, dan existing system.
2. `NoteTambahan.md` — requirement pengembangan tambahan.
3. Update requirement terbaru — perubahan role **Admin menjadi Relation** dan penambahan tanggung jawab Relation terhadap data Sekolah, Murid, dan Program Kelas.

---

# 1. Executive Summary

Learning Report System merupakan aplikasi berbasis web yang digunakan untuk mengelola dan melaporkan hasil pembelajaran peserta didik.

Berdasarkan `contextproject.md`, aplikasi menggunakan Laravel 12.0, PHP 8.2/8.3, JavaScript, database SQLite pada local environment dengan dukungan MySQL/PostgreSQL, Cloudinary untuk penyimpanan media, Fast-Excel untuk kebutuhan export/import Excel, Vite untuk frontend asset, serta Docker untuk deployment. Arsitektur aplikasi menggunakan pola MVC Laravel.

Pengembangan terbaru memperluas fungsi sistem menjadi sistem operasional pembelajaran yang mencakup:

* pengelolaan sekolah;
* pengelolaan murid;
* pengelolaan program kelas;
* pengelolaan Coach;
* assignment Coach;
* input program;
* pengelolaan dan monitoring absensi;
* export absensi;
* pengelolaan PIC Sekolah;
* school plotting;
* monitoring absensi oleh Finance;
* Accident Notes dengan visual urgent;
* notifikasi WhatsApp melalui WaHa;
* role-based access control.

Perubahan paling penting pada versi PRD ini adalah:

> **Role Admin tidak lagi digunakan sebagai role operasional. Role tersebut menjadi `Relation`.**

Relation bertanggung jawab terhadap penginputan dan pengelolaan data operasional utama yang berkaitan dengan sekolah, murid, program kelas, program, serta kebutuhan export absensi.

---

# 2. Product Vision

Membangun Learning Report System sebagai platform terintegrasi untuk mengelola:

```text
MASTER DATA
     +
PROGRAM & PEMBELAJARAN
     +
ATTENDANCE
     +
REPORTING
     +
ROLE & ACCESS CONTROL
     +
NOTIFICATION
```

Sistem harus memungkinkan setiap stakeholder bekerja berdasarkan tanggung jawabnya tanpa memberikan akses data yang tidak diperlukan.

Prinsip utama:

> **One System, Role-Based Access, School-Based Data Visibility.**

---

# 3. Problem Statement

Sistem membutuhkan pengembangan agar proses operasional tidak hanya berfokus pada learning report, tetapi juga mencakup pengelolaan data yang menjadi dasar proses pembelajaran.

Kebutuhan utama:

1. Data sekolah perlu dapat dimasukkan dan dikelola.
2. Data murid perlu dapat dimasukkan dan dikelola.
3. Program kelas perlu dapat dimasukkan dan dikelola.
4. Coach perlu dapat dikelola dan di-assign.
5. Program pembelajaran perlu dapat diinput.
6. Absensi perlu dapat dilihat dan diekspor berdasarkan hak akses.
7. PIC Sekolah harus dibatasi berdasarkan sekolah yang diplot.
8. Finance membutuhkan akses absensi berdasarkan sekolah.
9. Accident Notes perlu diberikan penekanan visual karena bersifat urgent.
10. Sistem membutuhkan notifikasi WhatsApp melalui WaHa.
11. SuperAdmin membutuhkan full access untuk mengelola sistem dan user.

---

# 4. Goals

## 4.1 Master Data Management

Menyediakan pengelolaan:

* Sekolah;
* Murid;
* Program Kelas;
* Coach.

## 4.2 Role-Based Access Control

Memastikan setiap role hanya memperoleh akses sesuai tanggung jawab.

## 4.3 School-Based Data Visibility

Memastikan user seperti PIC Sekolah hanya dapat melihat data sekolah yang menjadi tanggung jawabnya.

## 4.4 Attendance Management

Menyediakan monitoring dan export data absensi.

## 4.5 Operational Learning Management

Menyediakan kemampuan input program dan program kelas.

## 4.6 Urgent Accident Information

Membuat Accident Notes lebih terlihat dengan visual emphasis merah.

## 4.7 WhatsApp Notification

Menyediakan integrasi notifikasi melalui WaHa.

---

# 5. Non-Goals

Hal berikut belum menjadi bagian dari scope utama:

* mobile application;
* accounting/finance system penuh;
* payroll;
* redesign total aplikasi;
* migrasi framework;
* penggantian Laravel;
* penggantian database tanpa kebutuhan;
* penggantian Cloudinary;
* penggantian Fast-Excel;
* integrasi WhatsApp provider selain WaHa;
* fitur lain yang belum disebutkan dalam source requirement.

---

# 6. Technical Baseline

Berdasarkan `contextproject.md`:

| Component    | Technology                  |
| ------------ | --------------------------- |
| Backend      | Laravel 12.0                |
| Language     | PHP 8.2 / 8.3               |
| Frontend     | Blade + JavaScript          |
| Database     | SQLite / MySQL / PostgreSQL |
| Media        | Cloudinary                  |
| Excel        | Fast-Excel                  |
| Bundler      | Vite                        |
| Deployment   | Docker                      |
| Architecture | MVC                         |
| Testing      | PHPUnit                     |

Struktur utama:

```text
app/
├── Controllers/
├── Models/
└── Middleware/

routes/
├── web.php
└── api.php

database/
├── migrations/
├── seeders/
└── factories/

resources/
├── views/
└── assets/

public/
config/
tests/
```

PRD ini tidak mengubah technical baseline tersebut.

---

# 7. Product Domain Structure

Dengan tambahan requirement Relation, domain sistem sekarang lebih jelas menjadi empat kelompok:

```text
LEARNING REPORT SYSTEM
│
├── MASTER DATA
│   ├── Sekolah
│   ├── Murid
│   ├── Program Kelas
│   └── Coach
│
├── OPERATIONAL DATA
│   ├── Program
│   ├── Absensi
│   └── Accident Notes
│
├── ACCESS MANAGEMENT
│   ├── User
│   ├── Role
│   ├── School Plotting
│   └── Coach Assignment
│
└── NOTIFICATION
    └── WhatsApp / WaHa
```

---

# 8. User Roles

Sistem memiliki minimal enam role:

| Role            | Fokus                            |
| --------------- | -------------------------------- |
| **SuperAdmin**  | Full system access               |
| **Relation**    | Master data & operational input  |
| **SPV Coach**   | Management dan assignment Coach  |
| **Coach**       | Pelaksanaan program/pembelajaran |
| **PIC Sekolah** | Absensi sekolah                  |
| **Finance**     | Monitoring dan export absensi    |

---

# 9. Role: SuperAdmin

SuperAdmin memiliki akses penuh terhadap sistem.

## Responsibilities

* User management
* Role management
* School management
* Student management
* Program Kelas management
* Coach management
* Coach assignment
* Program management
* Attendance
* Export
* Accident Notes
* Notification
* PIC management
* School plotting
* System configuration

## Special Requirement

SuperAdmin dapat:

1. membuat akun PIC;
2. menentukan role user;
3. menentukan sekolah yang dapat diakses PIC;
4. mengubah school assignment;
5. mencabut school assignment;
6. mengakses seluruh data.

---

# 10. Role: Relation

## 10.1 Definition

`Relation` merupakan role operasional yang bertanggung jawab terhadap penginputan dan pengelolaan data yang berkaitan dengan sekolah, murid, program kelas, serta kebutuhan program dan absensi.

Role ini merupakan pengganti role `Admin` dalam requirement sebelumnya.

---

## 10.2 Relation Responsibilities

Relation dapat:

### School

* Input data sekolah.
* Mengelola data sekolah sesuai permission yang diberikan.

### Student

* Input data murid.
* Mengelola data murid sesuai permission yang diberikan.

### Program Kelas

* Input program kelas.
* Mengelola program kelas sesuai permission yang diberikan.

### Program

* Input program.

### Attendance

* View attendance.
* Export attendance.

---

# 11. Relation Workflow

```text
Relation
   │
   ├── Sekolah
   │     └── Input Data Sekolah
   │
   ├── Murid
   │     └── Input Data Murid
   │
   ├── Program Kelas
   │     └── Input Program Kelas
   │
   ├── Program
   │     └── Input Program
   │
   └── Absensi
         └── Export Absensi
```

---

# 12. Role: SPV Coach

SPV Coach bertanggung jawab terhadap pengelolaan Coach.

## Responsibilities

* Input data Coach
* Edit data Coach
* Assign Coach
* Reassign Coach
* View Coach assignment
* Export attendance

Requirement ini berasal dari `NoteTambahan.md`.

---

# 13. Role: Coach

Coach merupakan role operasional yang menjalankan kegiatan pembelajaran.

## Responsibilities

* Login
* Melihat assignment
* Menjalankan program pembelajaran
* Melakukan aktivitas pembelajaran sesuai sistem
* Melihat Accident Notes

Detail hak `input program` untuk Coach belum dinyatakan secara eksplisit pada source tambahan. Oleh karena itu, permission final Coach terhadap input program perlu mengikuti hasil audit sistem existing dan keputusan bisnis.

---

# 14. Role: PIC Sekolah

PIC Sekolah merupakan user yang memiliki akses berdasarkan school plotting.

## Responsibilities

* Melihat absensi sekolah.
* Melakukan filter attendance.
* Export attendance.

PIC tidak boleh melihat data sekolah yang tidak menjadi tanggung jawabnya.

---

# 15. Role: Finance

Finance membutuhkan akses untuk monitoring absensi berdasarkan sekolah.

## Responsibilities

* View attendance.
* Filter berdasarkan sekolah.
* View attendance per sekolah.
* Download attendance.
* Export attendance sebagai CSV.

Requirement ini berasal langsung dari `NoteTambahan.md`.

---

# 16. Permission Matrix

| Feature             | SuperAdmin | Relation | SPV Coach | Coach |        PIC | Finance |
| ------------------- | ---------: | -------: | --------: | ----: | ---------: | ------: |
| User Management     |          ✓ |        - |         - |     - |          - |       - |
| Role Management     |          ✓ |        - |         - |     - |          - |       - |
| School Management   |          ✓ |        ✓ |         - |     - | View Scope |   View* |
| Input School        |          ✓ |        ✓ |         - |     - |          - |       - |
| Student Management  |          ✓ |        ✓ |         - |     - |          - |       - |
| Input Student       |          ✓ |        ✓ |         - |     - |          - |       - |
| Program Kelas       |          ✓ |        ✓ |         - |     - |          - |       - |
| Input Program Kelas |          ✓ |        ✓ |         - |     - |          - |       - |
| Program Management  |          ✓ |        ✓ |         ? |   ✓/? |          - |       - |
| Coach Management    |          ✓ |        - |         ✓ |     - |          - |       - |
| Assign Coach        |          ✓ |        - |         ✓ |     - |          - |       - |
| View Attendance     |          ✓ |        ✓ |         ✓ | Scope |          ✓ |       ✓ |
| Export Attendance   |          ✓ |        ✓ |         ✓ |     ? |          ✓ |       ✓ |
| Export CSV          |          ✓ |        ✓ |         ✓ |     ? |          ? |       ✓ |
| Accident Notes      |          ✓ |        ✓ |         ✓ |     ✓ |          ✓ |       ✓ |
| WhatsApp            |          ✓ |        ? |         ? |     ? |          ? |       ? |

`?` berarti requirement belum menentukan secara eksplisit.

`View*` untuk Finance perlu dikonfirmasi apakah Finance dapat melihat master sekolah atau hanya menggunakan sekolah sebagai filter attendance.

---

# 17. Master Data Management

## 17.1 School

Sistem harus memiliki master data sekolah.

Minimal konsep:

```text
School
├── ID
├── Name
├── Code
├── Status
└── Existing metadata
```

Field final harus mengikuti database existing.

---

# 18. Student Management

Relation dapat melakukan input data murid.

Hubungan dasar:

```text
School
   │
   └── Students
```

Sehingga setiap murid harus memiliki hubungan dengan sekolah.

Contoh:

```text
School A
├── Student 001
├── Student 002
└── Student 003
```

---

# 19. Program Kelas Management

Relation dapat menginput program kelas.

Konsep:

```text
School
   │
   └── Class / Program Kelas
```

Program Kelas menjadi salah satu dasar untuk menghubungkan proses pembelajaran.

Detail struktur program kelas harus mengikuti kebutuhan bisnis dan database existing.

---

# 20. Program Management

Program merupakan data operasional pembelajaran.

Konsep hubungan yang perlu diaudit:

```text
School
   ↓
Program Kelas
   ↓
Program
   ↓
Coach
   ↓
Student
   ↓
Attendance
```

Hubungan final tidak boleh ditebak jika struktur existing berbeda.

---

# 21. Coach Management

SPV Coach dapat:

```text
Create Coach
     ↓
Edit Coach
     ↓
Assign Coach
     ↓
View Assignment
     ↓
Attendance / Program
```

SuperAdmin memiliki akses penuh terhadap fungsi tersebut.

---

# 22. School Plotting

School plotting merupakan mekanisme pembatasan akses berdasarkan sekolah.

Konsep:

```text
User
 │
 └── School Assignment
          │
          └── School
```

Contoh:

```text
PIC A → School A
PIC B → School B
PIC C → School C
```

Saat PIC A login:

```text
PIC A
 ↓
Authorization
 ↓
School A Scope
 ↓
Attendance School A
```

PIC A tidak boleh memperoleh data School B.

---

# 23. Attendance Management

Attendance harus mengikuti authorization user.

### SuperAdmin

Dapat melihat seluruh attendance.

### Relation

Dapat melihat attendance sesuai scope permission yang diberikan dan melakukan export.

### SPV Coach

Dapat melihat attendance sesuai operational scope dan melakukan export.

### PIC

Hanya dapat melihat attendance sekolah yang diplot.

### Finance

Dapat melihat attendance berdasarkan sekolah dan melakukan export CSV.

---

# 24. Attendance Export

Export attendance harus mengikuti filter dan authorization.

Flow:

```text
Attendance
    ↓
Apply Role Scope
    ↓
Apply School Filter
    ↓
Apply Date / Other Filters
    ↓
Preview
    ↓
Export
```

Export tidak boleh bypass school-level authorization.

---

# 25. Finance CSV Export

Finance harus memiliki kemampuan:

```text
Finance
 ↓
Attendance
 ↓
Select School
 ↓
Filter
 ↓
Generate CSV
 ↓
Download
```

CSV minimal harus merepresentasikan data attendance yang ditampilkan sesuai filter.

Field final harus mengikuti struktur attendance existing.

---

# 26. Accident Notes

Accident Notes merupakan informasi yang membutuhkan perhatian khusus.

Requirement:

> Blok Accident Notes menggunakan warna merah agar bersifat urgent dan mudah dilihat user.

Contoh konsep:

```text
┌─────────────────────────────────────────┐
│ ⚠ ACCIDENT NOTES                       │
├─────────────────────────────────────────┤
│ Informasi incident / accident...        │
└─────────────────────────────────────────┘
```

Tujuan:

* meningkatkan visibility;
* mengurangi risiko informasi terlewat;
* memberikan visual indicator bahwa informasi bersifat urgent.

---

# 27. WhatsApp Notification

Sistem membutuhkan integrasi WhatsApp menggunakan **WaHa**.

Arsitektur:

```text
Learning Report System
          ↓
 Notification Service
          ↓
       WaHa
          ↓
      WhatsApp
          ↓
      Recipient
```

---

# 28. WhatsApp MVP

Minimum requirement:

* koneksi ke WaHa;
* authentication;
* recipient;
* message;
* send;
* success status;
* failure status;
* logging.

Event yang memicu WhatsApp **belum ditentukan secara eksplisit** pada source.

Oleh karena itu jangan langsung menganggap semua Accident, Attendance, atau Program harus mengirim WhatsApp.

---

# 29. Access Control Architecture

Authorization harus diterapkan pada backend.

```text
Request
   ↓
Route
   ↓
Middleware
   ↓
Policy / Permission
   ↓
Controller
   ↓
Service
   ↓
Query Scope
   ↓
Database
```

Frontend hanya berfungsi sebagai presentation layer.

---

# 30. School Data Isolation

Contoh:

```text
PIC School A
      ↓
GET /attendance?school_id=B
      ↓
Authorization
      ↓
DENIED
```

Sistem tidak boleh hanya menyembunyikan School B pada dropdown.

Backend harus tetap melakukan validasi.

---

# 31. Proposed Data Domain

Struktur domain yang direkomendasikan untuk diaudit:

```text
users
roles
schools
students
classes / program_classes
coaches
coach_assignments
programs
attendance
accident_notes
notifications
school_user
```

**Catatan penting:** daftar tersebut adalah domain proposal dari requirement, bukan pernyataan bahwa semua tabel tersebut belum tersedia.

Developer harus melakukan audit migration dan Model existing terlebih dahulu.

---

# 32. Relationship Concept

Konsep hubungan bisnis:

```text
                  SCHOOL
                    │
             ┌──────┴──────┐
             │             │
         STUDENTS      PROGRAM KELAS
                           │
                           ▼
                        PROGRAM
                           │
                           ▼
                         COACH
                           │
                           ▼
                       ATTENDANCE
```

Sedangkan akses:

```text
USER
 │
 ├── ROLE
 │
 └── SCHOOL ASSIGNMENT
```

---

# 33. User Flow — SuperAdmin

```text
Login
 ↓
Dashboard
 ↓
User Management
 ↓
Create User
 ↓
Select Role
 ↓
If PIC → Select School
 ↓
Save
 ↓
Account Created
```

---

# 34. User Flow — Relation

```text
Login
 ↓
Dashboard
 ↓
School
 ↓
Input School
 ↓
Student
 ↓
Input Student
 ↓
Program Kelas
 ↓
Input Program Kelas
 ↓
Program
 ↓
Input Program
 ↓
Attendance
 ↓
Export Attendance
```

---

# 35. User Flow — SPV Coach

```text
Login
 ↓
Dashboard
 ↓
Coach Management
 ↓
Input Coach
 ↓
Assign Coach
 ↓
Attendance
 ↓
Export
```

---

# 36. User Flow — Coach

```text
Login
 ↓
Dashboard
 ↓
View Assignment
 ↓
Program / Learning Activity
 ↓
Learning Process
 ↓
View Accident Notes
```

---

# 37. User Flow — PIC

```text
Login
 ↓
Dashboard
 ↓
Attendance
 ↓
System Checks School Assignment
 ↓
Show Assigned School
 ↓
Filter
 ↓
Export
```

---

# 38. User Flow — Finance

```text
Login
 ↓
Finance Dashboard
 ↓
Attendance
 ↓
Select School
 ↓
Apply Filter
 ↓
View Attendance
 ↓
Export CSV
```

---

# 39. Functional Requirements

## FR-01 — Authentication

User dapat login berdasarkan account yang dibuat oleh administrator.

**Priority:** P0

---

## FR-02 — Role Management

SuperAdmin dapat mengatur role user.

**Priority:** P0

---

## FR-03 — User Management

SuperAdmin dapat membuat dan mengelola user.

**Priority:** P0

---

## FR-04 — School Management

SuperAdmin dan Relation dapat melakukan input data sekolah sesuai permission.

**Priority:** P0

---

## FR-05 — Student Management

Relation dapat melakukan input data murid.

**Priority:** P0

---

## FR-06 — Program Kelas

Relation dapat melakukan input program kelas.

**Priority:** P0

---

## FR-07 — Coach Management

SPV Coach dapat melakukan input dan pengelolaan Coach.

**Priority:** P0

---

## FR-08 — Coach Assignment

SPV Coach dapat melakukan assignment Coach.

**Priority:** P0

---

## FR-09 — Program Input

Relation dapat melakukan input program.

Hak akses Coach terhadap fungsi input program perlu dikonfirmasi berdasarkan implementation existing.

**Priority:** P0

---

## FR-10 — Attendance

User dapat melihat attendance sesuai role dan scope.

**Priority:** P0

---

## FR-11 — Attendance Export

Relation, SPV Coach, PIC, Finance, dan SuperAdmin dapat melakukan export sesuai permission.

**Priority:** P0

---

## FR-12 — Finance CSV

Finance dapat melakukan export attendance dalam format CSV.

**Priority:** P0

---

## FR-13 — Accident Notes

Accident Notes ditampilkan sebagai blok urgent berwarna merah.

**Priority:** P0

---

## FR-14 — WhatsApp

Sistem dapat mengirim notifikasi melalui WaHa setelah API dan event ditentukan.

**Priority:** P1

---

# 40. Non-Functional Requirements

## NFR-01 Security

Authorization wajib diterapkan di backend.

## NFR-02 Data Isolation

School-level data harus terisolasi.

## NFR-03 Backward Compatibility

Fitur existing tidak boleh rusak.

## NFR-04 Maintainability

Gunakan pola MVC Laravel existing.

## NFR-05 Performance

Attendance filtering dan export dilakukan pada query/database layer.

## NFR-06 Configuration Security

Credential WaHa tidak boleh di-hardcode.

---

# 41. UX Requirements

Menu harus disesuaikan dengan role.

## SuperAdmin

```text
Dashboard
Users
Schools
Students
Program Kelas
Coaches
Programs
Attendance
Reports
Notifications
Settings
```

## Relation

```text
Dashboard
Schools
Students
Program Kelas
Programs
Attendance
Export
```

## SPV Coach

```text
Dashboard
Coaches
Assignment
Attendance
Export
```

## Coach

```text
Dashboard
My Program
Learning Activity
Accident Notes
```

## PIC

```text
Dashboard
Attendance
Export
```

## Finance

```text
Dashboard
Attendance
School Filter
Export CSV
```

Menu yang tidak memiliki permission sebaiknya tidak ditampilkan.

Namun backend tetap harus melakukan authorization.

---

# 42. Database Requirements

Sebelum migration baru dibuat, developer wajib melakukan audit:

```text
database/migrations
        ↓
Models
        ↓
Relationships
        ↓
Existing Controllers
        ↓
Existing Queries
```

Tujuan:

* mencegah duplicate table;
* mencegah duplicate relation;
* mencegah breaking migration;
* menggunakan entity existing jika sudah tersedia.

---

# 43. API / Route Requirements

Route existing harus dipertahankan.

Potential routes:

```text
/users
/users/{id}

/schools
/schools/{id}

/students
/students/{id}

/program-classes
/program-classes/{id}

/coaches
/coaches/{id}
/coaches/{id}/assign

/programs
/programs/{id}

/attendance
/attendance/export

/notifications/whatsapp
```

Daftar ini adalah rancangan konseptual.

Developer harus audit route existing sebelum membuat route baru.

---

# 44. Development Phases

## Phase 1 — Existing System Audit

Audit:

* Models;
* Controllers;
* Routes;
* Middleware;
* Policies;
* Migrations;
* Blade;
* Attendance;
* Program;
* Existing roles;
* Existing export.

Output:

```text
Architecture Map
Database Map
Role Map
Attendance Map
Program Map
```

---

## Phase 2 — Role & Authorization

Implement:

```text
SuperAdmin
Relation
SPV Coach
Coach
PIC
Finance
```

Kemudian:

```text
Middleware
Policy
Permission
School Scope
```

---

## Phase 3 — Master Data

Implement/fix:

```text
School
Student
Program Kelas
Coach
```

---

## Phase 4 — Assignment

Implement:

```text
School Plotting
Coach Assignment
```

---

## Phase 5 — Program

Implement:

```text
Program
Input Program
Program ↔ Class
Program ↔ Coach
```

sesuai struktur existing.

---

## Phase 6 — Attendance

Implement:

```text
Attendance
Filtering
Authorization
Export
```

---

## Phase 7 — Finance

Implement:

```text
Finance Attendance
School Filter
CSV Export
```

---

## Phase 8 — Accident Notes

Implement visual treatment:

```text
Accident Notes
      ↓
Urgent Red Block
```

---

## Phase 9 — WhatsApp

Implement:

```text
Notification Service
      ↓
WaHa Adapter
      ↓
WhatsApp
```

---

## Phase 10 — Testing

Test semua role dan cross-school access.

---

# 45. Acceptance Criteria

## SuperAdmin

* [ ] Dapat login.
* [ ] Dapat membuat user.
* [ ] Dapat menentukan role.
* [ ] Dapat membuat PIC.
* [ ] Dapat menentukan school assignment.
* [ ] Dapat mengubah plotting.
* [ ] Dapat melihat seluruh data.

## Relation

* [ ] Dapat login.
* [ ] Dapat input sekolah.
* [ ] Dapat input murid.
* [ ] Dapat input program kelas.
* [ ] Dapat input program.
* [ ] Dapat melihat attendance sesuai permission.
* [ ] Dapat export attendance.

## SPV Coach

* [ ] Dapat input Coach.
* [ ] Dapat edit Coach.
* [ ] Dapat assign Coach.
* [ ] Dapat melihat assignment.
* [ ] Dapat export attendance.

## Coach

* [ ] Dapat login.
* [ ] Dapat melihat assignment.
* [ ] Dapat menjalankan aktivitas program.
* [ ] Dapat melihat Accident Notes.
* [ ] Accident Notes memiliki visual urgent merah.

## PIC

* [ ] Dapat login.
* [ ] Hanya dapat melihat sekolah yang diplot.
* [ ] Dapat melihat attendance.
* [ ] Dapat export attendance.
* [ ] Tidak dapat mengakses school lain melalui URL/API.

## Finance

* [ ] Dapat login.
* [ ] Dapat melihat attendance.
* [ ] Dapat memilih sekolah.
* [ ] Dapat melakukan filtering.
* [ ] Dapat export CSV.

---

# 46. Regression Testing

Fitur existing wajib dites kembali:

* [ ] Login
* [ ] Logout
* [ ] Existing dashboard
* [ ] Existing learning report
* [ ] Existing program
* [ ] Existing attendance
* [ ] Existing export
* [ ] Cloudinary
* [ ] Database migration
* [ ] Vite assets
* [ ] Docker configuration

---

# 47. Security Test

Minimal test:

```text
PIC School A
      ↓
Request School A
      ↓
ALLOW
```

dan:

```text
PIC School A
      ↓
Request School B
      ↓
DENY
```

Test juga harus dilakukan melalui:

* UI;
* direct URL;
* query parameter;
* API jika tersedia.

---

# 48. Risk Register

| Risk                                  | Impact   | Mitigation                    |
| ------------------------------------- | -------- | ----------------------------- |
| Admin → Relation tidak konsisten      | High     | Audit seluruh role references |
| PIC dapat melihat sekolah lain        | Critical | Policy + query scope          |
| Relation mendapat akses terlalu besar | High     | Explicit permission           |
| Migration merusak existing DB         | High     | Backup + migration testing    |
| Duplicate student/school entity       | Medium   | Audit existing models         |
| Duplicate attendance logic            | High     | Reuse existing attendance     |
| WaHa unavailable                      | Medium   | Retry + logging               |
| Export terlalu besar                  | Medium   | Query optimization            |
| Existing feature rusak                | High     | Regression testing            |

---

# 49. Open Questions

Requirement berikut masih perlu dikonfirmasi sebelum implementation final.

## Q1 — Relation Edit/Delete

Saat ini requirement menyebut Relation dapat **menginput**:

* sekolah;
* murid;
* program kelas.

Belum disebutkan apakah Relation juga dapat:

* edit;
* delete;
* deactivate.

Untuk sementara:

> **Input/Create = Confirmed**
> **Edit/Delete = TBD**

---

## Q2 — Relation School Scope

Apakah Relation:

```text
All Schools
```

atau:

```text
Assigned Schools Only
```

?

---

## Q3 — Relation Input Program

Apakah Relation dapat membuat:

```text
Program
```

atau hanya:

```text
Program Kelas
```

?

Requirement terbaru menyebut keduanya, sehingga PRD saat ini menganggap keduanya sebagai fitur berbeda.

---

## Q4 — Coach Assignment

Coach di-assign berdasarkan:

* Sekolah?
* Program Kelas?
* Program?
* Jadwal?
* kombinasi?

Belum ditentukan oleh source.

---

## Q5 — PIC Multi-School

Apakah satu PIC dapat menangani:

```text
1 School
```

atau:

```text
Multiple Schools
```

?

---

## Q6 — Finance Scope

Apakah Finance dapat melihat:

```text
All Schools
```

atau hanya sekolah tertentu?

---

## Q7 — WhatsApp Trigger

Event yang memicu WhatsApp belum ditentukan.

Kemungkinan:

* Accident;
* Attendance;
* Program;
* Report;
* lainnya.

Tidak boleh diasumsikan sebelum dikonfirmasi.

---

## Q8 — WaHa API

Diperlukan:

* API documentation;
* endpoint;
* authentication;
* session;
* request format;
* response format.

---

# 50. Priority

## P0 — Must Have

1. SuperAdmin
2. Relation
3. SPV Coach
4. Coach
5. PIC Sekolah
6. Finance
7. School Management
8. Student Management
9. Program Kelas
10. Coach Management
11. Coach Assignment
12. School Plotting
13. Attendance
14. Attendance Export
15. Finance CSV
16. Accident Notes urgent styling

## P1 — Should Have

1. WaHa integration
2. Notification logging
3. Assignment history
4. Audit log

## P2 — Could Have

1. Advanced notification templates
2. Notification dashboard
3. Scheduled reports
4. Advanced analytics

---

# 51. Definition of Done

Feature dianggap selesai apabila:

* [ ] Requirement telah diimplementasikan.
* [ ] Role permission telah diterapkan.
* [ ] Backend authorization tersedia.
* [ ] School scope tersedia jika relevan.
* [ ] UI sesuai role.
* [ ] Validation tersedia.
* [ ] Error handling tersedia.
* [ ] Database migration aman.
* [ ] Existing feature tidak rusak.
* [ ] Test berhasil.
* [ ] Tidak ada credential hard-coded.
* [ ] Tidak ada privilege escalation.
* [ ] Cross-school access telah diuji.

---

# 52. Recommended Implementation Strategy

Development **tidak langsung dilakukan dengan coding seluruh fitur**.

Gunakan urutan:

```text
contextproject.md
        +
NoteTambahan.md
        +
PRD v2
        ↓
Existing Project Audit
        ↓
Database & Model Mapping
        ↓
Role & Permission Design
        ↓
School Scope Design
        ↓
Implementation Plan
        ↓
Migration
        ↓
Backend
        ↓
Frontend
        ↓
Export
        ↓
Notification
        ↓
Testing
        ↓
Regression
```

---

# 53. Target Architecture

Target konseptual sistem:

```text
                         LEARNING REPORT SYSTEM
                                  │
          ┌───────────────────────┼───────────────────────┐
          │                       │                       │
          ▼                       ▼                       ▼
     MASTER DATA            OPERATIONAL DATA       ACCESS CONTROL
          │                       │                       │
    ┌─────┼─────┐          ┌──────┼──────┐         ┌─────┼─────┐
    │     │     │          │      │      │         │     │     │
 School Student Program   Program Attendance Accident User  Role
              Kelas                           Notes
    │                                               │
    └───────────────────────────────────────────────┤
                                                    │
                                            School Plotting
                                            Coach Assignment

                                  │
                                  ▼
                            NOTIFICATION
                                  │
                                  ▼
                                WaHa
                                  │
                                  ▼
                             WhatsApp
```

---

# 54. Final Role Architecture

Struktur role final untuk versi PRD ini:

```text
SUPERADMIN
│
├── Full System Access
├── User Management
├── School Management
├── Student Management
├── Program Kelas
├── Coach Management
├── Coach Assignment
├── Attendance
├── Export
└── School Plotting
        │
        ├───────────────┐
        ▼               ▼
     RELATION       PIC SEKOLAH
        │               │
        ├── School      └── Attendance
        ├── Student         └── Export
        ├── Program Kelas
        ├── Program
        └── Attendance
              └── Export

SPV COACH
│
├── Coach Management
├── Assign Coach
└── Attendance Export

COACH
│
├── Assigned Program
├── Learning Activity
└── Accident Notes

FINANCE
│
├── Attendance
├── School Filter
└── CSV Export
```

---

# 55. Product Success Criteria

Pengembangan dianggap berhasil apabila:

1. `Admin` telah digantikan secara konseptual oleh `Relation` pada seluruh workflow yang relevan.
2. Relation dapat menginput data Sekolah.
3. Relation dapat menginput data Murid.
4. Relation dapat menginput Program Kelas.
5. Relation dapat melakukan Input Program sesuai permission.
6. Relation dapat melakukan Export Absensi.
7. SPV Coach dapat input dan assign Coach.
8. PIC Sekolah hanya dapat mengakses sekolah yang diplot.
9. Finance dapat melihat attendance berdasarkan sekolah.
10. Finance dapat export attendance ke CSV.
11. SuperAdmin memiliki full access.
12. SuperAdmin dapat membuat akun PIC.
13. SuperAdmin dapat menentukan school plotting PIC.
14. Accident Notes memiliki visual emphasis merah.
15. Integrasi WhatsApp melalui WaHa dapat dilakukan setelah detail API dan trigger ditentukan.
16. Tidak terjadi privilege escalation.
17. Existing functionality tetap berjalan setelah update.

---

# 56. Final Development Principle

PRD ini harus diperlakukan sebagai **requirement baseline**, bukan instruksi untuk membangun ulang sistem.

Prinsip implementasi:

> **Audit → Understand → Extend → Secure → Test → Preserve**

`contextproject.md` tetap menjadi baseline teknis proyek. `NoteTambahan.md` menjadi sumber requirement tambahan. Update terbaru mengenai perubahan **Admin → Relation** menjadi bagian dari PRD versi 2 ini.
Developer/AI **tidak boleh mengasumsikan requirement yang belum ditentukan**, terutama terkait:

* hak edit/delete Relation;
* scope Relation;
* multi-school PIC;
* Coach assignment;
* Finance scope;
* trigger WhatsApp;
* detail API WaHa.

Hal-hal tersebut harus dikonfirmasi atau ditentukan melalui tahap **Implementation Planning** sebelum coding.
