<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\School;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_reports'     => Report::count(),
            'submitted_reports' => Report::where('status', 'submitted')->count(),
            'approved_reports'  => Report::where('status', 'approved')->count(),
            'rejected_reports'  => Report::where('status', 'rejected')->count(),
            'total_schools'     => School::count(),
            'total_coaches'     => User::where('role', 'coach')->count(),
        ];

        // 5 laporan terbaru yang perlu direview
        $pendingReports = Report::with(['coach', 'school', 'schoolClass'])
            ->where('status', 'submitted')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact('stats', 'pendingReports'));
    }
}