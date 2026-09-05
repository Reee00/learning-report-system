# Struktur Folder

- `app/Models/`: User, School, SchoolClass, Program, ProgramClass, Student, CoachClass, Report, ReportAttendance, ReportMedia.
- `app/Http/Controllers/`: Auth, Admin, Coach, SchoolPic, Attendance, Student, Media.
- `app/Http/Middleware/`: auth role/permission middleware.
- `app/Services/`: Authorization, AttendanceScope, AttendanceExport, MediaStorage.
- `app/Console/Commands/`: termasuk `media:migrate-cloudinary` untuk kompatibilitas legacy.
- `database/migrations/`: schema dan perubahan role/media; saat audit berisi 14 file.
- `resources/views/`: Blade layout/sidebar, admin compatibility views, coach, school PIC, attendance, student, report, media.
- `resources/css/`, `resources/js/`: asset source; layout utama menggunakan Bootstrap CDN.
- `routes/web.php`: seluruh route aplikasi.
- `tests/Feature/`, `tests/Unit/`: test authorization, scope, report, master data, media, student, school, dan flow.

Nama folder `Admin` dan prefix `admin` dipertahankan untuk kompatibilitas route/controller, bukan role operasional.
