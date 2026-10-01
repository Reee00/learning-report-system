<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingSchedule;
use App\Models\TeachingScheduleTemplate;
use App\Models\User;
use App\Services\ScheduleTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Penjadwalan BERBASIS SESI (refactor 2026-09-29).
 *
 * Source of truth: `teaching_schedules` — bukan hasil generate mingguan.
 *
 * - `session_date` DIISI MANUAL dan boleh dikosongkan ("Belum dijadwalkan").
 * - Tanggal tidak harus berjarak 7 hari (10 Agu, 24 Agu, 31 Agu, 14 Sep).
 * - `meeting_number` = urutan pertemuan, TIDAK bergeser saat tanggal dipindah.
 * - Pola/template hanya METADATA (preferensi hari/jam/coach/target).
 * - Menghapus / mengubah konfigurasi tidak boleh menghilangkan sesi yang
 *   sudah punya laporan (laporan membawa absensi + media).
 * - Satu sesi maksimal satu laporan.
 */
class SessionBasedSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private SchoolClass $class;
    private SchoolClass $otherClass;
    private Program $program;
    private User $coach;
    private User $coachExtra;
    private User $relation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SD Sesi']);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 4A']);
        $this->otherClass = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 4B']);
        $this->program = Program::create(['name' => 'Coding Kids', 'code' => 'COD', 'status' => 'active']);

        Student::create(['class_id' => $this->class->id, 'name' => 'Murid Satu']);
        Student::create(['class_id' => $this->class->id, 'name' => 'Murid Dua']);

        $this->coach = $this->makeUser(User::ROLE_COACH, 'coach.utama');
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->class->id]);

        $this->coachExtra = $this->makeUser(User::ROLE_COACH, 'coach.tambahan');
        CoachClass::create(['coach_id' => $this->coachExtra->id, 'class_id' => $this->class->id]);

        $this->relation = $this->makeUser(User::ROLE_RELATION, 'relation');
    }

    private function makeUser(string $role, string $slug): User
    {
        return User::create([
            'name'     => ucwords(str_replace('.', ' ', $slug)),
            'email'    => $slug.'@test.test',
            'password' => Hash::make('password'),
            'role'     => $role,
        ]);
    }

    /**
     * Payload store sesi manual. Tanggal TIDAK wajib — itulah inti refactor.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sessionPayload(array $overrides = []): array
    {
        return array_merge([
            'school_id'   => $this->school->id,
            'class_id'    => $this->class->id,
            'program_id'  => $this->program->id,
            'coach_id'    => $this->coach->id,
            'start_time'  => '08:00',
            'end_time'    => '09:30',
            'student_count' => 12,
            'jalan_minggu_ini' => '1',
        ], $overrides);
    }

    /**
     * Buat sesi manual lewat jalur resmi (route store) supaya nomor pertemuan
     * benar-benar diberikan oleh modul jadwal, bukan disetel test.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createSession(array $overrides = []): TeachingSchedule
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $this->sessionPayload($overrides))
            ->assertRedirectToRoute('admin.schedules.index', ['view' => 'sesi']);

        return TeachingSchedule::latest('id')->firstOrFail();
    }

    /**
     * Template/pola (metadata) + sesinya, dibuat langsung supaya tanggalnya
     * dapat dikendalikan penuh oleh test.
     */
    private function makePattern(string $startDate = '2026-08-10', int $meetingCount = 4, ?int $classId = null): TeachingScheduleTemplate
    {
        return TeachingScheduleTemplate::create([
            'pattern_name'  => 'Pola Test',
            'start_date'    => $startDate,
            'meeting_count' => $meetingCount,
            'day_of_week'   => 1,
            'school_id'     => $this->school->id,
            'class_id'      => $classId ?? $this->class->id,
            'program_id'    => $this->program->id,
            'coach_id'      => $this->coach->id,
            'start_time'    => '08:00',
            'end_time'      => '09:30',
            'jalan_minggu_ini' => true,
        ]);
    }

    /**
     * Laporan + absensi pada satu sesi. Ini definisi "sesi punya riwayat".
     */
    private function attachReport(TeachingSchedule $session): Report
    {
        $report = Report::create([
            'coach_id'             => $this->coach->id,
            'school_id'            => $session->school_id,
            'class_id'             => $session->class_id,
            'teaching_schedule_id' => $session->id,
            'report_date'          => $session->session_date?->toDateString() ?? '2026-08-10',
            'lesson_material'      => 'Introduction',
            'goals_materi'         => 'Goals',
            'activity_report'      => 'Kegiatan',
            'status'               => 'submitted',
        ]);

        ReportAttendance::create([
            'report_id'  => $report->id,
            'student_id' => Student::where('class_id', $session->class_id)->firstOrFail()->id,
            'status'     => 'present',
        ]);

        return $report;
    }

    // =====================================================================
    // 1–4. Target kelas + sesi manual bertanggal bebas
    // =====================================================================

    public function test_class_holds_a_target_and_ten_manual_sessions_can_be_created(): void
    {
        $this->actingAs($this->relation)
            ->put(route('admin.classes.update', $this->class), [
                'school_id'       => $this->school->id,
                'name'            => $this->class->name,
                'target_meetings' => 10,
            ])
            ->assertRedirect();

        $this->assertSame(10, $this->class->refresh()->sessionTarget());

        for ($i = 1; $i <= 10; $i++) {
            $this->createSession(['session_date' => '2026-08-'.str_pad((string) (1 + $i), 2, '0', STR_PAD_LEFT)]);
        }

        $numbers = TeachingSchedule::where('class_id', $this->class->id)
            ->orderBy('meeting_number')
            ->pluck('meeting_number')
            ->all();

        $this->assertSame(range(1, 10), $numbers);

        $progress = SchoolClass::sessionProgressFor([$this->class->id])->get($this->class->id);
        $this->assertSame(10, $progress['target']);
        $this->assertSame(10, $progress['scheduled']);
        $this->assertSame(0, $progress['unscheduled']);
    }

    public function test_session_dates_do_not_have_to_be_weekly(): void
    {
        // Contoh dari requirement: 10 Agu, 24 Agu, 31 Agu, 14 Sep.
        $dates = ['2026-08-10', '2026-08-24', '2026-08-31', '2026-09-14'];

        foreach ($dates as $date) {
            $this->createSession(['session_date' => $date]);
        }

        $stored = TeachingSchedule::where('class_id', $this->class->id)
            ->orderBy('meeting_number')
            ->pluck('session_date')
            ->map(fn ($date) => $date->toDateString())
            ->all();

        $this->assertSame($dates, $stored);

        // Jaraknya 14, 7, dan 14 hari — bukan kelipatan 7 yang seragam, dan
        // sistem tidak pernah mengoreksi tanggalnya.
        $this->assertSame(14, (int) \Illuminate\Support\Carbon::parse($dates[0])->diffInDays($dates[1]));
        $this->assertSame(7, (int) \Illuminate\Support\Carbon::parse($dates[1])->diffInDays($dates[2]));
        $this->assertSame(14, (int) \Illuminate\Support\Carbon::parse($dates[2])->diffInDays($dates[3]));
    }

    public function test_session_can_be_created_without_a_date_and_is_shown_as_unscheduled(): void
    {
        $session = $this->createSession(['session_date' => null]);

        $this->assertNull($session->session_date);
        $this->assertNull($session->day_of_week);
        $this->assertSame('Pertemuan 1', $session->meetingLabel());

        $progress = SchoolClass::sessionProgressFor([$this->class->id])->get($this->class->id);
        $this->assertSame(1, $progress['unscheduled']);

        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi']))
            ->assertOk()
            ->assertSee('Belum dijadwalkan');
    }

    // =====================================================================
    // 5–6. Pindah tanggal tidak menggeser nomor pertemuan
    // =====================================================================

    public function test_editing_pertemuan_3_date_keeps_meeting_number_3(): void
    {
        foreach (['2026-08-10', '2026-08-17', '2026-08-24'] as $date) {
            $this->createSession(['session_date' => $date]);
        }

        $third = TeachingSchedule::where('class_id', $this->class->id)
            ->where('meeting_number', 3)
            ->firstOrFail();

        $this->actingAs($this->relation)
            ->patch(route('admin.schedules.reschedule', $third), ['session_date' => '2026-09-07'])
            ->assertRedirect();

        $third->refresh();

        $this->assertSame(3, $third->meeting_number);
        $this->assertSame('2026-09-07', $third->session_date->toDateString());

        // Nomor pertemuan sesi LAIN tidak ikut bergeser.
        $this->assertSame(
            [1, 2, 3],
            TeachingSchedule::where('class_id', $this->class->id)->orderBy('id')->pluck('meeting_number')->all()
        );
    }

    public function test_postponed_session_is_rescheduled_as_the_same_session(): void
    {
        $session = $this->createSession(['session_date' => '2026-08-10']);
        $originalId = $session->id;

        $this->actingAs($this->relation)
            ->patch(route('admin.schedules.status', $session), ['status' => TeachingSchedule::STATUS_POSTPONED])
            ->assertRedirect();

        $this->assertSame(TeachingSchedule::STATUS_POSTPONED, $session->refresh()->status);

        // Dicabut tanggalnya -> menunggu tanggal baru, tetap sesi yang sama.
        $this->actingAs($this->relation)
            ->patch(route('admin.schedules.reschedule', $session), ['session_date' => null])
            ->assertRedirect();

        $session->refresh();
        $this->assertNull($session->session_date);
        $this->assertSame(TeachingSchedule::STATUS_POSTPONED, $session->status);
        $this->assertSame(1, $session->meeting_number);

        // Diberi tanggal baru -> kembali terjadwal, TANPA membuat sesi baru.
        $this->actingAs($this->relation)
            ->patch(route('admin.schedules.reschedule', $session), ['session_date' => '2026-10-05'])
            ->assertRedirect();

        $session->refresh();
        $this->assertSame($originalId, $session->id);
        $this->assertSame(TeachingSchedule::STATUS_SCHEDULED, $session->status);
        $this->assertSame('2026-10-05', $session->session_date->toDateString());
        $this->assertSame(1, $session->meeting_number);
        $this->assertSame(1, TeachingSchedule::where('class_id', $this->class->id)->count());
    }

    public function test_reschedule_rejects_a_duplicate_slot(): void
    {
        $first = $this->createSession(['session_date' => '2026-08-10']);
        $second = $this->createSession(['session_date' => '2026-08-17']);

        $this->actingAs($this->relation)
            ->patch(route('admin.schedules.reschedule', $second), ['session_date' => '2026-08-10'])
            ->assertSessionHasErrors('session_date');

        $this->assertSame('2026-08-17', $second->refresh()->session_date->toDateString());
        $this->assertSame(1, $first->meeting_number);
    }

    // =====================================================================
    // 7–10. Riwayat aman, sesi kosong boleh dibersihkan
    // =====================================================================

    public function test_deleting_a_pattern_with_history_is_refused_entirely(): void
    {
        $template = $this->makePattern();
        app(ScheduleTemplateService::class)->generate($template);

        $this->assertSame(4, $template->sessions()->count());

        // Pertemuan 2 yang punya laporan + absensi.
        $keep = $template->sessions()->where('meeting_number', 2)->firstOrFail();
        $report = $this->attachReport($keep);

        // Aturan final 2026-10-01: satu sesi ber-riwayat menolak SELURUH
        // penghapusan pola — bukan hanya sesi itu yang disisakan.
        $result = app(ScheduleTemplateService::class)
            ->deletePattern($this->relation, 1, $this->school->id, '2026-08-10');

        $this->assertFalse($result['deleted']);
        $this->assertSame(1, $result['sessions_blocking']);
        $this->assertSame(0, $result['sessions_deleted']);

        $this->assertSame(4, TeachingSchedule::count());
        $this->assertSame(1, TeachingScheduleTemplate::count());
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'teaching_schedule_id' => $keep->id]);
        $this->assertDatabaseHas('report_attendances', ['report_id' => $report->id]);
    }

    public function test_pattern_without_history_is_deleted_with_its_generated_sessions(): void
    {
        $template = $this->makePattern('2026-09-07', 3);
        app(ScheduleTemplateService::class)->generate($template);

        $this->assertSame(3, $template->sessions()->count());

        $result = app(ScheduleTemplateService::class)
            ->deletePattern($this->relation, 1, $this->school->id, '2026-09-07');

        $this->assertTrue($result['deleted']);
        $this->assertSame(1, $result['rows']);
        $this->assertSame(3, $result['sessions_deleted']);

        // Tidak ada sesi hasil generate yang tertinggal sebagai sesi yatim.
        $this->assertSame(0, TeachingSchedule::count());
        $this->assertSame(0, TeachingScheduleTemplate::count());
    }

    public function test_report_keeps_its_attendance_after_the_session_is_rescheduled(): void
    {
        $session = $this->createSession(['session_date' => '2026-08-10']);
        $report = $this->attachReport($session);
        $studentId = ReportAttendance::where('report_id', $report->id)->value('student_id');

        $this->actingAs($this->relation)
            ->patch(route('admin.schedules.reschedule', $session), ['session_date' => '2026-09-21'])
            ->assertRedirect();

        $report->refresh();

        // Absensi tetap menempel pada laporan yang sama, dan laporan itu tetap
        // menempel pada sesi yang sama.
        $this->assertSame($session->id, $report->teaching_schedule_id);
        $this->assertSame(1, ReportAttendance::where('report_id', $report->id)->count());
        $this->assertSame(1, ReportAttendance::where('report_id', $report->id)->where('student_id', $studentId)->count());
    }

    public function test_session_with_history_cannot_be_deleted_from_the_schedule_module(): void
    {
        $session = $this->createSession(['session_date' => '2026-08-10']);
        $this->attachReport($session);

        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.destroy', $session))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertModelExists($session);
    }

    public function test_empty_session_can_be_deleted(): void
    {
        $session = $this->createSession(['session_date' => '2026-08-10']);

        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.destroy', $session))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertModelMissing($session);
    }

    // =====================================================================
    // 10–11. Tidak ada duplikat sesi / laporan
    // =====================================================================

    public function test_regenerating_a_pattern_does_not_duplicate_sessions(): void
    {
        $template = $this->makePattern();
        $service = app(ScheduleTemplateService::class);

        $first = $service->generate($template);
        $second = $service->generate($template);
        $third = $service->generatePattern($this->relation, 1, $this->school->id, '2026-08-10');

        $this->assertSame(4, $first['created']);
        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $third['created']);
        $this->assertSame(4, TeachingSchedule::where('class_id', $this->class->id)->count());
        $this->assertSame(
            4,
            TeachingSchedule::where('class_id', $this->class->id)->distinct()->count('session_date')
        );
    }

    public function test_regenerate_fills_the_gap_without_renumbering_other_sessions(): void
    {
        $template = $this->makePattern();
        $service = app(ScheduleTemplateService::class);
        $service->generate($template);

        $deleted = $template->sessions()->where('meeting_number', 2)->firstOrFail();
        $deleted->delete();

        $this->assertSame([1, 3, 4], $template->sessions()->orderBy('meeting_number')->pluck('meeting_number')->all());

        $result = $service->generate($template);
        $this->assertSame(1, $result['created']);

        // Lubangnya diisi, dan nomor sesi lain tidak bergeser.
        $sessions = $template->sessions()->orderBy('session_date')->get();
        $this->assertSame([1, 2, 3, 4], $sessions->pluck('meeting_number')->all());
        $this->assertSame('2026-08-17', $sessions->firstWhere('meeting_number', 2)->session_date->toDateString());
        $this->assertSame(4, $sessions->count());
    }

    public function test_one_session_holds_at_most_one_report(): void
    {
        $session = $this->createSession(['session_date' => '2026-08-10']);
        $this->attachReport($session);

        // Lapisan aplikasi menolak laporan kedua untuk sesi yang sama.
        $student = Student::where('class_id', $this->class->id)->firstOrFail();

        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), [
                'class_id'        => $this->class->id,
                'report_date'     => '2026-08-10',
                'lesson_material' => 'Materi Kedua',
                'goals_materi'    => 'Goals',
                'activity_report' => 'Kegiatan',
                'notes'           => null,
                'attendance'      => [$student->id => 'present'],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(1, Report::where('teaching_schedule_id', $session->id)->count());
    }

    // =====================================================================
    // 12–13. Label "Pertemuan N" & absensi
    // =====================================================================

    public function test_report_is_labelled_pertemuan_n_not_week_n(): void
    {
        $session = $this->createSession(['session_date' => '2026-08-10']);
        $student = Student::where('class_id', $this->class->id)->firstOrFail();

        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), [
                'class_id'        => $this->class->id,
                'report_date'     => '2026-08-10',
                'lesson_material' => 'Introduction',
                'goals_materi'    => 'Goals',
                'activity_report' => 'Kegiatan',
                'notes'           => null,
                'attendance'      => [$student->id => 'present'],
            ])
            ->assertSessionHasNoErrors();

        $report = Report::latest('id')->firstOrFail();
        $this->assertSame($session->id, $report->teaching_schedule_id);

        $this->actingAs($this->coach)
            ->get(route('coach.reports.show', $report))
            ->assertOk()
            ->assertSee('Pertemuan 1 — Introduction')
            ->assertDontSee('Week 1 — Introduction');

        $this->actingAs($this->coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Pertemuan 1 — Introduction');
    }

    public function test_attendance_stays_on_the_session_the_report_belongs_to(): void
    {
        $first = $this->createSession(['session_date' => '2026-08-10']);
        $second = $this->createSession(['session_date' => '2026-08-17']);

        $report = $this->attachReport($second);

        $this->assertSame($second->id, $report->teaching_schedule_id);
        $this->assertSame(0, Report::where('teaching_schedule_id', $first->id)->count());

        $attendance = ReportAttendance::where('report_id', $report->id)->firstOrFail();
        $this->assertSame($this->class->id, Student::findOrFail($attendance->student_id)->class_id);
    }

    // =====================================================================
    // 14–15. Coach tambahan & sesi nonaktif tetap seperti semula
    // =====================================================================

    public function test_shared_and_additional_coach_assignment_still_works(): void
    {
        $session = $this->createSession([
            'session_date'        => '2026-08-10',
            'additional_coaches'  => [$this->coachExtra->id],
        ]);

        // Coach tambahan melihat sesinya di daftar jadwal...
        $this->actingAs($this->coachExtra)
            ->get(route('admin.schedules.index', ['view' => 'sesi']))
            ->assertOk()
            ->assertSee('Grade 4A');

        // ...dan bisa membuat laporan untuk sesi itu walau tanpa coach_classes
        // (penugasan sementara di level jadwal).
        CoachClass::where('coach_id', $this->coachExtra->id)->delete();

        $student = Student::where('class_id', $this->class->id)->firstOrFail();

        $this->actingAs($this->coachExtra)
            ->post(route('coach.reports.store'), [
                'class_id'        => $this->class->id,
                'report_date'     => '2026-08-10',
                'lesson_material' => 'Introduction',
                'goals_materi'    => 'Goals',
                'activity_report' => 'Kegiatan',
                'notes'           => null,
                'attendance'      => [$student->id => 'present'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $session->id,
            Report::latest('id')->firstOrFail()->teaching_schedule_id
        );
    }

    public function test_inactive_session_behaviour_is_unchanged(): void
    {
        $session = $this->createSession(['session_date' => '2026-08-10']);

        $this->actingAs($this->relation)
            ->patch(route('admin.schedules.toggle-active', $session))
            ->assertRedirect();

        $session->refresh();

        // Nonaktif BUKAN dihapus: nomor, tanggal, dan statusnya tetap.
        $this->assertFalse($session->is_active);
        $this->assertSame(1, $session->meeting_number);
        $this->assertSame('2026-08-10', $session->session_date->toDateString());
        $this->assertSame('inactive', $session->displayStatus());
        $this->assertSame('Inactive', $session->statusLabel());

        // Sesi nonaktif tidak menerima laporan baru.
        $student = Student::where('class_id', $this->class->id)->firstOrFail();

        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), [
                'class_id'        => $this->class->id,
                'report_date'     => '2026-08-10',
                'lesson_material' => 'Introduction',
                'goals_materi'    => 'Goals',
                'activity_report' => 'Kegiatan',
                'notes'           => null,
                'attendance'      => [$student->id => 'present'],
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, Report::count());
    }

    public function test_deleting_a_pattern_from_the_ui_removes_its_generated_sessions(): void
    {
        $template = $this->makePattern();
        app(ScheduleTemplateService::class)->generate($template);

        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.pattern.destroy'), [
                'day'        => 1,
                'school_id'  => $this->school->id,
                'start_date' => '2026-08-10',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Pola dan pertemuan hasil generate-nya hilang bersama — tidak ada
        // sesi yatim yang masih tampil di jadwal.
        $this->assertSame(0, TeachingScheduleTemplate::count());
        $this->assertSame(0, TeachingSchedule::count());
    }

    public function test_deleting_a_pattern_from_the_ui_is_refused_when_a_session_has_history(): void
    {
        $template = $this->makePattern('2026-09-07', 3);
        app(ScheduleTemplateService::class)->generate($template);

        $keep = $template->sessions()->where('meeting_number', 1)->firstOrFail();
        $report = $this->attachReport($keep);

        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.pattern.destroy'), [
                'day'        => 1,
                'school_id'  => $this->school->id,
                'start_date' => '2026-09-07',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // Ditolak aman: pola, sesi, laporan, dan absensinya utuh semua.
        $this->assertModelExists($keep);
        $this->assertSame(1, TeachingScheduleTemplate::count());
        $this->assertSame(3, TeachingSchedule::count());
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'teaching_schedule_id' => $keep->id]);
        $this->assertDatabaseHas('report_attendances', ['report_id' => $report->id]);
    }

    public function test_create_session_page_is_available_for_managers(): void
    {
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.sessions.create'))
            ->assertOk()
            ->assertSee('Tambah Pertemuan');

        // Coach tidak boleh membuka form tambah pertemuan.
        $this->actingAs($this->coach)
            ->get(route('admin.schedules.sessions.create'))
            ->assertForbidden();
    }

    public function test_status_filter_lists_pertemuan_by_state(): void
    {
        $completed = $this->createSession(['session_date' => '2026-08-10']);
        $cancelled = $this->createSession(['session_date' => '2026-08-17']);

        $this->actingAs($this->relation)
            ->patch(route('admin.schedules.status', $completed), ['status' => TeachingSchedule::STATUS_COMPLETED])
            ->assertRedirect();
        $this->actingAs($this->relation)
            ->patch(route('admin.schedules.status', $cancelled), ['status' => TeachingSchedule::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame(TeachingSchedule::STATUS_COMPLETED, $completed->refresh()->status);
        $this->assertSame('Terlaksana', $completed->statusLabel());
        $this->assertSame('Dibatalkan', $cancelled->refresh()->statusLabel());

        // Sesi dibatalkan tidak lagi dihitung sebagai bagian rencana kelas.
        $progress = SchoolClass::sessionProgressFor([$this->class->id])->get($this->class->id);
        $this->assertSame(1, $progress['scheduled']);
        $this->assertSame(1, $progress['completed']);
    }
}
