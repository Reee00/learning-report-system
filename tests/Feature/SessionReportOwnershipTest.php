<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingSchedule;
use App\Models\User;
use App\Services\ReportReminderService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Aturan final 2026-09-28 — SATU SESI = SATU LAPORAN.
 *
 * Satu sesi mengajar (`teaching_schedules`) hanya boleh punya satu laporan,
 * siapa pun pembuatnya: coach utama, coach pendamping tetap, atau coach
 * pendamping sementara. Yang dilaporkan adalah SESINYA, bukan coach-nya —
 * karena itu `reports.teaching_schedule_id` menjadi rujukannya, dan
 * keunikannya dijaga indeks unik di database (bukan hanya validasi form).
 *
 * Turunannya diuji di sini juga:
 * - sesi nonaktif menolak laporan BARU tetapi tidak menghapus riwayat;
 * - coach pendamping melihat laporan tunggal sesi yang dia ajar, dan tidak
 *   melihat laporan sesi lain di sekolah yang sama;
 * - reminder dihitung per SESI, bukan per coach.
 */
class SessionReportOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private SchoolClass $class;
    private Program $program;
    private User $primary;
    private User $additional;
    private User $outsider;
    private User $relation;

    private const DATE = '2026-03-02';

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SD Syafana']);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 4A']);

        $otherSchool = School::create(['name' => 'SD Lain']);
        $otherClass = SchoolClass::create(['school_id' => $otherSchool->id, 'name' => 'Grade 9Z']);

        $this->program = Program::create(['name' => 'Coding', 'code' => 'COD', 'status' => 'active']);

        // Coach utama: ter-assign permanen ke kelas sesi.
        $this->primary = $this->makeUser(User::ROLE_COACH, 'coach.wildan');
        CoachClass::create(['coach_id' => $this->primary->id, 'class_id' => $this->class->id]);

        // Coach pendamping: TIDAK punya assignment permanen ke kelas sesi.
        $this->additional = $this->makeUser(User::ROLE_COACH, 'coach.renaldy');
        CoachClass::create(['coach_id' => $this->additional->id, 'class_id' => $otherClass->id]);

        // Coach di luar sesi: hanya di sekolah lain.
        $this->outsider = $this->makeUser(User::ROLE_COACH, 'coach.outsider');
        CoachClass::create(['coach_id' => $this->outsider->id, 'class_id' => $otherClass->id]);

        $this->relation = $this->makeUser(User::ROLE_RELATION, 'relation');
    }

    private function makeUser(string $role, string $slug): User
    {
        return User::create([
            'name' => ucwords(str_replace('.', ' ', $slug)),
            'email' => $slug.'@test.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }

    /**
     * Sesi lewat form CRUD resmi — jalur yang sama dengan UI admin.
     *
     * @param  array<int, User>  $additionalCoaches
     */
    private function teachSession(array $additionalCoaches = [], array $overrides = []): TeachingSchedule
    {
        $payload = array_merge([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'program_id' => $this->program->id,
            'coach_id' => $this->primary->id,
            'additional_coaches' => array_map(fn (User $u) => $u->id, $additionalCoaches),
            'session_date' => self::DATE,
            'start_time' => '08:00',
            'end_time' => '09:30',
            'student_count' => 12,
        ], $overrides);

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.store'), $payload)
            ->assertSessionHasNoErrors();

        return TeachingSchedule::where('class_id', $payload['class_id'])
            ->whereDate('session_date', $payload['session_date'])
            ->firstOrFail();
    }

    private function student(): Student
    {
        return Student::create([
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'name' => 'Murid Satu',
        ]);
    }

    /**
     * Payload form laporan. Form memang hanya mengirim kelas + tanggal —
     * sesinya diresolusi backend, jadi tes ini pun tidak pernah menyebut
     * teaching_schedule_id saat submit.
     */
    private function reportPayload(array $overrides = []): array
    {
        return array_merge([
            'class_id' => $this->class->id,
            'report_date' => self::DATE,
            'lesson_material' => 'Materi',
            'goals_materi' => 'Goals',
            'activity_report' => 'Aktivitas',
            'attendance' => [$this->student()->id => 'present'],
        ], $overrides);
    }

    private function submitReport(User $coach, array $overrides = [])
    {
        return $this->actingAs($coach)->post(route('coach.reports.store'), $this->reportPayload($overrides));
    }

    // ===== 1. Satu sesi = satu laporan =====

    public function test_report_created_by_primary_coach_is_linked_to_the_session(): void
    {
        $session = $this->teachSession();

        $this->submitReport($this->primary)->assertRedirect()->assertSessionHasNoErrors();

        $report = Report::firstOrFail();
        $this->assertSame($session->id, (int) $report->teaching_schedule_id);
        $this->assertSame($this->primary->id, (int) $report->coach_id);
    }

    public function test_additional_coach_is_blocked_once_primary_coach_reported(): void
    {
        $this->teachSession([$this->additional]);

        $this->submitReport($this->primary)->assertSessionHasNoErrors();

        $this->submitReport($this->additional)
            ->assertSessionHasErrors([
                'report_date' => 'Report untuk pertemuan ini sudah dibuat oleh coach lain.',
            ]);

        $this->assertSame(1, Report::count());
    }

    public function test_primary_coach_is_blocked_once_additional_coach_reported(): void
    {
        $this->teachSession([$this->additional]);

        $this->submitReport($this->additional)->assertSessionHasNoErrors();

        $this->submitReport($this->primary)
            ->assertSessionHasErrors([
                'report_date' => 'Report untuk pertemuan ini sudah dibuat oleh coach lain.',
            ]);

        $this->assertSame(1, Report::count());
        $this->assertSame($this->additional->id, (int) Report::firstOrFail()->coach_id);
    }

    public function test_temporary_coach_reporting_first_blocks_every_other_coach(): void
    {
        $tempA = $this->makeUser(User::ROLE_COACH, 'coach.temp.a');
        $tempB = $this->makeUser(User::ROLE_COACH, 'coach.temp.b');

        $this->teachSession([$tempA, $tempB]);

        // Coach pendamping sementara membuat laporan lebih dulu — tanpa
        // assignment permanen ke kelas ini.
        $this->submitReport($tempA)->assertSessionHasNoErrors();
        $this->assertFalse(
            CoachClass::where('coach_id', $tempA->id)->where('class_id', $this->class->id)->exists(),
            'Membuat laporan tidak boleh menciptakan assignment permanen.'
        );

        foreach ([$this->primary, $tempB] as $other) {
            $this->submitReport($other)
                ->assertSessionHasErrors([
                    'report_date' => 'Report untuk pertemuan ini sudah dibuat oleh coach lain.',
                ]);
        }

        $this->assertSame(1, Report::count());
    }

    public function test_same_coach_cannot_submit_a_second_report_for_the_same_session(): void
    {
        $this->teachSession();

        $this->submitReport($this->primary)->assertSessionHasNoErrors();

        $this->submitReport($this->primary)
            ->assertSessionHasErrors([
                'report_date' => 'Anda sudah membuat laporan untuk pertemuan ini.',
            ]);

        $this->assertSame(1, Report::count());
    }

    /**
     * Penjaga balapan: dua submit hampir bersamaan sama-sama lolos validasi
     * aplikasi, jadi yang menentukan adalah indeks unik di database. Di sini
     * penulisan kedua dilakukan langsung ke model untuk membuktikan constraint
     * itu benar-benar ada — bukan hanya ada di controller.
     */
    public function test_database_index_rejects_a_second_report_for_the_same_session(): void
    {
        $session = $this->teachSession();
        $student = $this->student();

        $this->submitReport($this->primary)->assertSessionHasNoErrors();

        $this->expectException(QueryException::class);

        Report::create([
            'coach_id' => $this->additional->id,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'teaching_schedule_id' => $session->id,
            'report_date' => self::DATE,
            'lesson_material' => 'Materi kedua',
            'goals_materi' => 'Goals',
            'activity_report' => 'Aktivitas',
            'status' => 'submitted',
        ]);
    }

    public function test_one_session_never_ends_up_with_two_reports(): void
    {
        $session = $this->teachSession([$this->additional]);

        $this->submitReport($this->primary)->assertSessionHasNoErrors();
        $this->submitReport($this->additional)->assertSessionHasErrors('report_date');
        $this->submitReport($this->primary)->assertSessionHasErrors('report_date');

        $this->assertSame(1, Report::count());
        $this->assertSame(1, $session->refresh()->report()->count());
    }

    // ===== 2. Alur koreksi laporan yang ditolak =====

    public function test_rejected_report_is_corrected_without_creating_a_second_row(): void
    {
        $session = $this->teachSession();

        $this->submitReport($this->primary)->assertSessionHasNoErrors();

        $report = Report::firstOrFail();

        $this->actingAs($this->relation)
            ->patch(route('admin.reports.reject', $report), ['admin_notes' => 'Foto kurang jelas'])
            ->assertRedirect();

        $this->assertSame('rejected', $report->refresh()->status);

        // Coach memperbaiki laporan yang sama — bukan membuat laporan baru.
        $this->actingAs($this->primary)
            ->get(route('coach.reports.edit', $report))
            ->assertOk();

        $this->actingAs($this->primary)
            ->put(route('coach.reports.update', $report), [
                'report_date' => self::DATE,
                'lesson_material' => 'Materi revisi',
                'goals_materi' => 'Goals revisi',
                'activity_report' => 'Aktivitas revisi',
                'attendance' => $report->attendances->pluck('status', 'student_id')->all(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Report::count());

        $report->refresh();
        $this->assertSame('submitted', $report->status);
        $this->assertSame('Materi revisi', $report->lesson_material);
        $this->assertSame($session->id, (int) $report->teaching_schedule_id);
    }

    // ===== 3. Visibilitas coach pendamping =====

    public function test_additional_coach_sees_the_single_report_of_their_session(): void
    {
        $this->teachSession([$this->additional]);

        $this->submitReport($this->primary)->assertSessionHasNoErrors();

        $this->actingAs($this->additional)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Grade 4A')
            ->assertSee('Dibuat oleh')
            ->assertSee($this->primary->name);
    }

    public function test_coach_does_not_see_reports_from_unrelated_sessions(): void
    {
        $this->teachSession([$this->additional]);

        $this->submitReport($this->primary)->assertSessionHasNoErrors();

        $this->actingAs($this->outsider)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertDontSee('Grade 4A')
            ->assertDontSee('SD Syafana');
    }

    public function test_shared_coach_can_download_the_approved_report_of_their_session(): void
    {
        $this->teachSession([$this->additional]);

        $this->submitReport($this->primary)->assertSessionHasNoErrors();

        $report = Report::firstOrFail();
        $report->update(['status' => 'approved']);

        $this->actingAs($this->additional)
            ->get(route('coach.reports.download', $report))
            ->assertOk();

        $this->actingAs($this->outsider)
            ->get(route('coach.reports.download', $report))
            ->assertForbidden();
    }

    // ===== 4. Sesi nonaktif =====

    public function test_inactive_session_rejects_a_new_report(): void
    {
        $session = $this->teachSession();

        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));

        $this->submitReport($this->primary)
            ->assertSessionHasErrors([
                'report_date' => 'Sesi ini dinonaktifkan, jadi laporan baru tidak bisa dibuat untuk pertemuan ini.',
            ]);

        $this->assertSame(0, Report::count());
    }

    public function test_inactive_session_keeps_its_existing_report_and_history(): void
    {
        $session = $this->teachSession();

        $this->submitReport($this->primary)->assertSessionHasNoErrors();

        $report = Report::firstOrFail();

        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));

        // Sesi tetap ada, laporannya tidak dihapus dan tidak diduplikasi.
        $this->assertFalse($session->refresh()->is_active);
        $this->assertSame(1, Report::count());
        $this->assertSame($session->id, (int) $report->refresh()->teaching_schedule_id);

        // Dan tetap terlihat oleh pemiliknya sebagai riwayat.
        $this->actingAs($this->primary)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Grade 4A');
    }

    public function test_reactivated_session_accepts_a_report_again(): void
    {
        $session = $this->teachSession([$this->additional]);

        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));

        // Bagi coach pendamping — yang aksesnya HANYA lewat sesi ini — kelasnya
        // ikut hilang dari form pembuatan laporan selama sesinya nonaktif…
        $this->actingAs($this->additional)
            ->get(route('coach.reports.create'))
            ->assertOk()
            ->assertDontSee('Grade 4A');

        // …dan submit langsung ke endpoint tetap ditolak untuk semua coach.
        $this->submitReport($this->primary)->assertSessionHasErrors('report_date');
        $this->submitReport($this->additional)->assertSessionHasErrors('report_date');
        $this->assertSame(0, Report::count());

        // Setelah diaktifkan lagi, perilakunya kembali normal.
        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));
        $this->assertTrue($session->refresh()->is_active);

        $this->actingAs($this->additional)
            ->get(route('coach.reports.create'))
            ->assertOk()
            ->assertSee('Grade 4A');

        $this->submitReport($this->primary)->assertSessionHasNoErrors();
        $this->assertSame(1, Report::count());
    }

    // ===== 5. Reminder dihitung per sesi, bukan per coach =====

    public function test_reminder_counts_one_report_per_session_not_per_coach(): void
    {
        // Sesi diletakkan di masa lalu supaya masuk daftar tunggakan, dan
        // laporannya memakai tanggal sesi itu — persis seperti coach mengisi
        // form memakai tanggal pertemuan.
        $past = now()->subDay()->toDateString();
        $session = $this->teachSession([$this->additional], ['session_date' => $past]);

        $reminders = app(ReportReminderService::class);

        // Satu sesi bersama dua coach tetap SATU tunggakan, bukan dua.
        $missing = $reminders->missingSessions($this->relation);
        $this->assertSame(1, $missing->where('id', $session->id)->count());

        // Coach pendamping membuat laporan → sesi selesai untuk SEMUA coach
        // yang terlibat, walau coach utama belum membuat laporan sendiri.
        $this->submitReport($this->additional, ['report_date' => $past])->assertSessionHasNoErrors();

        $this->assertFalse(
            $reminders->missingSessions($this->relation)->contains('id', $session->id),
            'Satu laporan per sesi sudah cukup menandai sesi itu selesai.'
        );
    }

    public function test_reminder_still_waits_for_sessions_without_any_report(): void
    {
        $past = now()->subDay()->toDateString();
        $session = $this->teachSession([$this->additional], ['session_date' => $past]);

        $reminders = app(ReportReminderService::class);

        $this->assertTrue($reminders->missingSessions($this->relation)->contains('id', $session->id));
    }
}
