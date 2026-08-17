<?php
namespace App\Http\Controllers\SchoolPic;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
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
        if ($request->filled('date_from')) {
            $query->whereDate('report_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('report_date', '<=', $request->date_to);
        }

        $reports     = $query->paginate(20)->withQueryString();
        $schools     = School::whereIn('id', $schoolIds)->orderBy('name')->get();
        $classes     = SchoolClass::whereIn('school_id', $schoolIds)->orderBy('name')->get();
        $totalReports = Report::whereIn('school_id', $schoolIds)->where('status', 'approved')->count();
        $thisMonth    = Report::whereIn('school_id', $schoolIds)->where('status', 'approved')
                            ->whereMonth('report_date', now()->month)->count();

        return view('school_pic.dashboard', compact('reports', 'schools', 'classes', 'totalReports', 'thisMonth'));
    }

    public function show(Report $report)
    {
        // Pastikan PIC hanya bisa lihat laporan dari sekolahnya sendiri
        abort_unless(in_array((int) $report->school_id, $this->schoolIds(), true), 403);
        // Pastikan laporan sudah disetujui
        abort_if($report->status !== 'approved', 403);

        $report->load(['coach', 'school', 'schoolClass', 'attendances.student', 'media']);
        return view('school_pic.reports.show', compact('report'));
    }
}
