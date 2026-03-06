<?php
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Coach\ReportController as CoachReportController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SchoolController;
use App\Http\Controllers\Admin\ClassController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\SchoolPic\DashboardController as PicDashboard;
use Illuminate\Support\Facades\Route;

// ===== PUBLIC ROUTES =====
Route::get('/', fn() => redirect()->route('login'));
Route::get('/login',  [LoginController::class, 'showForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
// ===== STUDENT ROUTES (Admin + Coach + School PIC) =====
Route::middleware('auth')->group(function () {
    Route::get('/classes/{class}/students',         [StudentController::class, 'show'])->name('students.show');
    Route::post('/classes/{class}/students',        [StudentController::class, 'store'])->name('students.store');
    Route::post('/classes/{class}/students/import', [StudentController::class, 'import'])->name('students.import');
    Route::delete('/classes/{class}/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::get('/students/template',                [StudentController::class, 'template'])->name('students.template');
});

// ===== COACH ROUTES =====
// Hanya bisa diakses oleh user dengan role 'coach'
Route::middleware(['auth', 'role:coach'])->prefix('coach')->name('coach.')->group(function () {
    Route::resource('reports', CoachReportController::class)->only(['index','create','store','edit','update']);
});

// ===== ADMIN ROUTES =====
// Hanya bisa diakses oleh user dengan role 'admin'
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [AdminDashboard::class, 'index'])->name('dashboard');

    // Manajemen Akun
Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index'])->name('users.index');
Route::post('users', [\App\Http\Controllers\Admin\UserController::class, 'store'])->name('users.store');
Route::put('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'update'])->name('users.update');
Route::patch('users/{user}/reset-password', [\App\Http\Controllers\Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
Route::delete('users/{user}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->name('users.destroy');
    // Laporan
    Route::get('reports',              [AdminReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{report}',     [AdminReportController::class, 'show'])->name('reports.show');
    Route::patch('reports/{report}/approve', [AdminReportController::class, 'approve'])->name('reports.approve');
    Route::patch('reports/{report}/reject',  [AdminReportController::class, 'reject'])->name('reports.reject');

    // Master Data
    Route::resource('schools', SchoolController::class);
    Route::resource('classes', ClassController::class);
    // Setelah Route::resource('classes', ClassController::class);
// Tambahkan baris berikut:

Route::get('coaches', [\App\Http\Controllers\Admin\CoachController::class, 'index'])->name('coaches.index');
Route::get('coaches/{coach}', [\App\Http\Controllers\Admin\CoachController::class, 'show'])->name('coaches.show');
Route::post('coaches/{coach}/assign', [\App\Http\Controllers\Admin\CoachController::class, 'assign'])->name('coaches.assign');
Route::delete('coaches/{coach}/assignments/{assignment}', [\App\Http\Controllers\Admin\CoachController::class, 'unassign'])->name('coaches.unassign');
});

// ===== SCHOOL PIC ROUTES =====
// Hanya bisa diakses oleh user dengan role 'school_pic'
Route::middleware(['auth', 'role:school_pic'])->prefix('pic')->name('pic.')->group(function () {
    Route::get('dashboard',           [PicDashboard::class, 'index'])->name('dashboard');
    Route::get('reports/{report}',    [PicDashboard::class, 'show'])->name('reports.show');
});

// ===== API ENDPOINT untuk AJAX (Load Students) =====
Route::get('/api/classes/{class}/students', function(\App\Models\SchoolClass $class) {
    return response()->json($class->students()->select('id', 'name')->get());
})->middleware('auth');