<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoachClass;
use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingScheduleTemplate;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use App\Services\ScheduleTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Pola jadwal per HARI + SEKOLAH (refactor 2026-09-25).
 *
 * Builder "Jadwal Semester" tidak lagi ada: pola dimasukkan lewat form jadwal
 * DIGISchool (tab hari, blok sekolah dengan tanggal mulai sendiri), dan
 * tanggal mulai TIDAK PERNAH global. Rute lama dipertahankan sebagai
 * pengalihan agar tautan/bookmark lama tidak mati.
 */
class ScheduleTemplateController extends Controller
{
    public function __construct(
        private AuthorizationService $authorization,
        private ActivityLogService $activityLog,
        private ScheduleTemplateService $templates,
    ) {
    }

    /**
     * Rute lama "Tambah Jadwal Semester" -> form pola DIGISchool.
     */
    public function semester()
    {
        $this->assertCanManage();

        return redirect()
            ->route('admin.schedules.create')
            ->with('success', 'Form jadwal semester digantikan form jadwal DIGISchool: pilih hari, lalu isi tanggal mulai dan jumlah pertemuan PER SEKOLAH.');
    }

    /**
     * Rute lama simpan semester -> form pola DIGISchool.
     */
    public function store()
    {
        $this->assertCanManage();

        return redirect()
            ->route('admin.schedules.create')
            ->with('error', 'Jadwal tidak disimpan: setiap sekolah harus punya tanggal mulai sendiri. Isi lewat form jadwal DIGISchool.');
    }

    /**
     * Daftar pola (dikelompokkan per hari + sekolah + tanggal mulai) beserta
     * jumlah sesi tergenerate.
     */
    public function index(Request $request)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');

        $activeDay = (int) $request->query('day', 0);
        if ($activeDay < 0 || $activeDay > 7) {
            $activeDay = 0;
        }

        $result = $this->templates->paginatedPatterns(
            $user,
            $activeDay > 0 ? $activeDay : null,
            [],
            20
        );

        return view('admin.schedules.templates', [
            'patterns'  => $result['patterns'],
            'paginator' => $result['paginator'],
            'activeDay' => $activeDay,
            'dayLabels' => TeachingScheduleTemplate::DAY_LABELS_UPPER,
        ]);
    }

    /**
     * Generate sesi yang kurang untuk SATU baris pola (mis. setelah libur
     * dihapus) — tidak menduplikasi sesi yang sudah ada.
     */
    public function generate(Request $request, TeachingScheduleTemplate $template)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');
        $this->assertSchoolInScope((int) $template->school_id);

        $result = $this->templates->generate($template, checkConflicts: true);

        $this->activityLog->log(
            $user,
            'schedule.template_generated',
            'teaching_schedule_template',
            $template->id,
            "Generate ulang sesi pola: {$template->pattern_name} — {$result['created']} sesi baru",
            ['created' => $result['created'], 'skipped' => $result['skipped']],
            $request,
        );

        return back()->with('success', $this->generateMessage($result));
    }

    /**
     * Generate sesi yang kurang untuk SATU POLA penuh (semua kelasnya).
     */
    public function generatePattern(Request $request)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');

        $day = (int) $request->input('day');
        $schoolId = (int) $request->input('school_id');
        $start = (string) $request->input('start_date');

        $result = $this->templates->generatePattern($user, $day, $schoolId, $start);

        $this->activityLog->log(
            $user,
            'schedule.pattern_generated',
            'teaching_schedule_template',
            null,
            "Generate ulang pola: hari {$day}, sekolah #{$schoolId}, mulai {$start} — {$result['created']} sesi baru",
            ['created' => $result['created'], 'skipped' => $result['skipped']],
            $request,
        );

        return back()->with('success', $this->generateMessage($result));
    }

    /**
     * Hapus satu pola penuh. Sesi yang sudah digenerate TETAP ADA (data bisnis
     * aktif tidak dihapus) — hanya tautan template_id yang dilepas.
     */
    public function destroyPattern(Request $request)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');

        $day = (int) $request->input('day');
        $schoolId = (int) $request->input('school_id');
        $start = (string) $request->input('start_date');
        $label = (string) $request->input('label', 'pola jadwal');

        $result = $this->templates->deletePattern($user, $day, $schoolId, $start);

        $this->activityLog->log(
            $user,
            'schedule.pattern_deleted',
            'teaching_schedule_template',
            null,
            "Pola jadwal dihapus: {$label} ({$result['sessions_kept']} sesi tetap tersimpan)",
            ['rows' => $result['rows'], 'sessions_kept' => $result['sessions_kept']],
            $request,
        );

        return back()->with('success', "Pola dihapus. {$result['sessions_kept']} sesi yang sudah dibuat tetap tersimpan sebagai jadwal biasa.");
    }

    /**
     * Hapus satu baris pola (satu kelas). Sesi tergenerate tetap tersimpan.
     */
    public function destroy(Request $request, TeachingScheduleTemplate $template)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');
        $this->assertSchoolInScope((int) $template->school_id);

        $sessionCount = $template->sessions()->count();

        $this->activityLog->log(
            $user,
            'schedule.template_deleted',
            'teaching_schedule_template',
            $template->id,
            "Baris pola dihapus: {$template->pattern_name} ({$sessionCount} sesi tetap tersimpan)",
            ['pattern_name' => $template->pattern_name, 'sessions_kept' => $sessionCount],
            $request,
        );

        $template->delete();

        return back()->with('success', "Baris pola dihapus. {$sessionCount} sesi yang sudah dibuat tetap tersimpan sebagai jadwal biasa.");
    }

    /**
     * @param  array{created: int, skipped: int, warnings: array<int, string>}  $result
     */
    private function generateMessage(array $result): string
    {
        $message = $result['created'] > 0
            ? "{$result['created']} sesi baru digenerate."
            : 'Tidak ada sesi baru yang perlu digenerate (semua pertemuan sudah ada).';

        if ($result['warnings'] !== []) {
            $message .= ' Perhatian: '.implode(' ', array_slice($result['warnings'], 0, 5));
        }

        return $message;
    }

    private function assertCanManage(): void
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');
    }

    private function assertSchoolInScope(int $schoolId): void
    {
        $schoolIds = $this->scopedSchoolIds($this->actingUser());
        if ($schoolIds !== null && !in_array($schoolId, $schoolIds, true)) {
            abort(403, 'Pola ini di luar scope Anda.');
        }
    }

    private function scopedSchoolIds(User $user): ?array
    {
        if ($user->isSuperAdmin() || $user->isRelationUser()) {
            return null;
        }

        return $user->assignedSchoolIds();
    }

    private function actingUser(): User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
