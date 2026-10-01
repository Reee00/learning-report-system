<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function __construct(
        private AuthorizationService $authorization,
        private ActivityLogService $activityLog,
    ) {
    }

    /**
     * REVIEW — antrean kerja reviewer, dipisah dari ARSIP.
     *
     * Dua konsep, satu tabel `reports`:
     * - REVIEW (halaman ini) menjawab "apa yang harus saya kerjakan sekarang":
     *   laporan `submitted` yang menunggu keputusan, dan laporan `rejected`
     *   yang menunggu koreksi coach. Di sini tombol Setujui/Tolak muncul.
     * - ARSIP (index()) menjawab "apa yang sudah terjadi": riwayat lengkap
     *   baca-saja, dikelompokkan Sekolah → Kelas.
     *
     * Tidak ada tabel arsip kedua dan tidak ada baris yang disalin — keduanya
     * query ke `reports` yang sama dengan scope sekolah yang sama.
     */
    public function review(Request $request)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'reports.review'), 403, 'Permission tidak mencukupi.');

        $query = Report::with(['coach', 'school', 'schoolClass'])
            ->whereIn('status', ['submitted', 'rejected'])
            ->latest();

        // Scope sekolah diterapkan lebih dulu; filter dari request hanya bisa
        // mempersempit, tidak pernah melewatinya.
        $accessibleSchoolIds = $this->authorization->accessibleSchoolIds($user);
        if ($accessibleSchoolIds !== null) {
            $query->whereIn('school_id', $accessibleSchoolIds);
        }

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        if ($request->filled('coach_id')) {
            $query->where('coach_id', $request->coach_id);
        }
        if ($request->filled('status') && in_array($request->status, ['submitted', 'rejected'], true)) {
            $query->where('status', $request->status);
        }

        $reports = $query->paginate(20)->withQueryString();

        // Hitungan antrean dihitung dari query ter-scope yang sama, sebelum
        // filter status, supaya reviewer tahu beban kerjanya walau sedang
        // menyaring satu status saja.
        $countsQuery = Report::query()->whereIn('status', ['submitted', 'rejected']);
        if ($accessibleSchoolIds !== null) {
            $countsQuery->whereIn('school_id', $accessibleSchoolIds);
        }
        if ($request->filled('school_id')) {
            $countsQuery->where('school_id', $request->school_id);
        }

        $pendingCount = (clone $countsQuery)->where('status', 'submitted')->count();
        $rejectedCount = (clone $countsQuery)->where('status', 'rejected')->count();

        $schoolsQuery = School::orderBy('name');
        if ($accessibleSchoolIds !== null) {
            $schoolsQuery->whereIn('id', $accessibleSchoolIds);
        }
        $schools = $schoolsQuery->get();

        $classesQuery = SchoolClass::orderBy('name')->with('school');
        if ($accessibleSchoolIds !== null) {
            $classesQuery->whereIn('school_id', $accessibleSchoolIds);
        }
        $classes = $classesQuery->get();

        $coachesQuery = User::where('role', User::ROLE_COACH)->orderBy('name');
        if ($accessibleSchoolIds !== null) {
            $coachesQuery->whereHas('reports', fn ($q) => $q->whereIn('school_id', $accessibleSchoolIds));
        }
        $coaches = $coachesQuery->get();

        return view('admin.reports.review', compact(
            'reports', 'schools', 'classes', 'coaches',
            'pendingCount', 'rejectedCount'
        ));
    }

    public function index(Request $request)
    {
        $user = $this->actingUser();
        $query = Report::with(['coach', 'school', 'schoolClass'])->latest();

        // School scope diterapkan sebelum filter dari request, sehingga filter
        // school_id hanya bisa mempersempit scope dan tidak bisa melewatinya.
        $accessibleSchoolIds = $this->authorization->accessibleSchoolIds($user);
        if ($accessibleSchoolIds !== null) {
            $query->whereIn('school_id', $accessibleSchoolIds);
        }

        // Teacher School hanya boleh melihat laporan yang sudah disetujui;
        // filter status dari request diabaikan supaya tidak bisa dipakai
        // membocorkan laporan ditolak/perlu diperbaiki (meeting 2026-09 req. A).
        $approvedOnly = $user->role === User::ROLE_TEACHER_SCHOOL;
        if ($approvedOnly) {
            $query->where('status', 'approved');
        } elseif ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter berdasarkan sekolah
        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }
        // Filter kelas & coach: keduanya hanya mempersempit, tidak pernah
        // melebarkan — scope sekolah di atas sudah lebih dulu diterapkan.
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }
        if ($request->filled('coach_id')) {
            $query->where('coach_id', $request->coach_id);
        }
        // Filter berdasarkan tanggal
        if ($request->filled('date_from')) {
            $query->whereDate('report_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('report_date', '<=', $request->date_to);
        }

        // Arsip historis: satu sekolah bisa punya ratusan laporan, dan
        // pengelompokan Sekolah → Kelas dibangun per halaman. Pilihan jumlah
        // per halaman membuat riwayat satu sekolah bisa dilihat utuh tanpa
        // berpindah halaman (dan tanpa memecah accordion di tengah).
        $allowedPerPage = [20, 50, 100, 200];
        $perPage = (int) $request->query('per_page', 20);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 20;
        }

        $reports = $query->paginate($perPage)->withQueryString();

        // Audit UX 2026-09-11: laporan dikelompokkan Sekolah → Kelas untuk
        // presentasi accordion. Grouping dibangun dari data ter-scope di
        // atas, sehingga tidak bisa membocorkan sekolah di luar scope.
        // Kunci array JANGAN di-reset (->values()) — nama kelas dipakai
        // sebagai key oleh view.
        $grouped = $reports->getCollection()->groupBy([
            fn ($report) => $report->school->name,
            fn ($report) => $report->schoolClass->name,
        ]);

        // Scope school dropdown to accessible schools.
        $schoolsQuery = School::orderBy('name');
        if ($accessibleSchoolIds !== null) {
            $schoolsQuery->whereIn('id', $accessibleSchoolIds);
        }
        $schools = $schoolsQuery->get();

        // Pilihan filter kelas & coach juga dibatasi scope sekolah yang sama,
        // supaya dropdown tidak membocorkan nama sekolah/coach di luar wewenang.
        $classesQuery = SchoolClass::orderBy('name')->with('school');
        if ($accessibleSchoolIds !== null) {
            $classesQuery->whereIn('school_id', $accessibleSchoolIds);
        }
        $classes = $classesQuery->get();

        $coachesQuery = User::where('role', User::ROLE_COACH)->orderBy('name');
        if ($accessibleSchoolIds !== null) {
            // Hanya coach yang benar-benar punya laporan di sekolah ter-scope.
            $coachesQuery->whereHas('reports', fn ($q) => $q->whereIn('school_id', $accessibleSchoolIds));
        }
        $coaches = $coachesQuery->get();

        // Only users with reports.review can approve/reject (Relation + SuperAdmin).
        $canReview = $this->authorization->allows($user, 'reports.review');

        return view('admin.reports.index', compact(
            'reports', 'grouped', 'schools', 'classes', 'coaches',
            'canReview', 'approvedOnly', 'perPage', 'allowedPerPage'
        ));
    }

    /**
     * Reminder laporan (meeting 2026-09 req. D) untuk Relation/SuperAdmin:
     * kirim notifikasi ke semua coach yang masih memiliki sesi mengajar
     * belum dilaporkan. Route dibatasi permission reports.remind.
     */
    public function remind(Request $request)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'reports.remind'), 403, 'Permission tidak mencukupi.');

        $validated = $request->validate([
            'coach_id' => 'nullable|integer|exists:users,id',
            'message'  => 'nullable|string|max:500',
        ]);

        $reminders = app(\App\Services\ReportReminderService::class);

        if (!empty($validated['coach_id'])) {
            $coach = User::findOrFail($validated['coach_id']);
            $sent = $reminders->send($user, $coach, $validated['message'] ?? null);
        } else {
            $sent = $reminders->send($user, $reminders->overdueCoaches($user), $validated['message'] ?? null);
        }

        if ($sent === 0) {
            // Tanpa data jadwal mengajar tidak ada coach yang dianggap
            // menunggak — pastikan user paham reminder tidak terkirim.
            if (TeachingSchedule::count() === 0) {
                return back()->with('error', 'Reminder tidak dapat dikirim: belum ada data jadwal mengajar. Import jadwal mengajar terlebih dahulu (menu Jadwal Mengajar).');
            }

            return back()->with('success', 'Tidak ada coach yang perlu diingatkan saat ini (semua sesi sudah dilaporkan, atau reminder sebelumnya masih belum dibaca).');
        }

        if ($sent > 0) {
            $this->activityLog->log(
                $user,
                'report.reminder_sent',
                'report',
                null,
                "Reminder laporan dikirim ke {$sent} coach",
                ['coach_id' => $validated['coach_id'] ?? null, 'count' => $sent],
                $request,
            );
        }

        return back()->with('success', "Reminder berhasil dikirim ke {$sent} coach.");
    }

    public function show(Report $report)
    {
        $this->ensureSchoolAccess($report);

        // Teacher School hanya boleh melihat detail laporan approved (req. A),
        // konsisten dengan index() yang memaksa filter status approved.
        if ($this->actingUser()->role === User::ROLE_TEACHER_SCHOOL) {
            abort_if($report->status !== 'approved', 403, 'Laporan belum disetujui.');
        }

        $report->load(['coach', 'school', 'schoolClass', 'attendances.student', 'media', 'attendanceMedia']);
        $canReview = $this->authorization->allows($this->actingUser(), 'reports.review');
        return view('admin.reports.show', compact('report', 'canReview'));
    }

    public function approve(Request $request, Report $report)
    {
        $this->ensureSchoolAccess($report);
        abort_if($report->status !== 'submitted', 422, 'Hanya laporan yang dikirim bisa disetujui.');

        $report->update([
            'status'      => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'admin_notes' => null,
        ]);

        $this->activityLog->log(
            $this->actingUser(),
            'report.approved',
            'report',
            $report->id,
            "Laporan #{$report->id} disetujui",
            request: $request,
        );

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

        $this->activityLog->log(
            $this->actingUser(),
            'report.rejected',
            'report',
            $report->id,
            "Laporan #{$report->id} ditolak",
            ['admin_notes' => $request->admin_notes],
            $request,
        );

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

    /**
     * Download (print-ready HTML) an approved Coach Report.
     *
     * Security checks (in order):
     * 1. Permission middleware enforces reports.download capability.
     * 2. Object scope: role coach memakai aturan akses LAPORAN
     *    (AuthorizationService::canAccessReport — per SESI), role lain memakai
     *    scope sekolah via ensureSchoolAccess(). Role coach TIDAK boleh
     *    memakai ensureSchoolAccess() karena accessibleSchoolIds() bernilai
     *    null (global) untuk coach, sehingga scope sekolah akan meloloskannya
     *    ke seluruh sekolah. Aturan ini sama persis dengan yang dipakai
     *    Coach\ReportController, jadi tidak ada dua versi aturan.
     * 3. Report status must be approved — no other status is downloadable.
     */
    public function download(Report $report)
    {
        if ($this->actingUser()->role === User::ROLE_COACH) {
            abort_unless(
                $this->authorization->canAccessReport($this->actingUser(), $report),
                403,
                'Anda tidak memiliki akses ke laporan ini.'
            );
        } else {
            $this->ensureSchoolAccess($report);
        }

        abort_unless($report->status === 'approved', 403, 'Hanya laporan yang sudah disetujui dapat diunduh.');

        $report->load(['coach', 'school', 'schoolClass', 'attendances.student', 'media', 'attendanceMedia']);

        // Build a safe filename: Coach-Report-{School}-{Coach}-{Date}
        $filename = 'Coach-Report-'
            . Str::slug($report->school->name) . '-'
            . Str::slug($report->coach->name) . '-'
            . $report->report_date->format('Y-m-d')
            . '.html';

        // Konteks tombol "Kembali" dan tautan "Lihat Video" pada halaman
        // unduhan: PIC kembali ke dashboard PIC, coach ke daftar laporannya
        // sendiri, role lain ke daftar laporan admin.
        $backTo = match ($this->actingUser()->role) {
            User::ROLE_SCHOOL_PIC => 'pic',
            User::ROLE_COACH      => 'coach',
            default               => 'admin',
        };

        return response()
            ->view('admin.reports.download', compact('report', 'backTo'))
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }
}