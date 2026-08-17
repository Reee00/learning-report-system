# Data Model Map

Tanggal: 14 Agustus 2026  
Phase: 1 — Architecture & Data Mapping  
Status: mapping berdasarkan codebase aktual; belum merupakan migration plan final.

## Mapping Summary

Codebase sudah memiliki fondasi master data dan learning report. Entity yang sudah ada harus diperluas, bukan diduplikasi.

Struktur aktual:

    School
      └── classes (SchoolClass)
            └── students

    User (role string)
      ├── school_id untuk PIC satu sekolah
      └── coach_classes ── classes untuk assignment Coach

    Report
      ├── coach_id ── users
      ├── school_id ── schools
      ├── class_id ── classes
      ├── report_attendances ── students
      └── report_media

Entity Program, ProgramClass, Notification, Accident Notes, dan school plotting pivot belum tersedia pada codebase. Keputusan Phase 1 menetapkan desain minimal untuk Program/ProgramClass dan plotting PIC; implementasinya tetap dilakukan pada phase terkait.

## Entity Mapping

| Entity | Existing table | Existing model | Primary key | Foreign keys | Migration |
|---|---|---|---|---|---|
| User | users | App\Models\User | id | school_id → schools.id | 2026_02_28_214726_create_users_table.php |
| Role | Tidak ada table/model | String pada User | — | — | Enum pada migration users |
| School | schools | App\Models\School | id | — | 2026_02_28_214640_create_schools_table.php |
| Program Kelas / Class | classes | App\Models\SchoolClass | id | school_id → schools.id | 2026_02_28_214748_create_classes_table.php |
| Student | students | App\Models\Student | id | class_id → classes.id | 2026_02_28_215654_create_students_table.php |
| Coach | Tidak ada table khusus | User dengan role = coach | users.id | — | users migration |
| Coach Assignment | coach_classes | App\Models\CoachClass | id | coach_id → users.id, class_id → classes.id | 2026_02_28_215718_create_coach_classes_table.php |
| Report | reports | App\Models\Report | id | coach_id, school_id, class_id, approved_by | 2026_02_28_215822_create_reports_table.php |
| Attendance | report_attendances | App\Models\ReportAttendance | id | report_id → reports.id, student_id → students.id | 2026_02_28_215901_create_report_attendances_table.php |
| Report Media | report_media | App\Models\ReportMedia | id | report_id → reports.id | 2026_03_01_215607_create_report_media_table.php |
| Session | sessions | Laravel session driver | id | nullable user_id tanpa FK | 2026_02_28_230827_create_sessions_table.php |
| Program | Tidak ada | Tidak ada | — | — | Belum ada |
| ProgramClass | Tidak ada | Tidak ada | — | program_id dan class_id belum ada | Belum ada; association entity yang direncanakan |
| Accident Notes | Tidak ada | Tidak ada | — | — | Belum ada |
| Notification | Tidak ada | Tidak ada | — | — | Belum ada |
| School Plotting | Tidak ada pivot | Tidak ada | — | Rencana user_id → users.id dan school_id → schools.id | Belum ada; many-to-many PIC |

## Existing Entity Detail

### User / Role

Kolom aktual: id, name, email, password, role enum (admin, coach, school_pic), nullable school_id, dan timestamps.

User memiliki relasi school() dan coachClasses(). Role belum menjadi entity atau package permission.

Rekomendasi:

- pertahankan User sebagai authentication model;
- jangan membuat tabel User/Coach baru;
- tentukan canonical role key sebelum migration;
- pisahkan full-access SuperAdmin dari permission operasional Relation;
- tentukan apakah school_id tetap single-school atau ditambah pivot plotting.

### School

Kolom aktual: id, name, nullable address, nullable pic_name, dan timestamps.

Relasi: hasMany SchoolClass dan hasMany User.

Controller/view: Admin\SchoolController dan resources/views/admin/master/schools.blade.php. Entity ini dapat dipakai ulang untuk Relation dan SuperAdmin.

### Program Kelas / SchoolClass

