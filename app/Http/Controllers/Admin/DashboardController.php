<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\School;
use App\Models\User;
use App\Services\AuthorizationService;

class DashboardController extends Controller
{
    public function __construct(private AuthorizationService $authorization)
    {
    }

    public function index()
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        // QA H-002: statistik dashboard wajib mengikuti school scope user.
        // Null berarti scope operasional-global (SuperAdmin/Relation/SPV).
        $schoolIds = $this->authorization->accessibleSchoolIds($user);

        $reportQuery = Report::query();
        $schoolQuery = School::query();

        if ($schoolIds !== null) {
            $reportQuery->whereIn('school_id', $schoolIds);
            $schoolQuery->whereIn('id', $schoolIds);
        }

        $stats = [
            'total_reports'     => (clone $reportQuery)->count(),
            'submitted_reports' => (clone $reportQuery)->where('status', 'submitted')->count(),
            'approved_reports'  => (clone $reportQuery)->where('status', 'approved')->count(),
            'rejected_reports'  => (clone $reportQuery)->where('status', 'rejected')->count(),
            'total_schools'     => $schoolQuery->count(),
            'total_coaches'     => User::where('role', 'coach')->count(),
        ];

        // 5 laporan terbaru yang perlu direview
        $pendingReports = Report::with(['coach', 'school', 'schoolClass'])
            ->where('status', 'submitted')
            ->when($schoolIds !== null, fn ($query) => $query->whereIn('school_id', $schoolIds))
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'pendingReports'));
    }
}
