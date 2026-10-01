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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Meeting 2026-09-27 — status AKTIF / NONAKTIF per SESI mengajar.
 *
 * Aturan bisnis:
 * - Setiap sesi hasil generate default AKTIF.
 * - Sesi bisa dinonaktifkan (mis. libur/ujian sekolah) tanpa dihapus:
 *   meeting_number dan session_date tetap, riwayat laporan tetap ada.
 * - Sesi nonaktif tidak dihitung sebagai sesi mengajar aktif: tidak muncul
 *   sebagai tuntutan laporan dan tidak memicu reminder.
 * - Sesi bisa diaktifkan kembali; perilakunya kembali seperti semula.
 */
class ScheduleSessionActiveTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private Program $program;
    private User $coach;
    private User $relation;
    private User $superadmin;
    private User $picA;

    /** Senin acuan; 20 pertemuan mingguan berakhir 2026-05-18. */
    private const START = '2026-01-05';

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'SD Aktif A']);
        $this->schoolB = School::create(['name' => 'SD Aktif B']);
        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 1A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 1B']);

        $this->program = Program::create(['name' => 'Coding', 'code' => 'COD', 'status' => 'active']);

        $this->coach = $this->makeUser(User::ROLE_COACH, 'coach.aktif');
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->classA->id]);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->classB->id]);

        $this->relation = $this->makeUser(User::ROLE_RELATION, 'relation');
        $this->superadmin = $this->makeUser(User::ROLE_SUPERADMIN, 'superadmin');

        $this->picA = $this->makeUser(User::ROLE_SCHOOL_PIC, 'pic.a');
        $this->picA->schools()->attach($this->schoolA->id);
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
     * Pola Senin 20 pertemuan untuk satu kelas, lewat jalur resmi (form bulk).
     */
    private function generatePattern(int $schoolId, int $classId, int $coachId, int $meetings = 20): void
    {
        $this->actingAs($this->relation)
            ->post(route('admin.schedules.bulk.store'), [
                'days' => [
                    1 => [
                        'blocks' => [[
                            'school_id' => $schoolId,
                            'start_date' => self::START,
                            'meeting_count' => $meetings,
                            'rows' => [[
                                'class_id' => $classId,
                                'program_id' => $this->program->id,
                                'coach_id' => $coachId,
                                'start_time' => '08:00',
                                'end_time' => '09:30',
                                'student_count' => 10,
                            ]],
                        ]],
                    ],
                ],
            ])
            ->assertSessionHasNoErrors();
    }

    private function sessionAt(int $meetingNumber = 1, ?int $classId = null): TeachingSchedule
    {
        return TeachingSchedule::where('class_id', $classId ?? $this->classA->id)
            ->where('meeting_number', $meetingNumber)
            ->firstOrFail();
    }

    // ===== 1. Toggle tidak menghapus sesi =====

    public function test_toggle_keeps_all_twenty_generated_sessions(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id);

        $this->assertSame(20, TeachingSchedule::where('class_id', $this->classA->id)->count());

        $target = $this->sessionAt(7);
        $originalDate = $target->session_date->toDateString();

        $this->actingAs($this->relation)
            ->patch(route('admin.schedules.toggle-active', $target))
            ->assertRedirect();

        // Jumlah sesi tidak berkurang, nomor pertemuan dan tanggal tetap.
        $this->assertSame(20, TeachingSchedule::where('class_id', $this->classA->id)->count());

        $target->refresh();
        $this->assertFalse($target->is_active);
        $this->assertSame(7, (int) $target->meeting_number);
        $this->assertSame($originalDate, $target->session_date->toDateString());
    }

    public function test_generated_sessions_default_to_active(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 3);

        $this->assertSame(
            3,
            TeachingSchedule::where('class_id', $this->classA->id)->where('is_active', true)->count()
        );
    }

    // ===== 2. Aktif = tuntutan laporan, nonaktif = bebas tuntutan =====

    public function test_inactive_session_is_excluded_from_missing_report_reminder(): void
    {
        // Satu sesi saja supaya daftar tunggakan benar-benar bersih saat
        // sesi itu dinonaktifkan.
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 1);

        $session = $this->sessionAt(1);
        $session->update(['session_date' => now()->subDay()->toDateString()]);

        $reminders = app(ReportReminderService::class);

        // Aktif + belum dilaporkan → sesi muncul sebagai tunggakan.
        $this->assertTrue(
            $reminders->missingSessions($this->relation)->contains('id', $session->id),
            'Sesi aktif yang belum dilaporkan harus terhitung menunggak.'
        );

        // Dinonaktifkan → keluar dari daftar tunggakan.
        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));

        $this->assertFalse(
            $reminders->missingSessions($this->relation)->contains('id', $session->id),
            'Sesi nonaktif tidak boleh menuntut laporan.'
        );
        $this->assertSame(0, $reminders->overdueCoaches($this->relation)->count());
    }

    public function test_reactivating_session_restores_report_requirement(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 3);

        $session = $this->sessionAt(2);
        $session->update(['session_date' => now()->subDay()->toDateString()]);

        $reminders = app(ReportReminderService::class);

        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));
        $this->assertFalse($reminders->missingSessions($this->relation)->contains('id', $session->id));

        // Aktifkan kembali → perilaku kembali normal.
        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));
        $session->refresh();

        $this->assertTrue($session->is_active);
        $this->assertTrue(
            $reminders->missingSessions($this->relation)->contains('id', $session->id),
            'Setelah diaktifkan lagi, sesi kembali menuntut laporan.'
        );
    }

    public function test_reported_session_is_not_counted_regardless_of_activation(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 3);

        $session = $this->sessionAt(1);
        $session->update(['session_date' => now()->subDay()->toDateString()]);

        Report::create([
            'coach_id' => $this->coach->id,
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'report_date' => $session->session_date->toDateString(),
            'lesson_material' => 'Materi',
            'goals_materi' => 'Goals',
            'activity_report' => 'Aktivitas',
            'status' => 'submitted',
        ]);

        $reminders = app(ReportReminderService::class);

        $this->assertFalse($reminders->missingSessions($this->relation)->contains('id', $session->id));
    }

    // ===== 3. Tampilan & filter =====

    public function test_inactive_session_is_flagged_and_filterable_in_the_list(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 3);

        $inactive = $this->sessionAt(3);
        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $inactive));

        // Tanggal sesi nonaktif dipakai sebagai penanda baris — string kelas
        // CSS `sched-row-inactive` selalu ikut terkirim di dalam <style>, jadi
        // tidak bisa dipakai untuk membuktikan ada/tidaknya baris nonaktif.
        $inactiveDate = $inactive->session_date->translatedFormat('d M Y');
        $activeDate = $this->sessionAt(1)->session_date->translatedFormat('d M Y');

        $response = $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi']))
            ->assertOk();

        // Badge status terlihat, dan baris nonaktif ditandai kelas khusus.
        $response->assertSee('Nonaktif');
        $response->assertSee('Aktif');
        $response->assertSee('sched-row-inactive', false);
        $response->assertSee($inactiveDate);

        // Filter status menyaring sesuai pilihan.
        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi', 'status' => 'nonaktif']))
            ->assertOk()
            ->assertSee($inactiveDate)
            ->assertDontSee($activeDate);

        $this->actingAs($this->relation)
            ->get(route('admin.schedules.index', ['view' => 'sesi', 'status' => 'aktif']))
            ->assertOk()
            ->assertSee($activeDate)
            ->assertDontSee($inactiveDate);
    }

    public function test_pattern_detail_lists_status_and_toggle_for_each_session(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 3);

        $this->actingAs($this->relation)
            ->get(route('admin.schedules.pattern.show', [
                'day' => 1,
                'school' => $this->schoolA->id,
                'start' => self::START,
            ]))
            ->assertOk()
            ->assertSee('Status')
            ->assertSee('Nonaktifkan')
            ->assertSee(route('admin.schedules.toggle-active', $this->sessionAt(1)), false);
    }

    // ===== 4. Otorisasi toggle =====

    public function test_coach_cannot_toggle_session_status(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 2);

        $session = $this->sessionAt(1);

        $this->actingAs($this->coach)
            ->patch(route('admin.schedules.toggle-active', $session))
            ->assertForbidden();

        $this->assertTrue($session->refresh()->is_active);
    }

    public function test_pic_cannot_toggle_session_of_another_school(): void
    {
        $this->generatePattern($this->schoolB->id, $this->classB->id, $this->coach->id, 2);

        $foreign = TeachingSchedule::where('class_id', $this->classB->id)->firstOrFail();

        $this->actingAs($this->picA)
            ->patch(route('admin.schedules.toggle-active', $foreign))
            ->assertForbidden();

        $this->assertTrue($foreign->refresh()->is_active);
    }

    public function test_pic_can_toggle_session_within_own_school(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 2);

        $session = $this->sessionAt(1);

        $this->actingAs($this->picA)
            ->patch(route('admin.schedules.toggle-active', $session))
            ->assertRedirect();

        $this->assertFalse($session->refresh()->is_active);
    }

    // ===== 5. Riwayat tetap utuh =====

    public function test_history_survives_deactivation_and_reactivation(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 3);

        $session = $this->sessionAt(1);

        $report = Report::create([
            'coach_id' => $this->coach->id,
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'report_date' => $session->session_date->toDateString(),
            'lesson_material' => 'Materi',
            'goals_materi' => 'Goals',
            'activity_report' => 'Aktivitas',
            'status' => 'approved',
        ]);

        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));
        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));

        // Sesi, nomor pertemuan, dan laporan historis tetap ada setelah
        // nonaktif → aktif kembali.
        $this->assertSame(3, TeachingSchedule::where('class_id', $this->classA->id)->count());
        $this->assertSame(1, (int) $this->sessionAt(1)->meeting_number);
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'approved']);
    }

    /**
     * Aturan final 2026-09-28: sesi nonaktif berarti sesi itu TIDAK
     * operasional — laporan BARU untuknya ditolak. Yang dilindungi adalah
     * riwayat: laporan yang sudah ada sebelumnya tetap tersimpan dan tetap
     * bisa dikoreksi coach-nya lewat alur resubmit.
     */
    public function test_deactivated_session_rejects_a_new_report(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 2);

        $session = $this->sessionAt(1);
        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));

        $student = Student::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'name' => 'Murid Satu',
        ]);

        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), [
                'class_id' => $this->classA->id,
                'report_date' => $session->session_date->toDateString(),
                'lesson_material' => 'Materi',
                'goals_materi' => 'Goals',
                'activity_report' => 'Aktivitas',
                'attendance' => [$student->id => 'present'],
            ])
            ->assertSessionHasErrors([
                'report_date' => 'Sesi ini dinonaktifkan, jadi laporan baru tidak bisa dibuat untuk pertemuan ini.',
            ]);

        $this->assertSame(0, Report::count());
    }

    /**
     * Sesi nonaktif dengan laporan yang sudah ada: laporannya tetap ada,
     * tetap milik coach-nya, dan tetap bisa dikoreksi bila ditolak reviewer.
     */
    public function test_deactivated_session_preserves_its_existing_report(): void
    {
        $this->generatePattern($this->schoolA->id, $this->classA->id, $this->coach->id, 2);

        $session = $this->sessionAt(1);

        $report = Report::create([
            'coach_id' => $this->coach->id,
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'teaching_schedule_id' => $session->id,
            'report_date' => $session->session_date->toDateString(),
            'lesson_material' => 'Materi',
            'goals_materi' => 'Goals',
            'activity_report' => 'Aktivitas',
            'status' => 'rejected',
            'admin_notes' => 'Perbaiki foto',
        ]);

        $this->actingAs($this->relation)->patch(route('admin.schedules.toggle-active', $session));

        $this->assertFalse($session->refresh()->is_active);
        $this->assertSame(1, Report::count());
        $this->assertSame('rejected', $report->refresh()->status);

        // Menonaktifkan sesi tidak mencabut hak koreksi atas laporan yang sudah ada.
        $student = Student::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'name' => 'Murid Satu',
        ]);

        $this->actingAs($this->coach)
            ->put(route('coach.reports.update', $report), [
                'report_date' => $session->session_date->toDateString(),
                'lesson_material' => 'Materi revisi',
                'goals_materi' => 'Goals',
                'activity_report' => 'Aktivitas',
                'attendance' => [$student->id => 'present'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('submitted', $report->refresh()->status);
        $this->assertSame(1, Report::count());
    }
}