Entity aktual adalah SchoolClass pada table classes. Entity ini tetap menjadi representasi kelas sekolah yang dapat direuse. Jika satu kelas membutuhkan konteks program yang berbeda, konteks tersebut direpresentasikan melalui entity association ProgramClass, bukan dengan menduplikasi row classes.

Kolom: id, school_id, name, timestamps.

Relasi: belongsTo School, hasMany Student, hasMany CoachClass.

Keputusan Phase 1: reuse classes untuk kelas sekolah yang sama; gunakan ProgramClass untuk hubungan program-specific antara Program dan SchoolClass ketika terdapat program berbeda pada kelas/sekolah.

Minimum association yang direkomendasikan untuk ProgramClass: id, program_id, class_id, dan timestamps. Unique constraint minimal adalah program_id + class_id.

### Student

Kolom: id, class_id, name, created_at. Table tidak memiliki updated_at.

Relasi: belongsTo SchoolClass. Student memiliki school scope secara tidak langsung melalui class_id → classes.school_id.

Flow existing: input manual, import xlsx/xls/csv menggunakan Fast-Excel, delete, dan download template CSV.

### Coach dan Coach Assignment

Coach tidak memiliki model/table tersendiri. Akun Coach adalah User dengan role = coach.

Assignment disimpan di coach_classes:

- id;
- coach_id;
- class_id;
- unique pair coach_id + class_id;
- tanpa timestamps.

Assignment saat ini berarti Coach → Class. Belum ada assignment ke Program, Schedule, atau School secara langsung.

### Report

Kolom aktual: id, coach_id, school_id, class_id, report_date, lesson_material, activity_summary, notes, photo_path legacy, status, admin_notes, approved_by, approved_at, dan timestamps.

Relasi: Coach/User, School, SchoolClass, approver/User, ReportAttendance, dan ReportMedia.

lesson_material dan activity_summary adalah input pembelajaran langsung pada Report; keduanya bukan bukti adanya entity Program.

### Attendance

Attendance disimpan per report dan student: report_id, student_id, dan status (present, absent, sick, permission).

Input dilakukan Coach saat membuat/mengedit Report. Read dilakukan Admin dan PIC melalui detail report. Belum ada aggregate attendance query khusus untuk export dan Finance.

### Report Media

Media report terdiri dari report_id, type (photo/video), path, original_name, dan timestamps. Upload menggunakan CloudinaryHelper.

### Program dan ProgramClass (planned)

Program menjadi entity reusable lintas sekolah dengan minimum field: id, name, nullable code, nullable description, status, dan timestamps. Program tidak perlu memiliki school_id langsung; school scope diturunkan melalui ProgramClass → SchoolClass → School.

ProgramClass menjadi association entity antara Program dan existing SchoolClass. Dengan pola ini, SchoolClass yang sama dapat direuse, sementara perbedaan program pada beberapa sekolah/class memiliki row association yang berbeda tanpa duplicate class master.

## Target Entity Mapping

| Target PRD | Reuse existing | Gap | Extension point |
|---|---|---|---|
| User | users / User | role target belum ada | role migration + authorization layer |
| Role | users.role sementara | belum ada role/permission model | pilih enum/string atau package |
| School | schools / School | akses Relation belum ada | extend controller authorization |
| Student | students / Student | Relation access belum ada | reuse class-school relationship |
| Program Kelas | classes / SchoolClass + planned ProgramClass | aturan reuse sudah ditetapkan | implement association entity saat Phase 6/8 |
| Program | Tidak ada | planned reusable entity | implement saat Phase 8 dengan relationship ProgramClass |
| Coach | User | SPV management belum ada | reuse User + role authorization |
| Coach Assignment | coach_classes / CoachClass | SPV authorization belum ada | reuse; jangan membuat duplicate pivot |
| Attendance | reports + report_attendances | view/export/scope belum ada | shared scoped query/export service |
| Accident Notes | Tidak ada | field/model belum jelas | tentukan apakah reports.notes cukup |
| Notification | Tidak ada | provider/API contract belum ada | adapter setelah WaHa contract |
| School plotting | planned school_user pivot; users.school_id legacy | multi-school belum didukung | implement many-to-many untuk PIC pada Phase 9 |

