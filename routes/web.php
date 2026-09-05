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
Route::get('/attendance', [AttendanceController::class, 'index'])
    ->middleware(['auth', 'permission:attendance.view'])
    ->name('attendance.index');
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

    // Coach: view list of assigned classes and manage their students.
    // class_id is always resolved from the coach assignment in the backend.
    Route::get('students', [CoachStudentController::class, 'index'])
        ->middleware('permission:students.view')
        ->name('students.index');
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
        Route::get('reports/{report}', [PicDashboard::class, 'show'])->name('reports.show');
    });

// ===== AUTHORIZED MEDIA SERVING =====
// Media files are stored outside the public symlink. Access is authorized
// per-report: the MediaController checks the user's role and school scope.
Route::get('/media/{media}', [\App\Http\Controllers\MediaController::class, 'serve'])
    ->middleware('auth')
    ->name('media.serve');

// ===== AJAX ENDPOINT FOR STUDENTS =====
Route::get('/api/classes/{class}/students', function (\App\Models\SchoolClass $class) {
    abort_unless(
        app(\App\Services\AuthorizationService::class)->canAccessClass(request()->user(), $class),
        403,
        'Kamu tidak memiliki akses ke kelas ini.'
    );

    return response()->json($class->students()->select('id', 'name')->get());
})->middleware(['auth', 'permission:students.view']);

