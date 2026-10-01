<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeachingSchedule;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use App\Services\ScheduleTemplateService;
use App\Services\TeachingScheduleImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Modul manajemen jadwal mengajar (2026-09-11, direstruktur 2026-09-25,
 * dialihkan ke penjadwalan berbasis SESI 2026-09-29).
 *
 * Sejak 2026-09-29 SESI adalah record operasional yang otoritatif: tanggal
 * tiap pertemuan DIISI MANUAL, boleh diubah kapan saja, dan boleh dikosongkan
 * ("Belum dijadwalkan"). `meeting_number` adalah urutan pertemuan ("Pertemuan
 * N") dan tidak ikut bergeser saat tanggal dipindah — karena itu reschedule
 * punya aksi tersendiri yang hanya menyentuh `session_date`.
 *
 * Tab "Pola" tetap ada sebagai METADATA preferensi (hari favorit, jam, coach,
 * target jumlah pertemuan); tab "Sesi" adalah tempat kerja sebenarnya.
 *
 * Visibility: SuperAdmin dan Relation melihat semua, PIC School sekolah
 * plot-nya (dan bisa mengelola jadwal sekolah plot-nya), Coach hanya jadwal
 * yang melibatkannya (coach utama maupun tambahan). Scope diterapkan di query
 * sehingga tidak bisa dilewati lewat filter request.
 */
class ScheduleController extends Controller
{
    public function __construct(
        private AuthorizationService $authorization,
        private ActivityLogService $activityLog,
        private ScheduleTemplateService $templates,
    ) {
    }

    public function index(Request $request)
    {
        $user = $this->actingUser();

        // Dua tampilan: POLA (default, mengikuti alur Excel) dan SESI
        // (daftar pertemuan tergenerate, dengan filter tanggal).
        $view = $request->query('view') === 'sesi' ? 'sesi' : 'pola';

        $schools = $this->scopedSchools($user);
        $classes = $this->scopedClasses($user);
        $programs = Program::orderBy('name')->get();
        $coaches = $this->scopedCoaches($user);
        $canManage = $this->authorization->allows($user, 'schedules.manage');

        $activeDay = (int) $request->query('day', 1);
        if ($activeDay < 1 || $activeDay > 7) {
            $activeDay = 1;
        }

        $patterns = collect();
        $patternsPaginator = null;
        $schedules = null;
        $progress = collect();

        if ($view === 'pola') {
            $result = $this->templates->paginatedPatterns($user, $activeDay, [
                'school_id' => $request->query('school_id'),
                'class_id'  => $request->query('class_id'),
                'coach_id'  => $request->query('coach_id'),
            ], 15);

            $patterns = $result['patterns'];
            $patternsPaginator = $result['paginator'];
        } else {
            $query = TeachingSchedule::with(['school', 'schoolClass', 'program', 'coach', 'additionalCoaches'])
                ->orderByRaw('session_date IS NULL')
                ->orderByDesc('session_date')
                ->orderBy('start_time');

            $query = $this->applyScope($query, $user);

            if ($request->filled('school_id')) {
                $query->where('school_id', $request->school_id);
            }
            if ($request->filled('class_id')) {
                $query->where('class_id', $request->class_id);
            }
            if ($request->filled('program_id')) {
                $query->where('program_id', $request->program_id);
            }
            if ($request->filled('coach_id')) {
                $query->forCoach((int) $request->coach_id);
            }
            // Status sesi: kosong = tampilkan semua (riwayat tetap terlihat).
            // 'aktif' / 'nonaktif' menyaring flag operasional; sisanya adalah
            // status pertemuan (terjadwal, terlaksana, ditunda, dibatalkan).
            if ($request->query('status') === 'aktif') {
                $query->active();
            } elseif ($request->query('status') === 'nonaktif') {
                $query->where('is_active', false);
            } elseif (array_key_exists((string) $request->query('status'), TeachingSchedule::STATUS_LABELS)) {
                $query->where('status', $request->query('status'));
            }
            if ($request->query('status') === 'belum-dijadwalkan') {
                $query->unscheduled();
            }
            if ($request->filled('day')) {
                $query->where('day_of_week', (int) $request->day);
            }
            if ($request->filled('date_from')) {
                $query->whereDate('session_date', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('session_date', '<=', $request->date_to);
            }
            if ($request->filled('time_from')) {
                $query->whereTime('start_time', '>=', $request->time_from);
            }
            if ($request->filled('time_to')) {
                $query->whereTime('start_time', '<=', $request->time_to);
            }

            $schedules = $query->paginate(20)->withQueryString();

            // Target + progress "N/M pertemuan" per kelas yang benar-benar
            // tampil di halaman ini — satu query, bukan per baris.
            $progress = SchoolClass::sessionProgressFor(
                $schedules->getCollection()->pluck('class_id')->unique()->all()
            );
        }

        return view('admin.schedules.index', compact(
            'view', 'patterns', 'patternsPaginator', 'schedules', 'progress',
            'schools', 'classes', 'programs', 'coaches', 'canManage', 'activeDay'
        ));
    }

    /**
     * Halaman detail satu pola: daftar pertemuan yang sudah tergenerate.
     */
    public function patternShow(Request $request)
    {
        $user = $this->actingUser();
        $this->assertCanView();

        $day = (int) $request->query('day', 1);
        $schoolId = (int) $request->query('school');
        $start = (string) $request->query('start');

        abort_if($start === '' || $schoolId === 0, 404);

        $found = $this->templates->findPattern($user, $day, $schoolId, $start);
        abort_if($found === null, 404, 'Pola jadwal tidak ditemukan.');

        $canManage = $this->authorization->allows($user, 'schedules.manage');

        return view('admin.schedules.pattern', [
            'pattern'   => $found['pattern'],
            'sessions'  => $found['sessions'],
            'canManage' => $canManage,
        ]);
    }

    /**
     * Form pola jadwal DIGISchool: navigasi hari Senin–Sabtu, tiap hari berisi
     * blok sekolah yang punya TANGGAL MULAI dan JUMLAH PERTEMUAN sendiri
     * (mengikuti kolom KET pada workbook operasional).
     *
     * `?day=&school=&start=` membuka form dalam mode edit satu pola.
     */
    public function create(Request $request)
    {
        $user = $this->actingUser();
        $this->assertCanManage();

        $schools = $this->scopedSchools($user);
        $classes = $this->scopedClasses($user);
        $programs = Program::orderBy('name')->get();
        $coaches = $this->scopedCoaches($user);

        $activeDay = (int) $request->query('day', 1);
        if ($activeDay < 1 || $activeDay > 6) {
            $activeDay = 1;
        }

        $editing = null;
        $start = (string) $request->query('start', '');
        $schoolId = (int) $request->query('school');

        if ($start !== '' && $schoolId > 0) {
            $found = $this->templates->findPattern($user, $activeDay, $schoolId, $start);
            $editing = $found === null ? null : $found['pattern'];
        }

        return view('admin.schedules.bulk', [
            'schools'    => $schools,
            'programs'   => $programs,
            'coaches'    => $coaches,
            'activeDay'  => $activeDay,
            'dayLabels'  => self::DAY_LABELS,
            'masterData' => $this->masterDataPayload($schools, $classes, $coaches),
            'editing'    => $editing,
        ]);
    }

    /**
     * Simpan pola-pola dari form bulk: satu pengiriman boleh memuat banyak
     * sekolah pada banyak hari, masing-masing dengan TANGGAL MULAI dan JUMLAH
     * PERTEMUAN sendiri. Sesi pertemuan digenerate per pola oleh
     * ScheduleTemplateService.
     *
     * Semua blok divalidasi lebih dulu; bila SATU baris gagal, tidak ada pola
     * yang tersimpan (atomik) — pengguna memperbaiki lalu mengirim ulang.
     */
    public function storeBulk(Request $request)
    {
        $this->assertCanManage();

        $validated = $request->validate([
            'days'                                  => ['required', 'array', 'min:1'],
            'days.*.blocks'                         => ['required', 'array', 'min:1'],
            'days.*.blocks.*.school_id'             => ['required', 'integer', 'exists:schools,id'],
            // Tanggal mulai sengaja `nullable` di sini: validasinya ditangani
            // ScheduleTemplateService agar pesannya menyebut SEKOLAH dan baris
            // yang bermasalah, serta merujuk kolom KET pada workbook asli.
            // (§6: blok sekolah tanpa tanggal mulai yang valid dilaporkan,
            // bukan diam-diam diberi tanggal karangan.)
            'days.*.blocks.*.start_date'            => ['nullable', 'date'],
            'days.*.blocks.*.meeting_count'         => ['nullable', 'integer', 'min:1', 'max:60'],
            'days.*.blocks.*.departure_location'    => ['nullable', 'string', 'max:100'],
            'days.*.blocks.*.departure_time'        => ['nullable', 'date_format:H:i'],
            'days.*.blocks.*.arrival_time'          => ['nullable', 'date_format:H:i'],
            'days.*.blocks.*.rows'                  => ['required', 'array', 'min:1'],
        ]);

        // Kunci array `days` adalah nomor hari ISO (1=Senin..7=Minggu) sehingga
        // tidak ada field hari terpisah yang bisa tidak sinkron dengan posisinya.
        $days = [];
        foreach ($validated['days'] as $dayNumber => $day) {
            $dayNumber = (int) $dayNumber;
            if ($dayNumber < 1 || $dayNumber > 7) {
                continue;
            }

            $blocks = [];
            foreach ($day['blocks'] as $block) {
                $rows = [];
                foreach ($block['rows'] as $row) {
                    // Baris kosong (belum diisi) dilewati, bukan dianggap error.
                    if (($row['class_id'] ?? null) === null || ($row['coach_id'] ?? null) === null) {
                        continue;
                    }
                    $rows[] = $row;
                }

                if ($rows === []) {
                    continue;
                }

                $blocks[] = array_merge($block, ['rows' => $rows]);
            }

            if ($blocks !== []) {
                $days[$dayNumber] = ['blocks' => $blocks];
            }
        }

        if ($days === []) {
            return back()
                ->withInput()
                ->with('error', 'Tidak ada baris jadwal yang terisi. Isi minimal satu kelas dan coach.');
        }

        try {
            $result = $this->templates->build($this->actingUser(), ['days' => $days]);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            // Atomik: tidak ada pola tersimpan bila ada satu baris bermasalah.
            return back()
                ->withInput()
                ->withErrors(['schedules' => $exception->errors()['schedules'] ?? ['Jadwal tidak dapat disimpan.']]);
        }

        $this->activityLog->log(
            $this->actingUser(),
            'schedule.pattern_created',
            'teaching_schedule_template',
            null,
            "Pola jadwal dibuat: {$result['patterns']} pola, {$result['rows']} baris kelas, {$result['sessions']} sesi digenerate",
            ['patterns' => $result['patterns'], 'rows' => $result['rows'], 'sessions' => $result['sessions']],
            $request,
        );

        $message = "{$result['patterns']} pola jadwal tersimpan: {$result['rows']} kelas, {$result['sessions']} pertemuan digenerate.";
        if ($result['warnings'] !== []) {
            $message .= ' Perhatian: '.implode(' ', array_slice($result['warnings'], 0, 5));
        }

        return redirect()
            ->route('admin.schedules.index', ['view' => 'pola'])
            ->with('success', $message);
    }

    public function edit(TeachingSchedule $schedule)
    {
        $this->assertCanManage();
        $this->assertSchoolInScope($schedule);

        [$schools, $classes, $programs, $coaches] = $this->formData();
        $schedule->load('additionalCoaches');

        return view('admin.schedules.form', compact('schedule', 'schools', 'classes', 'programs', 'coaches'));
    }

    /**
     * Form SATU pertemuan baru (penjadwalan manual).
     *
     * Berbeda dari form pola (`create()`): di sini operator membuat satu sesi
     * untuk satu kelas pada satu tanggal — termasuk tanggal yang tidak
     * berjarak mingguan. Nomor pertemuannya diberikan backend saat simpan.
     */
    public function createSession()
    {
        $this->assertCanManage();

        [$schools, $classes, $programs, $coaches] = $this->formData();

        return view('admin.schedules.form', [
            'schedule' => new TeachingSchedule([
                'status' => TeachingSchedule::STATUS_SCHEDULED,
                'jalan_minggu_ini' => true,
            ]),
            'schools'  => $schools,
            'classes'  => $classes,
            'programs' => $programs,
            'coaches'  => $coaches,
        ]);
    }

    public function store(Request $request)
    {
        $this->assertCanManage();
        $attributes = $this->validateSchedule($request);

        // Nomor pertemuan diberikan di sini, bukan oleh form: "Pertemuan N"
        // adalah urutan pertemuan KELAS ini, dan operator tidak perlu
        // memikirkannya saat menambah sesi manual.
        $attributes['meeting_number'] = $this->nextMeetingNumber((int) $attributes['class_id']);

        $schedule = TeachingSchedule::create($attributes);
        $schedule->additionalCoaches()->sync($attributes['additional_coach_ids']);

        $this->activityLog->log(
            $this->actingUser(),
            'schedule.created',
            'teaching_schedule',
            $schedule->id,
            "Jadwal dibuat: {$schedule->schoolClass->name}, {$schedule->meetingLabel()}, {$this->dateLabel($schedule)}",
            ['school_id' => $schedule->school_id, 'class_id' => $schedule->class_id, 'coach_id' => $schedule->coach_id],
            $request,
        );

        return redirect()
            ->route('admin.schedules.index', ['view' => 'sesi'])
            ->with('success', $schedule->meetingLabel().' berhasil ditambahkan.');
    }

    public function update(Request $request, TeachingSchedule $schedule)
    {
        $this->assertCanManage();
        $this->assertSchoolInScope($schedule);

        $attributes = $this->validateSchedule($request, $schedule);

        // meeting_number sengaja TIDAK ikut diubah: mengedit/menjadwalkan ulang
        // sebuah pertemuan tidak boleh menukar urutan pertemuan.
        $schedule->update($attributes);
        $schedule->additionalCoaches()->sync($attributes['additional_coach_ids']);

        $this->activityLog->log(
            $this->actingUser(),
            'schedule.updated',
            'teaching_schedule',
            $schedule->id,
            "Jadwal diperbarui: {$schedule->schoolClass->name}, {$schedule->meetingLabel()}, {$this->dateLabel($schedule)}",
            ['school_id' => $schedule->school_id, 'class_id' => $schedule->class_id, 'coach_id' => $schedule->coach_id],
            $request,
        );

        return redirect()
            ->route('admin.schedules.index', ['view' => 'sesi'])
            ->with('success', $schedule->meetingLabel().' berhasil diperbarui.');
    }

    /**
     * Reschedule SATU pertemuan: hanya tanggalnya yang berpindah.
     *
     * Aksi terpisah dari `update()` supaya aturannya tidak bisa dilanggar
     * tidak sengaja oleh form yang lebih lengkap: di sini TIDAK ADA field
     * `meeting_number`, jadi urutan pertemuan dijamin tetap. Tanggal boleh
     * dikosongkan — pertemuan yang tanggalnya dicabut otomatis berstatus
     * "Ditunda" sampai tanggal barunya ditetapkan.
     *
     * Tidak ada generate/regenerate yang dijalankan: memindahkan tanggal tidak
     * pernah memerlukan regenerate.
     */
    public function reschedule(Request $request, TeachingSchedule $schedule)
    {
        $this->assertCanManage();
        $this->assertSchoolInScope($schedule);

        $validated = $request->validate([
            'session_date' => ['nullable', 'date'],
        ]);

        $newDate = $validated['session_date'] ?? null;

        if ($newDate !== null) {
            $duplicate = TeachingSchedule::query()
                ->where('school_id', $schedule->school_id)
                ->where('class_id', $schedule->class_id)
                ->whereKeyNot($schedule->id)
                ->whereDate('session_date', $newDate)
                ->whereTime('start_time', $schedule->start_time)
                ->whereTime('end_time', $schedule->end_time)
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'session_date' => 'Sesi untuk kelas, tanggal, dan jam ini sudah ada.',
                ]);
            }
        }

        $oldDate = $schedule->session_date?->toDateString();

        $schedule->session_date = $newDate;

        // Tanggal diisi -> pertemuan kembali terjadwal; tanggal dicabut ->
        // pertemuan menunggu tanggal baru.
        if ($newDate !== null && $schedule->status === TeachingSchedule::STATUS_POSTPONED) {
            $schedule->status = TeachingSchedule::STATUS_SCHEDULED;
        } elseif ($newDate === null && $schedule->status === TeachingSchedule::STATUS_SCHEDULED) {
            $schedule->status = TeachingSchedule::STATUS_POSTPONED;
        }

        $schedule->save();

        $this->activityLog->log(
            $this->actingUser(),
            'schedule.rescheduled',
            'teaching_schedule',
            $schedule->id,
            "Pertemuan dipindah: {$schedule->schoolClass->name}, {$schedule->meetingLabel()}, "
                .($oldDate ?? 'belum dijadwalkan').' -> '.($newDate ?? 'belum dijadwalkan'),
            ['session_date' => $newDate, 'previous_session_date' => $oldDate],
            $request,
        );

        return back()->with(
            'success',
            $newDate === null
                ? $schedule->meetingLabel().' ditandai belum dijadwalkan (Ditunda). Nomor pertemuannya tidak berubah.'
                : $schedule->meetingLabel().' dipindah ke '.$schedule->session_date->translatedFormat('d F Y').'. Nomor pertemuannya tidak berubah.'
        );
    }

    /**
     * Ubah status operasional satu pertemuan (Terjadwal / Terlaksana /
     * Ditunda / Dibatalkan). Tidak menyentuh nomor pertemuan, tanggal,
     * laporan, maupun absensi yang sudah ada.
     */
    public function setStatus(Request $request, TeachingSchedule $schedule)
    {
        $this->assertCanManage();
        $this->assertSchoolInScope($schedule);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(TeachingSchedule::STATUS_LABELS))],
        ]);

        $schedule->status = $validated['status'];
        $schedule->save();

        $this->activityLog->log(
            $this->actingUser(),
            'schedule.status_changed',
            'teaching_schedule',
            $schedule->id,
            "Status pertemuan diubah: {$schedule->schoolClass->name}, {$schedule->meetingLabel()} -> {$schedule->statusLabel()}",
            ['status' => $schedule->status],
            $request,
        );

        return back()->with('success', $schedule->meetingLabel().' berstatus '.$schedule->statusLabel().'.');
    }

    /**
     * Import jadwal dari XLSX/XLS/CSV. Dua format dideteksi otomatis oleh
     * service: template ternormalisasi (sesi per tanggal) atau workbook
     * master perusahaan (sheet mingguan DIGISchool -> POLA per hari + sekolah,
     * tanggal mulai dibaca dari kolom KET tiap blok sekolah, lalu sesi
     * pertemuan digenerate sebanyak `meetings`).
     */
    public function import(Request $request)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');

        $validated = $request->validate([
            'file'     => 'required|file|mimes:xlsx,xls,csv|max:51200',
            'meetings' => 'nullable|integer|min:1|max:60',
        ]);

        $result = app(TeachingScheduleImportService::class)->import(
            $user,
            $validated['file'],
            isset($validated['meetings']) ? (int) $validated['meetings'] : null,
        );

        if ($result['imported'] > 0) {
            $this->activityLog->log(
                $user,
                'schedule.imported',
                'teaching_schedule',
                null,
                "Import jadwal: {$result['imported']} baris berhasil, {$result['skipped']} dilewati",
                ['imported' => $result['imported'], 'skipped' => $result['skipped']],
                $request,
            );
        }

        $message = "{$result['imported']} jadwal berhasil diimport.";
        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} baris/sesi dilewati (data tidak lengkap, sudah ada, atau referensi tidak ditemukan di master data).";
        }
        if ($result['errors'] !== []) {
            $message .= ' Detail: ' . implode(' ', array_slice(array_unique($result['errors']), 0, 5));
        }

        return back()->with('success', $message);
    }

    public function destroy(Request $request, TeachingSchedule $schedule)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');

        $this->assertSchoolInScope($schedule);

        // Sesi yang sudah punya riwayat (laporan + absensi + media) tidak boleh
        // dihapus dari sini: menghapusnya berarti melepas laporan dari
        // sesinya. Untuk pertemuan yang batal, pakai status "Dibatalkan" atau
        // nonaktifkan sesinya — riwayatnya tetap utuh.
        if ($schedule->hasHistory()) {
            return back()->with('error', "{$schedule->meetingLabel()} sudah punya laporan, jadi tidak bisa dihapus. "
                .'Gunakan status "Dibatalkan" atau nonaktifkan sesinya supaya riwayat tetap tersimpan.');
        }

        $this->activityLog->log(
            $user,
            'schedule.deleted',
            'teaching_schedule',
            $schedule->id,
            "Jadwal dihapus: {$schedule->schoolClass->name}, {$schedule->meetingLabel()}, {$this->dateLabel($schedule)}",
            request: $request,
        );

        $schedule->delete();

        return back()->with('success', 'Sesi berhasil dihapus.');
    }

    /**
     * Aktifkan / nonaktifkan SATU sesi mengajar.
     *
     * Sesi nonaktif tidak dihapus: meeting_number dan session_date tetap, dan
     * laporan yang sudah ada tidak tersentuh. Yang berubah hanya apakah sesi
     * ini dihitung sebagai sesi mengajar aktif (reminder + tuntutan laporan).
     */
    public function toggleActive(Request $request, TeachingSchedule $schedule)
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');

        $this->assertSchoolInScope($schedule);

        $schedule->is_active = ! $schedule->is_active;
        $schedule->save();

        $this->activityLog->log(
            $user,
            $schedule->is_active ? 'schedule.activated' : 'schedule.deactivated',
            'teaching_schedule',
            $schedule->id,
            ($schedule->is_active ? 'Sesi diaktifkan: ' : 'Sesi dinonaktifkan: ')
                ."{$schedule->schoolClass->name}, {$schedule->meetingLabel()}, {$this->dateLabel($schedule)}",
            request: $request,
        );

        return back()->with(
            'success',
            $schedule->is_active
                ? 'Sesi diaktifkan kembali.'
                : 'Sesi dinonaktifkan. Sesi tetap tersimpan dan bisa diaktifkan lagi kapan saja.',
        );
    }

    /**
     * Template CSV — mengikuti pola template siswa.
     */
    public function template()
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="template_jadwal_mengajar.csv"',
        ];

        $rows = [
            [
                'tanggal', 'nama_sekolah', 'nama_kelas', 'email_coach', 'jam_mulai', 'jam_selesai',
                'topik', 'program', 'email_coach_tambahan', 'jumlah_murid', 'tools_dk', 'tools_rk',
                'jalan_minggu_ini', 'keterangan',
            ],
            [
                '2026-09-15', 'SD Harapan Bangsa', 'Grade 5A', 'coach@lrs.com', '08:00', '09:30',
                'Storytelling Visual', 'Coding', '', '12', 'Laptop 12', '', '1', 'Sesi perdana',
            ],
            [
                '2026-09-16', 'SD Harapan Bangsa', 'Grade 5A', 'coach@lrs.com', '08:00', '09:30',
                'Prompting AI', 'Coding', 'gilbran@lrs.com', '12', '', 'Box RK A', '1', '',
            ],
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Roster murid sebuah kelas (dari master students yang existing —
     * jadwal tidak pernah menduplikasi data murid). Dipakai form jadwal
     * untuk menampilkan murid kelas terpilih dan mengisi jumlah murid.
     */
    public function classStudents(Request $request, SchoolClass $class)
    {
        $this->assertCanManage();
        $this->assertSchoolInScopeForId((int) $class->school_id);

        return response()->json([
            'school_id' => (int) $class->school_id,
            'class_id'  => $class->id,
            'count'     => $class->students()->count(),
            'students'  => $class->students()->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    // ------------------------------------------------------------------
    // Validasi
    // ------------------------------------------------------------------

    /**
     * Validasi lengkap sesi mengajar: kombinasi sekolah/kelas, coach utama +
     * tambahan (harus coach yang ter-assign ke kelas), format jam, serta
     * pencegahan duplikat sesi dan bentrok jadwal coach.
     *
     * @return array atribut siap simpan (termasuk additional_coach_ids).
     */
    private function validateSchedule(Request $request, ?TeachingSchedule $ignore = null): array
    {
        $validated = $request->validate($this->scheduleRules());

        return $this->buildScheduleAttributes(
            $validated,
            $request->boolean('jalan_minggu_ini', true),
            $ignore,
        );
    }

    /**
     * Aturan validasi bentuk untuk SATU sesi. Dipakai form satuan maupun
     * form bulk (per baris) — tidak ada aturan yang ditulis dua kali.
     *
     * @return array<string, array<int, mixed>>
     */
    private function scheduleRules(): array
    {
        return [
            // Tanggal boleh kosong: "Belum dijadwalkan" adalah keadaan yang sah
            // dalam penjadwalan berbasis sesi.
            'session_date'       => ['nullable', 'date'],
            'status'             => ['nullable', Rule::in(array_keys(TeachingSchedule::STATUS_LABELS))],
            'school_id'          => ['required', 'integer', 'exists:schools,id'],
            'class_id'           => ['required', 'integer', 'exists:classes,id'],
            'program_id'         => ['nullable', 'integer', 'exists:programs,id'],
            'coach_id'           => ['required', 'integer', 'exists:users,id'],
            'additional_coaches' => ['nullable', 'array'],
            'additional_coaches.*' => ['integer', 'exists:users,id', 'distinct'],
            'start_time'         => ['required', 'date_format:H:i'],
            'end_time'           => ['required', 'date_format:H:i'],
            'student_count'      => ['nullable', 'integer', 'min:0', 'max:255'],
            'tools_dk'           => ['nullable', 'string', 'max:255'],
            'tools_rk'           => ['nullable', 'string', 'max:255'],
            'jalan_minggu_ini'   => ['nullable', 'boolean'],
            'keterangan'         => ['nullable', 'string', 'max:1000'],
            'topic'              => ['nullable', 'string', 'max:255'],
            'departure_location' => ['nullable', 'string', 'max:100'],
            'departure_time'     => ['nullable', 'date_format:H:i'],
            'arrival_time'       => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Validasi bisnis bersama untuk satu sesi (dipakai form satuan dan bulk):
     * scope sekolah, kelas milik sekolah, coach ter-assign ke kelas, jam
     * selesai > jam mulai, duplikat sesi, dan bentrok jadwal coach.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>  atribut siap simpan (termasuk additional_coach_ids)
     */
    private function buildScheduleAttributes(array $validated, bool $jalanMingguIni, ?TeachingSchedule $ignore = null): array
    {
        // whereTime (SQLite: strftime) membandingkan "HH:MM:SS" — pad nilai.
        foreach (['start_time', 'end_time'] as $field) {
            if (isset($validated[$field]) && strlen($validated[$field]) === 5) {
                $validated[$field] .= ':00';
            }
        }

        $user = $this->actingUser();

        // Scope sekolah: PIC hanya boleh mengelola sekolah plot-nya.
        $schoolIds = $this->scopedSchoolIds($user);
        if ($schoolIds !== null && !in_array((int) $validated['school_id'], $schoolIds, true)) {
            abort(403, 'Sekolah ini di luar scope Anda.');
        }

        // Kelas harus milik sekolah yang dipilih.
        $class = SchoolClass::findOrFail($validated['class_id']);
        if ((int) $class->school_id !== (int) $validated['school_id']) {
            throw $this->validationException([
                'class_id' => 'Kelas tidak terdaftar pada sekolah yang dipilih.',
            ]);
        }

        // Coach utama wajib sudah ter-assign permanen ke kelas ini.
        //
        // Coach TAMBAHAN berbeda: sesuai aturan bisnis, ia boleh ditugaskan
        // sementara di level JADWAL walau belum punya baris coach_classes untuk
        // sekolah/kelas ini. Penugasan sementara itu tidak membuat baris
        // coach_classes baru — aksesnya mengalir dari pivot
        // teaching_schedule_coach pada sesi ini saja (lihat
        // AuthorizationService::canReportOnClass). Konsekuensinya: mencopot
        // coach dari jadwal otomatis mencabut aksesnya, tanpa menghapus data
        // laporan historis miliknya.
        $primaryCoachId = (int) $validated['coach_id'];

        $primaryAssigned = User::where('role', User::ROLE_COACH)
            ->whereKey($primaryCoachId)
            ->whereHas('coachClasses', fn ($q) => $q->where('class_id', $class->id))
            ->exists();

        if (! $primaryAssigned) {
            $name = User::whereKey($primaryCoachId)->value('name') ?? 'Coach';
            throw $this->validationException([
                'coach_id' => "Coach {$name} belum di-assign ke kelas {$class->name}. Atur assignment coach terlebih dahulu.",
            ]);
        }

        // Coach tambahan tetap harus berperan sebagai coach (role) — divalidasi
        // di sini karena daftar pilihannya memuat seluruh coach dalam scope.
        $additionalIds = array_values(array_unique(array_map(
            'intval',
            array_filter($validated['additional_coaches'] ?? [], 'is_numeric')
        )));
        $additionalIds = array_values(array_filter(
            $additionalIds,
            fn (int $id) => $id !== $primaryCoachId
        ));

        if ($additionalIds !== []) {
            $validCoachIds = User::where('role', User::ROLE_COACH)
                ->whereIn('id', $additionalIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $invalid = array_diff($additionalIds, $validCoachIds);
            if ($invalid !== []) {
                $names = User::whereIn('id', $invalid)->pluck('name')->implode(', ');
                throw $this->validationException([
                    'additional_coaches' => "Akun berikut bukan coach: {$names}.",
                ]);
            }
        }

        $coachIds = array_values(array_unique(array_merge([$primaryCoachId], $additionalIds)));
        // Jam selesai harus setelah jam mulai.
        if ($validated['end_time'] <= $validated['start_time']) {
            throw $this->validationException([
                'end_time' => 'Jam selesai harus setelah jam mulai.',
            ]);
        }

        // Sesi tanpa tanggal belum menempati slot mana pun, jadi pemeriksaan
        // duplikat dan bentrok coach di bawah dilewati — keduanya butuh
        // tanggal untuk bisa dibandingkan.
        $sessionDate = $validated['session_date'] ?? null;

        if ($sessionDate !== null) {
            // Duplikat: sesi identik (sekolah + kelas + tanggal + jam) sudah ada.
            $duplicate = TeachingSchedule::query()
                ->where('school_id', $validated['school_id'])
                ->where('class_id', $validated['class_id'])
                ->whereDate('session_date', $sessionDate)
                ->whereTime('start_time', $validated['start_time'])
                ->whereTime('end_time', $validated['end_time'])
                ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
                ->exists();

            if ($duplicate) {
                throw $this->validationException([
                    'class_id' => 'Sesi untuk kelas, tanggal, dan jam ini sudah ada.',
                ]);
            }

            // Bentrok jadwal coach: coach yang sama tidak boleh mengajar dua
            // sesi yang beririsan waktunya pada tanggal yang sama.
            $this->assertNoCoachConflict($coachIds, $validated, $ignore);
        }

        return [
            'school_id'          => (int) $validated['school_id'],
            'class_id'           => (int) $validated['class_id'],
            'program_id'         => $validated['program_id'] ?? null,
            'coach_id'           => (int) $validated['coach_id'],
            'session_date'       => $sessionDate,
            'status'             => $validated['status'] ?? TeachingSchedule::STATUS_SCHEDULED,
            'student_count'      => $validated['student_count'] ?? null,
            'start_time'         => $validated['start_time'],
            'end_time'           => $validated['end_time'],
            'tools_dk'           => $validated['tools_dk'] ?? null,
            'tools_rk'           => $validated['tools_rk'] ?? null,
            'jalan_minggu_ini'   => $jalanMingguIni,
            'keterangan'         => $validated['keterangan'] ?? null,
            'topic'              => $validated['topic'] ?? null,
            'departure_location' => $validated['departure_location'] ?? null,
            'departure_time'     => $validated['departure_time'] ?? null,
            'arrival_time'       => $validated['arrival_time'] ?? null,
            'additional_coach_ids' => $additionalIds,
        ];
    }

    /**
     * @param  array<int, int>  $coachIds
     */
    private function assertNoCoachConflict(array $coachIds, array $validated, ?TeachingSchedule $ignore): void
    {
        $conflicts = TeachingSchedule::query()
            ->whereDate('session_date', $validated['session_date'])
            ->whereTime('start_time', '<', $validated['end_time'])
            ->whereTime('end_time', '>', $validated['start_time'])
            ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
            ->with(['coach', 'schoolClass'])
            ->get();

        foreach ($conflicts as $existing) {
            $involved = array_merge(
                [$existing->coach_id],
                $existing->additionalCoaches->pluck('id')->all()
            );

            foreach (array_intersect($coachIds, array_map('intval', $involved)) as $coachId) {
                $coach = User::find($coachId);
                throw $this->validationException([
                    'coach_id' => "Coach {$coach->name} sudah punya jadwal "
                        . "{$existing->schoolClass->name} ({$existing->start_time->format('H:i')}–{$existing->end_time->format('H:i')}) "
                        . 'pada tanggal dan jam yang sama.',
                ]);
            }
        }
    }


    private function validationException(array $errors): \Illuminate\Validation\ValidationException
    {
        return \Illuminate\Validation\ValidationException::withMessages($errors);
    }

    /**
     * Nomor pertemuan berikutnya untuk sebuah kelas: melanjutkan yang terbesar
     * supaya tidak pernah ada dua sesi bernomor sama dalam satu kelas.
     */
    private function nextMeetingNumber(int $classId): int
    {
        return (int) TeachingSchedule::where('class_id', $classId)->max('meeting_number') + 1;
    }

    /**
     * Label tanggal untuk log aktivitas — sesi boleh belum bertanggal.
     */
    private function dateLabel(TeachingSchedule $schedule): string
    {
        return $schedule->session_date?->format('Y-m-d') ?? 'belum dijadwalkan';
    }

    // ------------------------------------------------------------------
    // Data formulir & scope
    // ------------------------------------------------------------------

    private function formData(): array
    {
        $user = $this->actingUser();

        return [
            $this->scopedSchools($user),
            $this->scopedClasses($user),
            Program::orderBy('name')->get(),
            $this->scopedCoaches($user),
        ];
    }

    /**
     * Dropdown hanya berisi entitas dalam scope user.
     */
    private function scopedSchools(User $user)
    {
        $schoolIds = $this->scopedSchoolIds($user);

        return School::orderBy('name')
            ->when($schoolIds !== null, fn ($q) => $q->whereIn('id', $schoolIds))
            ->get();
    }

    private function scopedClasses(User $user)
    {
        $schoolIds = $this->scopedSchoolIds($user);

        return SchoolClass::with(['school', 'programs', 'coachAssignments.coach'])
            ->orderBy('name')
            ->when($schoolIds !== null, fn ($q) => $q->whereIn('school_id', $schoolIds))
            ->get();
    }

    private function scopedCoaches(User $user)
    {
        $schoolIds = $this->scopedSchoolIds($user);

        return User::where('role', User::ROLE_COACH)
            ->when($schoolIds !== null, function ($q) use ($schoolIds) {
                // Selain coach yang ter-assign permanen (coach_classes), daftar
                // ini juga memuat coach yang sudah terlibat pada jadwal sekolah
                // dalam scope. Itu perlu karena coach pendamping boleh ditugaskan
                // sementara di level jadwal tanpa assignment kelas permanen.
                $q->where(function ($inner) use ($schoolIds) {
                    $inner->whereHas('coachClasses.schoolClass', fn ($c) => $c->whereIn('school_id', $schoolIds))
                        ->orWhereHas('teachingSchedules', fn ($s) => $s->whereIn('school_id', $schoolIds))
                        ->orWhereHas('additionalSchedules', fn ($s) => $s->whereIn('teaching_schedules.school_id', $schoolIds));
                });
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Label hari operasional DIGISchool. Senin–Sabtu (1–6) sesuai ISO-8601
     * yang juga dipakai kolom teaching_schedules.day_of_week.
     */
    private const DAY_LABELS = [
        1 => 'SENIN',
        2 => 'SELASA',
        3 => 'RABU',
        4 => 'KAMIS',
        5 => 'JUMAT',
        6 => 'SABTU',
    ];

    /**
     * Data master untuk dropdown dependen di form bulk: kelas per sekolah,
     * program yang valid per kelas, dan coach yang ter-assign per kelas.
     * Semuanya dari relasi yang sudah ada — tidak ada master data baru.
     *
     * @return array{schools: array, classes: array}
     */
    private function masterDataPayload($schools, $classes, $coaches): array
    {
        $classes->load(['programs', 'coachAssignments.coach']);

        return [
            'schools' => $schools->map(fn (School $school) => [
                'id' => (int) $school->id,
                'name' => $school->name,
            ])->values()->all(),
            'classes' => $classes->map(fn (SchoolClass $class) => [
                'id' => (int) $class->id,
                'name' => $class->name,
                'school_id' => (int) $class->school_id,
                'programs' => $class->programs
                    ->map(fn (Program $program) => ['id' => (int) $program->id, 'name' => $program->name])
                    ->values()->all(),
                'coaches' => $class->coachAssignments
                    ->map(fn ($assignment) => $assignment->coach)
                    ->filter()
                    ->map(fn (User $coach) => ['id' => (int) $coach->id, 'name' => $coach->name])
                    ->unique('id')
                    ->values()->all(),
            ])->values()->all(),
            'coaches' => $coaches->map(fn (User $coach) => [
                'id' => (int) $coach->id,
                'name' => $coach->name,
            ])->values()->all(),
        ];
    }

    private function assertCanManage(): void
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.manage'), 403, 'Permission tidak mencukupi.');
    }

    private function assertCanView(): void
    {
        $user = $this->actingUser();
        abort_unless($this->authorization->allows($user, 'schedules.view'), 403, 'Permission tidak mencukupi.');
    }

    private function assertSchoolInScope(TeachingSchedule $schedule): void
    {
        $schoolIds = $this->scopedSchoolIds($this->actingUser());
        if ($schoolIds !== null && !in_array((int) $schedule->school_id, $schoolIds, true)) {
            abort(403, 'Jadwal ini di luar scope Anda.');
        }
    }

    private function assertSchoolInScopeForId(int $schoolId): void
    {
        $schoolIds = $this->scopedSchoolIds($this->actingUser());
        if ($schoolIds !== null && !in_array($schoolId, $schoolIds, true)) {
            abort(403, 'Kelas ini di luar scope Anda.');
        }
    }

    /**
     * Scope berdasarkan role: SuperAdmin/Relation global, PIC sekolah plot,
     * Coach jadwal yang melibatkannya (coach utama maupun tambahan).
     */
    private function applyScope($query, User $user)
    {
        if ($user->isSuperAdmin() || $user->isRelationUser()) {
            return $query;
        }

        if ($user->role === User::ROLE_SCHOOL_PIC) {
            return $query->whereIn('school_id', $user->assignedSchoolIds());
        }

        if ($user->role === User::ROLE_COACH) {
            return $query->forCoach($user->id);
        }

        // Role lain (mis. SPV/Finance/Teacher) tidak memiliki akses jadwal.
        abort(403, 'Anda tidak memiliki akses ke jadwal mengajar.');
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
