<?php
namespace App\Http\Controllers\SchoolPic;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    private function schoolId(): int
    {
        return Auth::user()->school_id;
    }

    public function index(Request $request)
    {
        $schoolId = $this->schoolId();

        $query = Report::with(['schoolClass', 'coach'])
            ->where('school_id', $schoolId)
            ->where('status', 'approved') // PIC hanya melihat laporan yang sudah disetujui
            ->latest('report_date');

        // Filter tambahan
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('report_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('report_date', '<=', $request->date_to);
        }

        $reports     = $query->paginate(20)->withQueryString();
        $classes     = SchoolClass::where('school_id', $schoolId)->orderBy('name')->get();
        $totalReports = Report::where('school_id', $schoolId)->where('status', 'approved')->count();
        $thisMonth    = Report::where('school_id', $schoolId)->where('status', 'approved')
                            ->whereMonth('report_date', now()->month)->count();

        return view('school_pic.dashboard', compact('reports', 'classes', 'totalReports', 'thisMonth'));
    }

    public function show(Report $report)
    {
        // Pastikan PIC hanya bisa lihat laporan dari sekolahnya sendiri
        abort_if($report->school_id !== $this->schoolId(), 403);
        // Pastikan laporan sudah disetujui
        abort_if($report->status !== 'approved', 403);

        $report->load(['coach', 'school', 'schoolClass', 'attendances.student', 'media']);
        return view('school_pic.reports.show', compact('report'));
    }
}