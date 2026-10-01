<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Progress "N/Target Pertemuan Terlaksana" (review meeting LRS 2026-10-01).
 *
 * Sumber kebenaran angkanya adalah BACKEND:
 * `SchoolClass::sessionProgressFor()` menghitung PERTEMUAN (sesi) yang sudah
 * punya laporan dengan status yang dianggap selesai oleh lifecycle LRS
 * (`Report::COMPLETED_STATUSES` = submitted + approved) — sama persis dengan
 * aturan yang dipakai reminder laporan.
 *
 * Yang dihitung adalah SESINYA, bukan laporannya, sehingga:
 * - sesi yang baru dibuat tetapi belum dilaporkan TIDAK menambah progress;
 * - satu sesi tidak pernah terhitung dua kali (reject → resubmit tetap +1);
 * - laporan lama tanpa tautan sesi tidak menambah hitungan untuk sesi yang
 *   sudah punya laporan sendiri.
 */
class MeetingProgressTest extends TestCase
{
    use RefreshDatabase;

    private School $school;
    private SchoolClass $class;
    private User $coach;
    private User $relation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SD Progress']);
        $this->class = SchoolClass::create([
            'school_id'       => $this->school->id,
            'name'            => 'Grade 5A',
            'target_meetings' => 20,
        ]);

        Student::create(['class_id' => $this->class->id, 'name' => 'Murid Satu']);

        $this->coach = $this->makeUser(User::ROLE_COACH, 'coach.progress');
        $this->relation = $this->makeUser(User::ROLE_RELATION, 'relation.progress');
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

    private function makeSession(string $date, int $meetingNumber): TeachingSchedule
    {
        return TeachingSchedule::create([
            'meeting_number' => $meetingNumber,
            'school_id'      => $this->school->id,
            'class_id'       => $this->class->id,
            'coach_id'       => $this->coach->id,
            'session_date'   => $date,
            'start_time'     => '08:00',
            'end_time'       => '09:30',
            'is_active'      => true,
            'status'         => TeachingSchedule::STATUS_SCHEDULED,
        ]);
    }

    /**
     * Laporan untuk satu sesi, lewat model — status yang dipakai sama dengan
     * yang ditulis alur submission coach (`submitted`).
     */
    private function submitReport(TeachingSchedule $session, string $status = Report::STATUS_SUBMITTED): Report
    {
        $report = Report::create([
            'coach_id'             => $this->coach->id,
            'school_id'            => $session->school_id,
            'class_id'             => $session->class_id,
            'teaching_schedule_id' => $session->id,
            'report_date'          => $session->session_date->toDateString(),
            'lesson_material'      => 'Materi',
            'goals_materi'         => 'Goals',
            'activity_report'      => 'Kegiatan',
            'status'               => $status,
        ]);

        ReportAttendance::create([
            'report_id'  => $report->id,
            'student_id' => Student::where('class_id', $session->class_id)->firstOrFail()->id,
            'status'     => 'present',
        ]);

        return $report;
    }

    /** @return array{target: ?int, terlaksana: int, scheduled: int, unscheduled: int, completed: int} */
    private function progress(?int $classId = null): array
    {
        return SchoolClass::sessionProgressFor([$classId ?? $this->class->id])
            ->get($classId ?? $this->class->id);
    }

    // =====================================================================
    // 1. Sebelum ada laporan: 0/20
    // =====================================================================

    public function test_progress_is_zero_before_any_report_is_submitted(): void
    {
        $this->makeSession('2026-08-10', 1);
        $this->makeSession('2026-08-17', 2);

        $progress = $this->progress();

        $this->assertSame(20, $progress['target']);
        $this->assertSame(0, $progress['terlaksana'], 'Sesi yang tergenerate saja tidak dihitung terlaksana.');
        $this->assertSame(2, $progress['scheduled']);

        // Yang tampil di frontend juga 0/20, bukan 2/20.
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi']))
            ->assertOk()
            ->assertSee('0/20')
            ->assertSee('Pertemuan Terlaksana');
    }

    // =====================================================================
    // 2–3. Tiap laporan menambah tepat satu pertemuan
    // =====================================================================

    public function test_report_on_the_first_meeting_makes_it_one_of_twenty(): void
    {
        $session = $this->makeSession('2026-08-10', 1);
        $this->makeSession('2026-08-17', 2);

        $this->submitReport($session);

        $this->assertSame(1, $this->progress()['terlaksana']);

        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi']))
            ->assertOk()
            ->assertSee('1/20');
    }

    public function test_report_on_the_second_meeting_makes_it_two_of_twenty(): void
    {
        $first = $this->makeSession('2026-08-10', 1);
        $second = $this->makeSession('2026-08-17', 2);

        $this->submitReport($first);
        $this->assertSame(1, $this->progress()['terlaksana']);

        $this->submitReport($second);
        $this->assertSame(2, $this->progress()['terlaksana']);

        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi']))
            ->assertOk()
            ->assertSee('2/20');
    }

    // =====================================================================
    // 4. Reject mengikuti aturan lifecycle yang ada, tanpa double-count
    // =====================================================================

