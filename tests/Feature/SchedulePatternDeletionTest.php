<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\ReportMedia;
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
 * Hapus POLA / JADWAL SEKOLAH (review meeting LRS 2026-10-01).
 *
 * Aturan final:
 * - Sesi hasil generate BUKAN riwayat historis. Pola yang dibuang membawa
 *   serta pertemuan hasil generate-nya yang belum pernah dipakai, supaya
 *   tidak ada sesi yatim yang masih tampil di jadwal.
 * - Bila SATU saja pertemuan dari pola itu sudah punya riwayat (laporan
 *   dan/atau absensi/media), penghapusan DITOLAK seluruhnya dengan pesan
 *   yang jelas. Tidak ada laporan/absensi yang dihapus demi merapikan
 *   konfigurasi.
 */
class SchedulePatternDeletionTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private SchoolClass $class;
    private Program $program;
    private User $coach;
    private User $relation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SD Pola']);
        $this->class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Grade 6A']);
        $this->program = Program::create(['name' => 'Robotics', 'code' => 'ROB', 'status' => 'active']);

        Student::create(['class_id' => $this->class->id, 'name' => 'Murid Satu']);
        Student::create(['class_id' => $this->class->id, 'name' => 'Murid Dua']);

        $this->coach = $this->makeUser(User::ROLE_COACH, 'coach.pola');
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->class->id]);

        $this->relation = $this->makeUser(User::ROLE_RELATION, 'relation.pola');
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

    /** Pola (metadata) + pertemuan hasil generate-nya. */
    private function makePatternWithSessions(string $startDate = '2026-08-10', int $meetingCount = 3): TeachingScheduleTemplate
    {
        $template = TeachingScheduleTemplate::create([
            'pattern_name'  => 'Pola Senin',
            'start_date'    => $startDate,
            'meeting_count' => $meetingCount,
            'day_of_week'   => 1,
            'school_id'     => $this->school->id,
            'class_id'      => $this->class->id,
            'program_id'    => $this->program->id,
            'coach_id'      => $this->coach->id,
            'start_time'    => '08:00',
            'end_time'      => '09:30',
            'jalan_minggu_ini' => true,
        ]);

        app(ScheduleTemplateService::class)->generate($template);

        return $template->refresh();
    }

    private function attachReport(TeachingSchedule $session, bool $withAttendance = false, bool $withMedia = false): Report
    {
        $report = Report::create([
            'coach_id'             => $this->coach->id,
            'school_id'            => $session->school_id,
            'class_id'             => $session->class_id,
            'teaching_schedule_id' => $session->id,
            'report_date'          => $session->session_date?->toDateString() ?? '2026-08-10',
            'lesson_material'      => 'Materi',
            'goals_materi'         => 'Goals',
            'activity_report'      => 'Kegiatan',
            'status'               => Report::STATUS_APPROVED,
        ]);

        if ($withAttendance) {
            foreach (Student::where('class_id', $session->class_id)->get() as $student) {
                ReportAttendance::create([
                    'report_id'  => $report->id,
                    'student_id' => $student->id,
                    'status'     => 'present',
                ]);
            }
        }

        if ($withMedia) {
            ReportMedia::create([
                'report_id'     => $report->id,
                'type'          => 'photo',
                'path'          => 'reports/bukti.jpg',
                'original_name' => 'bukti.jpg',
                'disk'          => 'local',
                'file_size'     => 1024,
            ]);
        }

        return $report;
    }

    private function deletePattern(string $startDate = '2026-08-10')
    {
        return $this->actingAs($this->relation)
            ->delete(route('admin.schedules.pattern.destroy'), [
                'day'        => 1,
                'school_id'  => $this->school->id,
                'start_date' => $startDate,
            ]);
    }

    // =====================================================================
    // 1–2. Pola + sesi tergenerate, lalu terhapus bersama
    // =====================================================================

    public function test_a_pattern_generates_sessions_and_is_deleted_with_them(): void
    {
        $template = $this->makePatternWithSessions();

        $this->assertSame(3, $template->sessions()->count());
        $this->assertSame(3, TeachingSchedule::count());

        $this->deletePattern()
            ->assertRedirect()
            ->assertSessionHas('success');

        // 5. Tidak ada sesi yatim yang tertinggal.
        $this->assertSame(0, TeachingScheduleTemplate::count());
        $this->assertSame(0, TeachingSchedule::count());
        $this->assertSame(0, TeachingSchedule::whereNotNull('template_id')->count());
    }

    public function test_sessions_never_used_are_removed_with_the_pattern(): void
    {
        $this->makePatternWithSessions('2026-09-07', 4);

        $this->deletePattern('2026-09-07')->assertSessionHas('success');

        $this->assertSame(0, TeachingSchedule::count());
    }

    // =====================================================================
    // 3–4. Ada riwayat → ditolak aman
    // =====================================================================

    public function test_deleting_a_pattern_with_a_report_is_refused_safely(): void
    {
        $template = $this->makePatternWithSessions();
        $session = $template->sessions()->where('meeting_number', 2)->firstOrFail();
        $report = $this->attachReport($session);

        $this->deletePattern()
            ->assertRedirect()
            ->assertSessionHas('error');

        // 6. Tidak ada laporan/sesi yang ikut hilang.
        $this->assertModelExists($template);
        $this->assertModelExists($session);
        $this->assertModelExists($report);
        $this->assertSame(3, TeachingSchedule::count());
        $this->assertSame(1, TeachingScheduleTemplate::count());
        $this->assertSame(2, $session->refresh()->meeting_number);
    }

    public function test_deleting_a_pattern_with_attendance_is_refused_safely(): void
    {
        $template = $this->makePatternWithSessions();
        $session = $template->sessions()->where('meeting_number', 1)->firstOrFail();
        $report = $this->attachReport($session, withAttendance: true, withMedia: true);

        $attendanceCount = ReportAttendance::where('report_id', $report->id)->count();
        $this->assertSame(2, $attendanceCount);

        $this->deletePattern()
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertModelExists($template);
        $this->assertModelExists($session);
        $this->assertSame($attendanceCount, ReportAttendance::where('report_id', $report->id)->count());
        $this->assertSame(1, ReportMedia::where('report_id', $report->id)->count());
    }

    public function test_refused_deletion_leaves_no_partial_change(): void
    {
        $template = $this->makePatternWithSessions();
        $this->attachReport($template->sessions()->where('meeting_number', 3)->firstOrFail());

        $this->deletePattern()->assertSessionHas('error');

        // Semua sesi masih menempel pada polanya — tidak ada yang dihapus
        // sebagian lalu menyisakan sesi tanpa template.
        $this->assertSame(
            3,
            TeachingSchedule::where('template_id', $template->id)->count()
        );
    }

    // =====================================================================
    // Guard yang sama berlaku untuk hapus SATU BARIS pola
    // =====================================================================

    public function test_deleting_a_single_pattern_row_with_history_is_refused(): void
    {
        $template = $this->makePatternWithSessions();
        $session = $template->sessions()->firstOrFail();
        $this->attachReport($session, withAttendance: true);

        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.templates.destroy', $template))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertModelExists($template);
        $this->assertSame(3, TeachingSchedule::count());
    }

    public function test_deleting_a_single_pattern_row_without_history_removes_its_sessions(): void
    {
        $template = $this->makePatternWithSessions();

        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.templates.destroy', $template))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertModelMissing($template);
        $this->assertSame(0, TeachingSchedule::count());
    }

    // =====================================================================
    // Sesi ber-riwayat juga tetap tidak bisa dihapus dari modul jadwal
    // =====================================================================

    public function test_session_with_a_legacy_report_is_treated_as_history(): void
    {
        $template = $this->makePatternWithSessions();
        $session = $template->sessions()->where('meeting_number', 1)->firstOrFail();

        // Laporan lama tanpa tautan sesi: kecocokan kelas + tanggal.
        Report::create([
            'coach_id'        => $this->coach->id,
            'school_id'       => $this->school->id,
            'class_id'        => $this->class->id,
            'report_date'     => $session->session_date->toDateString(),
            'lesson_material' => 'Materi lama',
            'goals_materi'    => 'Goals',
            'activity_report' => 'Kegiatan',
            'status'          => Report::STATUS_SUBMITTED,
        ]);

        $this->assertTrue($session->refresh()->hasHistory());

        $this->actingAs($this->relation)
            ->delete(route('admin.schedules.destroy', $session))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertModelExists($session);

        // Dan karena itu, pola induknya juga tidak bisa dihapus.
        $this->deletePattern()->assertSessionHas('error');
        $this->assertModelExists($template);
    }
}
