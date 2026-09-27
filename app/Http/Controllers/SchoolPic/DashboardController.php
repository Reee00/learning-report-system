<?php
namespace App\Http\Controllers\SchoolPic;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\ReportReminderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private function schoolIds(): array
    {
        return Auth::user()->assignedSchoolIds();
    }

    public function index(Request $request)
    {
        $schoolIds = $this->schoolIds();

        $query = Report::with(['schoolClass', 'coach'])
            ->whereIn('school_id', $schoolIds)
            ->where('status', 'approved') // PIC hanya melihat laporan yang sudah disetujui
            ->latest('report_date');

        // Filter tambahan
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->integer('class_id'));
        }
        // Meeting 2026-09 req. C: PIC dapat memfilter berdasarkan coach yang
        // mengajar di sekolah plot-nya.
        if ($request->filled('coach_id')) {
            $query->where('coach_id', $request->integer('coach_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('report_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('report_date', '<=', $request->date_to);
        }

        $reports      = $query->paginate(20)->withQueryString();
        $schools      = School::whereIn('id', $schoolIds)->orderBy('name')->get();
        $classes      = SchoolClass::whereIn('school_id', $schoolIds)->orderBy('name')->get();
        // Hanya coach yang mengajar di sekolah plot-nya (coach lain tidak
        // pernah diekspos ke PIC).
        $coaches      = User::where('role', User::ROLE_COACH)
            ->whereHas('coachClasses.schoolClass', fn ($c) => $c->whereIn('school_id', $schoolIds))
            ->orderBy('name')
            ->get();
        $totalReports = Report::whereIn('school_id', $schoolIds)->where('status', 'approved')->count();
        $thisMonth    = Report::whereIn('school_id', $schoolIds)->where('status', 'approved')
                            ->whereMonth('report_date', now()->month)->count();

        // Meeting 2026-09 req. D: daftar coach yang belum menyelesaikan
        // laporan sesi mengajarnya, scoped ke sekolah plot-nya.
        $reminderService = app(ReportReminderService::class);
        $overdueCoaches  = $reminderService->overdueCoaches(Auth::user());

        return view('school_pic.dashboard', compact(
            'reports', 'schools', 'classes', 'coaches', 'totalReports', 'thisMonth', 'overdueCoaches'
        ));
    }

    /**
     * Reminder laporan untuk PIC — hanya untuk coach yang mengajar di
     * sekolah plot-nya. Cross-school reminder ditolak oleh service.
     */
    public function remind(Request $request)
    {
        $user = Auth::user();
        $validated = $request->validate([
            'coach_id' => 'required|integer|exists:users,id',
            'message'  => 'nullable|string|max:500',
        ]);

        $coach = User::where('role', User::ROLE_COACH)->findOrFail($validated['coach_id']);

        $reminders = app(ReportReminderService::class);
        $sent = $reminders->send($user, $coach, $validated['message'] ?? null);

        if ($sent === 0) {
            // Tanpa data jadwal mengajar, coach tidak dianggap menunggak.
            if (TeachingSchedule::count() === 0) {
                return back()->with('error', 'Reminder tidak dapat dikirim: belum ada data jadwal mengajar. Minta Relation mengimport jadwal mengajar terlebih dahulu.');
            }

            return back()->with('error', 'Anda hanya bisa mengingatkan coach yang mengajar di sekolah Anda.');
        }

        app(ActivityLogService::class)->log(
            $user,
            'report.reminder_sent',
            'user',
            $coach->id,
            "PIC mengirim reminder laporan ke coach {$coach->name}",
            request: $request,
        );

        return back()->with('success', 'Reminder berhasil dikirim ke ' . $coach->name . '.');
    }

    public function show(Report $report)
    {
        // Pastikan PIC hanya bisa lihat laporan dari sekolahnya sendiri
        abort_unless(in_array((int) $report->school_id, $this->schoolIds(), true), 403);
        // Pastikan laporan sudah disetujui
        abort_if($report->status !== 'approved', 403);

        $report->load(['coach', 'school', 'schoolClass', 'attendances.student', 'media', 'attendanceMedia']);
        return view('school_pic.reports.show', compact('report'));
    }
}
