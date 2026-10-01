<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Report;
use App\Services\AttendanceExportService;
use App\Services\AttendanceScopeService;
use App\Services\AuthorizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private AttendanceScopeService $attendanceScope,
        private AttendanceExportService $attendanceExport,
        private AuthorizationService $authorization,
    ) {
    }

    /**
     * ATTENDANCE INDEX — UX 2026-09-13: hierarki drill-down
     * Attendance → Sekolah → Kelas → Tanggal → Murid.
     *
     * Index HANYA menampilkan sekolah yang punya data kehadiran dalam
     * scope user, beserta ringkasan (jumlah kelas, jumlah sesi, tanggal
     * terakhir). Daftar murid / akumulasi TIDAK ada di sini — akumulasi
     * hanya di dokumen unduh (CSV/PDF).
     *
     * ONLY_FULL_GROUP_BY: semua kolom non-agregat (school_id, name) ada
     * di GROUP BY, ORDER BY hanya kolom grup — tanpa ANY_VALUE() dan
     * tanpa menonaktifkan SQL mode.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'attendance.view');

        $filters = $this->validatedFilters($request);
        $search = trim((string) $request->query('search', ''));

        $schools = $this->attendanceScope->query($user, $filters)
            ->setEagerLoads([])
            ->join('reports', 'reports.id', '=', 'report_attendances.report_id')
            ->join('schools', 'schools.id', '=', 'reports.school_id')
            ->selectRaw(
                'reports.school_id as school_id, schools.name as school_name,'
                . ' COUNT(DISTINCT reports.class_id) as classes_count,'
                . ' COUNT(DISTINCT reports.id) as sessions_count,'
                . ' MAX(reports.report_date) as last_date'
            )
            ->when($search !== '', function (Builder $schools) use ($search): void {
                $schools->where('schools.name', 'like', "%{$search}%");
            })
            ->reorder()
            ->groupBy('reports.school_id', 'schools.name')
            ->orderBy('schools.name')
            ->paginate(15, ['*'], 'school_page')
            ->withQueryString();

        return view('attendance.index', compact('schools'));
    }

    /**
     * SCHOOL ATTENDANCE DETAIL — daftar kelas pada sekolah tersebut yang
     * punya data kehadiran dalam scope user. Kelas sekolah lain tidak
     * pernah muncul (query difilter school_id + scope).
     */
    public function showSchool(Request $request, School $school)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'attendance.view');
        abort_unless(
            $this->authorization->canAccessSchool($user, $school->id),
            403,
            'Akses sekolah tidak diizinkan.'
        );

        $filters = $this->validatedFilters($request);

        $classes = $this->attendanceScope->query($user, array_merge($filters, ['school_id' => $school->id]))
            ->setEagerLoads([])
            ->join('reports', 'reports.id', '=', 'report_attendances.report_id')
            ->join('classes', 'classes.id', '=', 'reports.class_id')
            ->selectRaw(
                'classes.id as class_id, classes.name as class_name,'
                . ' COUNT(DISTINCT reports.id) as sessions_count,'
                . ' COUNT(DISTINCT report_attendances.student_id) as students_count,'
                . ' MAX(reports.report_date) as last_date'
            )
            ->reorder()
            ->groupBy('classes.id', 'classes.name')
            ->orderBy('classes.name')
            ->paginate(15, ['*'], 'class_page')
            ->withQueryString();

        // Info program untuk kartu kelas (jika kelas terhubung ke program).
        $programsByClass = SchoolClass::query()
            ->with('programs')
            ->whereIn('id', $classes->getCollection()->pluck('class_id'))
            ->get()
            ->keyBy('id');

        return view('attendance.school', [
            'school' => $school,
            'classes' => $classes,
            'programsByClass' => $programsByClass,
        ]);
    }

    /**
     * CLASS ATTENDANCE DETAIL — daftar sesi kehadiran (satu laporan per
     * tanggal) untuk kelas tersebut, urut tanggal terbaru. Klik tanggal
     * membuka detail murid sesi itu.
     *
     * Gerbang di sini memakai `canAccessSchool()` — aturan yang PERSIS sama
     * dengan halaman sekolah di atasnya dan dengan `showSession()`. Sebelumnya
     * halaman ini memakai `canAccessClass()`, yaitu predikat wewenang
     * PENGELOLAAN kelas (dipakai modul siswa) yang menolak role tanpa
     * penugasan kelas — termasuk Finance, yang scope kehadirannya justru
     * global. Akibatnya Finance bisa membuka daftar sekolah tapi tertahan 403
     * begitu menelusuri ke kelas.
     *
     * Pembatas yang sebenarnya dikerjakan oleh query: `scopedReports()`
     * menyaring laporan sesuai scope role (sekolah, status approval, penugasan
     * coach), jadi role yang lebih sempit tetap tidak melihat apa pun yang
     * bukan miliknya meski gerbangnya sama.
     */
    public function showClass(Request $request, School $school, SchoolClass $class)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'attendance.view');

        // Kelas harus milik sekolah di URL — 404 agar tidak membocorkan
        // keberadaan kelas milik sekolah lain.
        abort_unless($class->school_id === $school->id, 404);
        abort_unless(
            $this->authorization->canAccessSchool($user, $school->id),
            403,
            'Akses sekolah tidak diizinkan.'
        );

        $filters = $this->validatedFilters($request);

        $sessions = $this->attendanceScope->scopedReports($user)
            ->where('reports.school_id', $school->id)
            ->where('reports.class_id', $class->id)
            ->whereHas('attendances')
            ->when($filters['date_from'] ?? null, function (Builder $reports, string $date): void {
                $reports->whereDate('report_date', '>=', $date);
            })
            ->when($filters['date_to'] ?? null, function (Builder $reports, string $date): void {
                $reports->whereDate('report_date', '<=', $date);
            })
            ->with(['coach'])
            ->withCount(['attendances', 'attendanceMedia'])
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->paginate(15, ['*'], 'session_page')
            ->withQueryString();

        return view('attendance.class', [
            'school' => $school,
            'class' => $class,
            'sessions' => $sessions,
        ]);
    }

    /**
     * DATE ATTENDANCE DETAIL — seluruh murid pada sesi (laporan) itu:
     * tabel Murid | Status, plus galeri bukti kehadiran. Autorisasi
     * mengikuti scope yang sama (anti-IDOR: laporan di luar scope → 403/404).
     */
    public function showSession(Request $request, Report $report)
    {
        $user = $request->user();
        $this->ensurePermission($user, 'attendance.view');

        abort_unless(
            $this->authorization->canAccessSchool($user, $report->school_id),
            403,
            'Akses tidak diizinkan.'
        );
        // Laporan harus terlihat dalam scope user (status approval,
        // penugasan coach) — 404 agar tidak membocorkan keberadaannya.
        $inScope = $this->attendanceScope->scopedReports($user)
            ->where('reports.id', $report->id)
            ->exists();
        abort_unless($inScope, 404);

        $report->load(['attendances.student', 'attendanceMedia', 'coach', 'school', 'schoolClass']);

        $attendances = $report->attendances->sortBy(fn ($entry) => $entry->student->name ?? '')->values();

        return view('attendance.session', [
            'report' => $report,
            'attendances' => $attendances,
        ]);
    }

    public function export(Request $request)
    {
        $user = $request->user();
        abort_unless(
            $this->authorization->allows($user, 'attendance.export')
                || $this->authorization->allows($user, 'attendance.export_csv'),
            403,
            'Permission tidak mencukupi.'
        );

        $filters = $this->validatedFilters($request);
        $query = $this->attendanceScope->query($user, $filters);

        $format = $request->query('format');

        // QA M-001: Excel & PDF adalah dokumen administratif/formal — hanya
        // role dengan capability attendance.export penuh. Finance
        // (export_csv) dibatasi ke CSV data mentah.
        if ($format === 'excel' || $format === 'xlsx') {
            abort_unless(
                $this->authorization->allows($user, 'attendance.export'),
                403,
                'Export Excel memerlukan permission attendance.export.'
            );

            return $this->attendanceExport->downloadExcel(
                $query,
                'attendance-'.now()->format('Ymd-His').'.xlsx'
            );
        }

        if ($format === 'pdf') {
            abort_unless(
                $this->authorization->allows($user, 'attendance.export'),
                403,
                'Export PDF memerlukan permission attendance.export.'
            );

            return $this->attendanceExport->downloadPdf(
                $query,
                'attendance-'.now()->format('Ymd-His').'.pdf'
            );
        }

        return $this->attendanceExport->downloadCsv(
            $query,
            'attendance-'.now()->format('Ymd-His').'.csv'
        );
    }

    /**
     * Akumulasi kehadiran (TOTAL HADIR) hanya di dokumen unduh CSV/PDF —
     * tidak pernah di halaman index/detail (keputusan UX 2026-09-13).
     */

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'attendance_status' => ['nullable', 'in:present,absent,sick,permission'],
            'report_status' => ['nullable', 'in:draft,submitted,approved,rejected'],
        ]);
    }

    private function ensurePermission(User $user, string $permission): void
    {
        abort_unless(
            $this->authorization->allows($user, $permission),
            403,
            'Permission tidak mencukupi.'
        );
    }
}
