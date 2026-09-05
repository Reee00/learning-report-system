# Fitur dan Modul

- Authentication: login/logout session.
- Master data: School, Class, Program, Student, User/Coach.
- Coach assignment: assign/reassign Coach ke Class melalui `CoachClass`.
- Student management: CRUD terbatas scope dan import XLSX/XLS/CSV via FastExcel.
- Coach Report: draft, edit, submit, media, attendance, review, approve/reject/resubmit.
- Attendance: query dengan scope role, filter, CSV matrix, dan PDF.
- School portal: PIC dashboard/report approved; TEACHER SCHOOL memakai view bersama; Finance memakai attendance dan CSV capability.
- Media: local private filesystem, metadata `ReportMedia`, authorized serving, legacy external URL compatibility.
- AJAX: daftar siswa class yang sudah diotorisasi.

Belum ada API token service, PWA, permissions table, atau UI terpisah khusus Finance/TEACHER SCHOOL/Relation.
