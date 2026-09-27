<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Coach\ReportController as CoachReportController;
use App\Http\Controllers\Coach\StudentController as CoachStudentController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\Admin\ProgramController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SchoolPic\DashboardController as PicDashboard;
use Illuminate\Support\Facades\Route;

// ===== PUBLIC ROUTES =====
Route::get('/', fn() => redirect()->route('login'));
Route::get('/login', [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ===== ATTENDANCE SCOPE AND EXPORT =====
// UX 2026-09-13: hierarki drill-down Attendance → Sekolah → Kelas → Tanggal → Murid.
Route::get('/attendance', [AttendanceController::class, 'index'])
    ->middleware(['auth', 'permission:attendance.view'])
    ->name('attendance.index');
Route::get('/attendance/schools/{school}', [AttendanceController::class, 'showSchool'])
    ->middleware(['auth', 'permission:attendance.view'])
    ->name('attendance.school');
Route::get('/attendance/schools/{school}/classes/{class}', [AttendanceController::class, 'showClass'])
    ->middleware(['auth', 'permission:attendance.view'])
    ->name('attendance.class');
Route::get('/attendance/sessions/{report}', [AttendanceController::class, 'showSession'])
    ->middleware(['auth', 'permission:attendance.view'])
    ->name('attendance.session');
// Akumulasi kehadiran (TOTAL HADIR) HANYA ada di dokumen unduh/cetak
// (CSV/PDF) — bukan di halaman index/detail (keputusan UX 2026-09-13).
Route::get('/attendance/export', [AttendanceController::class, 'export'])
    ->middleware(['auth', 'permission_any:attendance.export,attendance.export_csv'])
    ->name('attendance.export');

// ===== STUDENT ROUTES =====
Route::middleware('auth')->group(function () {
    Route::get('/classes/{class}/students', [StudentController::class, 'show'])
        ->middleware('permission:students.view')
        ->name('students.show');
    Route::post('/classes/{class}/students', [StudentController::class, 'store'])
        ->middleware('permission:students.create')
        ->name('students.store');
    Route::post('/classes/{class}/students/import', [StudentController::class, 'import'])
        ->middleware('permission:students.create')
        ->name('students.import');
    Route::delete('/classes/{class}/students/{student}', [StudentController::class, 'destroy'])
        ->middleware('permission:students.delete')
        ->name('students.destroy');
    Route::get('/students/template', [StudentController::class, 'template'])
        ->middleware('permission:students.view')
        ->name('students.template');
});

// ===== COACH REPORT ROUTES =====
// Coach report routes remain role-scoped and now also require the relevant capability.
Route::middleware(['auth', 'role:coach'])->prefix('coach')->name('coach.')->group(function () {
    Route::get('reports', [CoachReportController::class, 'index'])
        ->middleware('permission:reports.view')
        ->name('reports.index');
    Route::get('reports/create', [CoachReportController::class, 'create'])
        ->middleware('permission:reports.create')
        ->name('reports.create');
    Route::post('reports', [CoachReportController::class, 'store'])
        ->middleware('permission:reports.create')
        ->name('reports.store');
    Route::get('reports/{report}/edit', [CoachReportController::class, 'edit'])
        ->middleware('permission:reports.update')
        ->name('reports.edit');
    Route::put('reports/{report}', [CoachReportController::class, 'update'])
        ->middleware('permission:reports.update')
        ->name('reports.update');
    Route::get('reports/{report}/download', [CoachReportController::class, 'download'])
        ->middleware('permission:reports.download')
        ->name('reports.download');

    // Coach: view list of assigned classes and manage their students.
    // class_id is always resolved from the coach assignment in the backend.
    Route::get('students', [CoachStudentController::class, 'index'])
        ->middleware('permission:students.view')
        ->name('students.index');

    // Tandai notifikasi reminder laporan sebagai sudah dibaca.
    Route::post('notifications/{id}/read', [\App\Http\Controllers\Coach\NotificationController::class, 'read'])
        ->name('notifications.read');
});


// ===== RELATION / SUPERADMIN COMPATIBILITY ROUTES =====
// URL dan route names admin.* dipertahankan; capability authorization dilakukan
// melalui permission middleware dan AuthorizationService.
Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [AdminDashboard::class, 'index'])
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    // User management: SuperAdmin only through users.manage.
    Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index'])
        ->middleware('permission:users.manage')
        ->name('users.index');
    Route::post('users', [\App\Http\Controllers\Admin\UserController::class, 'store'])
        ->middleware('permission:users.manage')
        ->name('users.store');
    Route::put('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])
        ->middleware('permission:users.manage')
        ->name('users.update');
    Route::patch('users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])
        ->middleware('permission:users.manage')
        ->name('users.reset-password');
    Route::delete('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])
        ->middleware('permission:users.manage')
        ->name('users.destroy');

    // Activity log (audit keamanan 2026-09-11): SuperAdmin only.
    Route::get('activity-logs', [\App\Http\Controllers\Admin\ActivityLogController::class, 'index'])
        ->middleware('permission:users.manage')
        ->name('activity-logs.index');
    Route::get('activity-logs/{log}', [\App\Http\Controllers\Admin\ActivityLogController::class, 'show'])
        ->middleware('permission:users.manage')
        ->name('activity-logs.show');

    // Report review console: listing and detail use reports.view_all so that
    // Relation, SPV Coach, PIC, Teacher, and SuperAdmin can browse reports.
    // Coach has reports.view (own reports only) and cannot access this console.
    // Only the approve/reject actions require reports.review (Relation + SuperAdmin).
    Route::get('reports', [AdminReportController::class, 'index'])
        ->middleware('permission:reports.view_all')
        ->name('reports.index');
    Route::get('reports/{report}', [AdminReportController::class, 'show'])
        ->middleware('permission:reports.view_all')
        ->name('reports.show');
    Route::patch('reports/{report}/approve', [AdminReportController::class, 'approve'])
        ->middleware('permission:reports.review')
        ->name('reports.approve');
    Route::patch('reports/{report}/reject', [AdminReportController::class, 'reject'])
        ->middleware('permission:reports.review')
        ->name('reports.reject');
    Route::get('reports/{report}/download', [AdminReportController::class, 'download'])
        ->middleware('permission:reports.download')
        ->name('reports.download');
    // Reminder laporan (meeting 2026-09 req. D): Relation/SuperAdmin dapat
    // mengingatkan semua coach menunggak; PIC memakai route pic.sendReminder.
    Route::post('reports/remind', [AdminReportController::class, 'remind'])
        ->middleware('permission:reports.remind')
        ->name('reports.remind');

    // Composer notifikasi custom ke coach (audit UX 2026-09-11): Relation
    // global, PIC scope sekolah plot (dipaksa di service).
    Route::get('notifications/create', [\App\Http\Controllers\Admin\NotificationController::class, 'create'])
        ->middleware('permission:notifications.send')
        ->name('notifications.create');
    Route::post('notifications', [\App\Http\Controllers\Admin\NotificationController::class, 'store'])
        ->middleware('permission:notifications.send')
        ->name('notifications.store');

    // Teaching schedule management module: full CRUD + Excel import.
    // Visibility scoped per role in the controller — SuperAdmin/Relation
    // global, PIC plotted schools (may also manage them), Coach only
    // schedules involving them. Manage/import requires schedules.manage.
    Route::get('schedules', [\App\Http\Controllers\Admin\ScheduleController::class, 'index'])
        ->middleware('permission:schedules.view')
        ->name('schedules.index');
    Route::get('schedules/create', [\App\Http\Controllers\Admin\ScheduleController::class, 'create'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.create');
    Route::post('schedules', [\App\Http\Controllers\Admin\ScheduleController::class, 'store'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.store');
    // Form bulk mingguan DIGISchool (2026-09-24): banyak sekolah/kelas/coach
    // dalam satu pengiriman, per hari Senin–Sabtu.
    Route::post('schedules/bulk', [\App\Http\Controllers\Admin\ScheduleController::class, 'storeBulk'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.bulk.store');
    // Roster murid kelas (master students) untuk form jadwal.
    Route::get('schedules/class-students/{class}', [\App\Http\Controllers\Admin\ScheduleController::class, 'classStudents'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.class-students');

    // Detail satu pola + aksi tingkat pola (generate ulang / hapus pola).
    // Dipakai §5: daftar utama menampilkan POLA per hari; 20 pertemuan
    // tergenerate tetap dapat dilihat di sini.
    //
    // WAJIB didaftarkan SEBELUM `schedules/{schedule}` di bawah: rute
    // DELETE schedules/{schedule} akan menangkap "schedules/pattern" lebih
    // dulu (model binding gagal -> 404) bila urutannya terbalik.
    Route::get('schedules/pattern', [\App\Http\Controllers\Admin\ScheduleController::class, 'patternShow'])
        ->middleware('permission:schedules.view')
        ->name('schedules.pattern.show');
    Route::post('schedules/pattern/generate', [\App\Http\Controllers\Admin\ScheduleTemplateController::class, 'generatePattern'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.pattern.generate');
    Route::delete('schedules/pattern', [\App\Http\Controllers\Admin\ScheduleTemplateController::class, 'destroyPattern'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.pattern.destroy');

    // Daftar pola (per hari + sekolah) dan aksi per baris kelas.
    Route::get('schedules/templates', [\App\Http\Controllers\Admin\ScheduleTemplateController::class, 'index'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.templates');
    Route::post('schedules/templates/{template}/generate', [\App\Http\Controllers\Admin\ScheduleTemplateController::class, 'generate'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.templates.generate');
    Route::delete('schedules/templates/{template}', [\App\Http\Controllers\Admin\ScheduleTemplateController::class, 'destroy'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.templates.destroy');

    Route::get('schedules/{schedule}/edit', [\App\Http\Controllers\Admin\ScheduleController::class, 'edit'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.edit');
    Route::put('schedules/{schedule}', [\App\Http\Controllers\Admin\ScheduleController::class, 'update'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.update');
    Route::post('schedules/import', [\App\Http\Controllers\Admin\ScheduleController::class, 'import'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.import');
    Route::get('schedules/template', [\App\Http\Controllers\Admin\ScheduleController::class, 'template'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.template');
    Route::delete('schedules/{schedule}', [\App\Http\Controllers\Admin\ScheduleController::class, 'destroy'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.destroy');
    // Status aktif/nonaktif per sesi: sesi nonaktif tetap tersimpan sebagai
    // riwayat, hanya tidak dihitung sebagai sesi mengajar aktif.
    Route::patch('schedules/{schedule}/active', [\App\Http\Controllers\Admin\ScheduleController::class, 'toggleActive'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.toggle-active');

    // Pola jadwal per HARI + SEKOLAH (refactor 2026-09-25).
    //
    // Tidak ada lagi periode/start-date global: satu pola adalah kelompok baris
    // (day_of_week, school_id, start_date), sehingga dua sekolah pada hari yang
    // sama boleh punya tanggal mulai dan jumlah pertemuan berbeda. Rute
    // `semester` / `semester.store` dipertahankan sebagai pengalihan agar
    // tautan lama tidak mati.
    Route::get('schedules/semester', [\App\Http\Controllers\Admin\ScheduleTemplateController::class, 'semester'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.semester');
    Route::post('schedules/semester', [\App\Http\Controllers\Admin\ScheduleTemplateController::class, 'store'])
        ->middleware('permission:schedules.manage')
        ->name('schedules.semester.store');

    // School master data.
    Route::get('schools', [SchoolController::class, 'index'])
        ->middleware('permission:schools.view')
        ->name('schools.index');
    Route::get('schools/{school}', [SchoolController::class, 'show'])
        ->middleware('permission:schools.view')
        ->name('schools.show');
    Route::post('schools', [SchoolController::class, 'store'])
        ->middleware('permission:schools.create')
        ->name('schools.store');
    Route::put('schools/{school}', [SchoolController::class, 'update'])
        ->middleware('permission:schools.update')
        ->name('schools.update');
    Route::delete('schools/{school}', [SchoolController::class, 'destroy'])
        ->middleware('permission:schools.delete')
        ->name('schools.destroy');

    // School Workspace (2026-09-24): assign/edit/hapus kelas dan program
    // langsung dari halaman sekolah. Memakai master data yang sama
    // (classes + program_classes) — tidak ada tabel kelas kedua.
    Route::post('schools/{school}/classes', [SchoolController::class, 'storeClass'])
        ->middleware('permission:program_classes.create')
        ->name('schools.classes.store');
    Route::put('schools/{school}/classes/{class}', [SchoolController::class, 'updateClass'])
        ->middleware('permission:program_classes.update')
        ->name('schools.classes.update');
    Route::delete('schools/{school}/classes/{class}', [SchoolController::class, 'destroyClass'])
        ->middleware('permission:program_classes.delete')
        ->name('schools.classes.destroy');

    // SchoolClass / Program Kelas master data.
    Route::get('classes', [ClassController::class, 'index'])
        ->middleware('permission:program_classes.view')
        ->name('classes.index');
    Route::post('classes', [ClassController::class, 'store'])
        ->middleware('permission:program_classes.create')
        ->name('classes.store');
    Route::put('classes/{class}', [ClassController::class, 'update'])
        ->middleware('permission:program_classes.update')
        ->name('classes.update');
    Route::delete('classes/{class}', [ClassController::class, 'destroy'])
        ->middleware('permission:program_classes.delete')
        ->name('classes.destroy');

    // Reusable Program and its ProgramClass associations.
    Route::get('programs', [ProgramController::class, 'index'])
        ->middleware('permission:programs.view')
        ->name('programs.index');
    Route::post('programs', [ProgramController::class, 'store'])
        ->middleware('permission:programs.create')
        ->name('programs.store');
    Route::get('programs/{program}', [ProgramController::class, 'show'])
        ->middleware('permission:programs.view')
        ->name('programs.show');
    Route::put('programs/{program}', [ProgramController::class, 'update'])
        ->middleware('permission:programs.update')
        ->name('programs.update');
    Route::delete('programs/{program}', [ProgramController::class, 'destroy'])
        ->middleware('permission:programs.delete')
        ->name('programs.destroy');

    // Coach management and assignment.
    Route::get('coaches', [\App\Http\Controllers\Admin\CoachController::class, 'index'])
        ->middleware('permission:coaches.view')
        ->name('coaches.index');
    Route::post('coaches', [\App\Http\Controllers\Admin\CoachController::class, 'store'])
        ->middleware('permission:coaches.create')
        ->name('coaches.store');
    Route::get('coaches/{coach}', [\App\Http\Controllers\Admin\CoachController::class, 'show'])
        ->middleware('permission:coaches.view')
        ->name('coaches.show');
    Route::put('coaches/{coach}', [\App\Http\Controllers\Admin\CoachController::class, 'update'])
        ->middleware('permission:coaches.update')
        ->name('coaches.update');
    Route::post('coaches/{coach}/assign', [\App\Http\Controllers\Admin\CoachController::class, 'assign'])
        ->middleware('permission:coaches.assign')
        ->name('coaches.assign');
    Route::delete('coaches/{coach}/assignments/{assignment}', [\App\Http\Controllers\Admin\CoachController::class, 'unassign'])
        ->middleware('permission:coaches.reassign')
        ->name('coaches.unassign');
});

// ===== SCHOOL PIC ROUTES =====
Route::middleware(['auth', 'role:school_pic', 'permission:attendance.view'])
    ->prefix('pic')
    ->name('pic.')
    ->group(function () {
        Route::get('dashboard', [PicDashboard::class, 'index'])->name('dashboard');
        Route::post('remind', [PicDashboard::class, 'remind'])->name('remind');
        Route::get('reports/{report}', [PicDashboard::class, 'show'])->name('reports.show');
        Route::get('reports/{report}/download', [AdminReportController::class, 'download'])
            ->middleware('permission:reports.download')
            ->name('reports.download');
    });

// ===== AUTHORIZED MEDIA SERVING =====
// Media files are stored outside the public symlink. Access is authorized
// per-report: the MediaController checks the user's role and school scope.
Route::get('/media/{media}', [\App\Http\Controllers\MediaController::class, 'serve'])
    ->middleware('auth')
    ->name('media.serve');

// ===== AJAX ENDPOINT FOR STUDENTS =====
// Roster dipakai form laporan: coach tambahan yang hanya terdaftar pada sesi
// mengajar (tanpa coach_classes) boleh membaca daftar siswa kelas yang dia
// ajar, tetapi tidak mendapat wewenang pengelolaan siswa.
Route::get('/api/classes/{class}/students', function (\App\Models\SchoolClass $class) {
    abort_unless(
        app(\App\Services\AuthorizationService::class)->canViewClassRoster(request()->user(), $class),
        403,
        'Kamu tidak memiliki akses ke kelas ini.'
    );

    return response()->json($class->students()->select('id', 'name')->get());
})->middleware(['auth', 'permission:students.view']);