## Relationship Map

- School 1 — N SchoolClass.
- School 1 — N User pada desain PIC saat ini.
- SchoolClass 1 — N Student.
- User Coach 1 — N CoachClass — 1 SchoolClass.
- User Coach 1 — N Report.
- School 1 — N Report.
- SchoolClass 1 — N Report.
- Report 1 — N ReportAttendance — 1 Student.
- Report 1 — N ReportMedia.
- User 1 — N Report sebagai approved_by.

Target relationship yang belum dapat diputuskan:

- Program 1 — N ProgramClass.
- ProgramClass N — 1 SchoolClass.
- ProgramClass memperoleh school scope melalui SchoolClass.
- PIC User N — N School melalui planned school_user pivot.
- Program ↔ Coach belum dibuat langsung; assignment tetap mengikuti CoachClass sampai requirement menetapkan hubungan baru.

## Mapping ke Controller, Route, dan View

| Entity/flow | Controller | Route family | View | Status |
|---|---|---|---|---|
| User | Admin\UserController | admin.users.* | admin/users/index | existing admin-only |
| School | Admin\SchoolController | admin.schools.* | admin/master/schools | existing admin-only |
| Class | Admin\ClassController | admin.classes.* | admin/master/classes | existing admin-only |
| Student | StudentController | students.* dan AJAX | students/index | existing manual scope |
| Coach/assignment | Admin\CoachController | admin.coaches.* | admin/master/coaches dan coach_show | existing admin-only |
| Report/attendance input | Coach\ReportController | coach.reports.* | coach/reports/* | existing coach-only |
| Report review | Admin\ReportController | admin.reports.* | admin/reports/* | existing admin-only |
| PIC report read | SchoolPic\DashboardController | pic.* | school_pic/* | existing one-school scope |
| Program | — | — | — | absent |
| Attendance export | — | — | — | absent |
| School plotting | — | — | — | absent |
| Notification/WaHa | — | — | — | absent |

## Schema and Migration Implications

Belum ada migration yang boleh dibuat hanya berdasarkan asumsi untuk:

- Program relation;
- Accident Notes schema;
- PIC multi-school plotting;
- final role keys;
- permission storage.

Perubahan yang akan dibutuhkan setelah keputusan Phase 1:

1. role migration yang aman terhadap enum dan existing users;
2. permission/authorization storage jika tidak memakai role-only middleware;
3. school plotting pivot many-to-many untuk PIC;
4. Program dan ProgramClass tables sesuai desain hybrid di atas;
5. optional domain fields untuk Accident Notes;
6. indexes/constraints untuk scoped attendance dan export.

Tidak boleh ada drop atau destructive rename sebelum foreign-key impact dan backup strategy diverifikasi.

## Phase 1 Decisions / Remaining TBD

Keputusan user yang sudah dicatat:

- existing admin dimigrasikan menjadi Relation;
- role SuperAdmin baru ditambahkan dengan akses penuh;
- PIC memakai many-to-many school plotting;
- SchoolClass direuse jika sama;
- ProgramClass dibuat sebagai association entity ketika konteks program berbeda;
- Program memakai minimum fields dan relationship yang direkomendasikan;
- authorization memakai Policy/service/query scope terpusat, tanpa permission package baru pada fase ini.

Remaining TBD non-blocking:

- apakah Accident Notes cukup menggunakan reports.notes atau membutuhkan field/domain khusus;
- detail field tambahan ProgramClass jika kebutuhan bisnis berkembang.

## Phase 1 Recommendation

Pertahankan User, School, SchoolClass, Student, CoachClass, Report, ReportAttendance, dan ReportMedia. Tambahkan Program, ProgramClass, dan school_user secara incremental pada phase terkait; jangan menduplikasi SchoolClass atau membuat Coach model baru.
