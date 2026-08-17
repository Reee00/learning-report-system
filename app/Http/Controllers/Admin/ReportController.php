<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\School;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct(private AuthorizationService $authorization)
    {
    }

    public function index(Request $request)
    {
        $query = Report::with(['coach', 'school', 'schoolClass'])->latest();

        // School scope diterapkan sebelum filter dari request, sehingga filter
        // school_id hanya bisa mempersempit scope dan tidak bisa melewatinya.
        $accessibleSchoolIds = $this->authorization->accessibleSchoolIds($this->actingUser());
        if ($accessibleSchoolIds !== null) {
            $query->whereIn('school_id', $accessibleSchoolIds);
        }

        // Filter berdasarkan sekolah
        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }
        // Filter berdasarkan status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        // Filter berdasarkan tanggal
        if ($request->filled('date_from')) {
            $query->whereDate('report_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('report_date', '<=', $request->date_to);
        }

        $reports = $query->paginate(20)->withQueryString();

        // Scope school dropdown to accessible schools.
        $schoolsQuery = School::orderBy('name');
        if ($accessibleSchoolIds !== null) {
            $schoolsQuery->whereIn('id', $accessibleSchoolIds);
        }
        $schools = $schoolsQuery->get();

        // Only users with reports.review can approve/reject (Relation + SuperAdmin).
        $canReview = $this->authorization->allows($this->actingUser(), 'reports.review');

        return view('admin.reports.index', compact('reports', 'schools', 'canReview'));
    }

    public function show(Report $report)
    {
        $this->ensureSchoolAccess($report);

        $report->load(['coach', 'school', 'schoolClass', 'attendances.student', 'media']);
        $canReview = $this->authorization->allows($this->actingUser(), 'reports.review');
        return view('admin.reports.show', compact('report', 'canReview'));
    }

    public function approve(Report $report)
    {
        $this->ensureSchoolAccess($report);
        abort_if($report->status !== 'submitted', 422, 'Hanya laporan yang dikirim bisa disetujui.');

        $report->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'admin_notes' => null,
        ]);

        return back()->with('success', "Laporan #{$report->id} berhasil disetujui.");
    }

    public function reject(Request $request, Report $report)
    {
        $this->ensureSchoolAccess($report);
        $request->validate(['admin_notes' => 'required|string|max:500']);
        abort_if($report->status !== 'submitted', 422, 'Hanya laporan yang dikirim bisa ditolak.');

        $report->update([
            'status'      => 'rejected',
            'admin_notes' => $request->admin_notes,
        ]);

        return back()->with('success', "Laporan #{$report->id} ditolak dengan catatan.");
    }

    /**
     * Object-level boundary. Route middleware sudah membatasi capability, tetapi
     * scope sekolah tetap diperiksa di backend agar tidak bergantung pada route
     * atau UI saja.
     */
    private function ensureSchoolAccess(Report $report): void
    {
        abort_unless(
            $this->authorization->canAccessSchool($this->actingUser(), (int) $report->school_id),
            403,
            'Kamu tidak memiliki akses ke laporan sekolah ini.'
        );
    }

    private function actingUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}