    public function test_rejected_report_follows_the_existing_lifecycle_rule(): void
    {
        $session = $this->makeSession('2026-08-10', 1);
        $report = $this->submitReport($session);

        $this->assertSame(1, $this->progress()['terlaksana']);

        // Aturan yang sudah ada (ReportReminderService): `rejected` BUKAN status
        // selesai — pertemuan itu kembali menunggu perbaikan coach.
        $report->update(['status' => Report::STATUS_REJECTED, 'admin_notes' => 'Perbaiki materi.']);

        $progress = $this->progress();
        $this->assertSame(0, $progress['terlaksana']);
        $this->assertSame(1, $progress['scheduled'], 'Sesinya tetap ada — reject tidak menghapus sesi.');

        // Tidak ada hitungan ganda: satu sesi tetap satu baris di tabel sesi.
        $this->assertSame(1, TeachingSchedule::where('class_id', $this->class->id)->count());
    }

    // =====================================================================
    // 5. Resubmit laporan yang sama tidak menjadi +2
    // =====================================================================

    public function test_resubmitting_the_same_report_does_not_double_count(): void
    {
        $session = $this->makeSession('2026-08-10', 1);
        $report = $this->submitReport($session);

        $report->update(['status' => Report::STATUS_REJECTED, 'admin_notes' => 'Perbaiki.']);
        $this->assertSame(0, $this->progress()['terlaksana']);

        // Coach memperbaiki lalu mengirim ulang laporan yang SAMA.
        $report->update(['status' => Report::STATUS_SUBMITTED, 'admin_notes' => null]);

        $this->assertSame(1, $this->progress()['terlaksana']);
        $this->assertSame(1, Report::where('teaching_schedule_id', $session->id)->count());
    }

    // =====================================================================
    // 6. Laporan approved tetap terhitung satu
    // =====================================================================

    public function test_approved_report_counts_exactly_once(): void
    {
        $first = $this->makeSession('2026-08-10', 1);
        $second = $this->makeSession('2026-08-17', 2);

        $approved = $this->submitReport($first);
        $approved->update(['status' => Report::STATUS_APPROVED, 'approved_at' => now()]);
        $this->submitReport($second, Report::STATUS_APPROVED);

        $this->assertSame(2, $this->progress()['terlaksana']);
    }

    // =====================================================================
    // 7. Satu sesi dihitung maksimum sekali
    // =====================================================================

    public function test_a_session_is_counted_at_most_once_even_with_a_legacy_report(): void
    {
        $session = $this->makeSession('2026-08-10', 1);
        $this->submitReport($session);

        // Laporan lama (sebelum migrasi teaching_schedule_id) dengan kelas dan
        // tanggal yang sama. Kalau progress dihitung dari sisi LAPORAN, sesi ini
        // akan terhitung dua kali; dihitung dari sisi SESI, tetap satu.
        Report::create([
            'coach_id'        => $this->coach->id,
            'school_id'       => $this->school->id,
            'class_id'        => $this->class->id,
            'report_date'     => '2026-08-10',
            'lesson_material' => 'Materi lama',
            'goals_materi'    => 'Goals',
            'activity_report' => 'Kegiatan',
            'status'          => Report::STATUS_APPROVED,
        ]);

        $this->assertSame(1, $this->progress()['terlaksana']);
    }

    public function test_cancelled_session_never_adds_progress(): void
    {
        $session = $this->makeSession('2026-08-10', 1);
        $this->submitReport($session);

        $this->assertSame(1, $this->progress()['terlaksana']);

        $session->update(['status' => TeachingSchedule::STATUS_CANCELLED]);

        $this->assertSame(0, $this->progress()['terlaksana']);
    }

    public function test_progress_is_computed_per_class(): void
    {
        $otherClass = SchoolClass::create([
            'school_id'       => $this->school->id,
            'name'            => 'Grade 5B',
            'target_meetings' => 20,
        ]);

        $mine = $this->makeSession('2026-08-10', 1);
        $this->submitReport($mine);

        TeachingSchedule::create([
            'meeting_number' => 1,
            'school_id'      => $this->school->id,
            'class_id'       => $otherClass->id,
            'coach_id'       => $this->coach->id,
            'session_date'   => '2026-08-10',
            'start_time'     => '08:00',
            'end_time'       => '09:30',
            'is_active'      => true,
            'status'         => TeachingSchedule::STATUS_SCHEDULED,
        ]);

        // Laporan legacy di kelas lain tidak boleh menambah progress kelas ini,
        // begitu pula sebaliknya.
        Report::create([
            'coach_id'        => $this->coach->id,
            'school_id'       => $this->school->id,
            'class_id'        => $otherClass->id,
            'report_date'     => '2026-08-10',
            'lesson_material' => 'Materi lama',
            'goals_materi'    => 'Goals',
            'activity_report' => 'Kegiatan',
            'status'          => Report::STATUS_APPROVED,
        ]);

        $progress = SchoolClass::sessionProgressFor([$this->class->id, $otherClass->id]);

        $this->assertSame(1, $progress->get($this->class->id)['terlaksana']);
        $this->assertSame(1, $progress->get($otherClass->id)['terlaksana']);
    }
}
