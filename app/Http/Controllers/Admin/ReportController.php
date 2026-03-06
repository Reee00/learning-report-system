<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = Report::with(['coach', 'school', 'schoolClass'])->latest();

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
        $schools = School::orderBy('name')->get();

        return view('admin.reports.index', compact('reports', 'schools'));
    }

    public function show(Report $report)
    {
        $report->load(['coach', 'school', 'schoolClass', 'attendances.student', 'media']);
        return view('admin.reports.show', compact('report'));
    }

    public function approve(Report $report)
    {
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
        $request->validate(['admin_notes' => 'required|string|max:500']);
        abort_if($report->status !== 'submitted', 422, 'Hanya laporan yang dikirim bisa ditolak.');

        $report->update([
            'status'      => 'rejected',
            'admin_notes' => $request->admin_notes,
        ]);

        return back()->with('success', "Laporan #{$report->id} ditolak dengan catatan.");
    }
}