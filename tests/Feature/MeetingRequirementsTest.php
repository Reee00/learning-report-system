<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\TeachingSchedule;
use App\Models\User;
use App\Notifications\ReportReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Focused tests for the 2026-09 meeting requirements:
 *
 * A. Teacher School hanya melihat laporan approved (filter status dipaksa).
 * B. Relation dashboard operasional.
 * C. PIC dashboard ter-scope ke sekolah plot-nya.
 * D. Reminder laporan — Relation global, PIC hanya coach sekolahnya.
 * E. Jadwal mengajar — visibility per role + scope.
 * F. Form laporan Goals Materi + Activity Report.
 * G. Media absensi pada arsitektur media privat.
 */
class MeetingRequirementsTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private User $coach;
    private User $coachB;
    private User $relation;
    private User $picA;
    private User $teacherB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('report_media');

        $this->schoolA = School::create(['name' => 'SD Meeting A']);
        $this->schoolB = School::create(['name' => 'SD Meeting B']);
        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Grade 1A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Grade 1B']);

        Student::create(['class_id' => $this->classA->id, 'name' => 'Siswa A1']);

        $this->coach = User::create([
            'name' => 'Coach Meeting', 'email' => 'coach.meeting@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->classA->id]);

        $this->coachB = User::create([
            'name' => 'Coach B', 'email' => 'coach.b@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $this->coachB->id, 'class_id' => $this->classB->id]);

        $this->relation = User::create([
            'name' => 'Relation Meeting', 'email' => 'relation.meeting@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_RELATION,
        ]);

        $this->picA = User::create([
            'name' => 'PIC A', 'email' => 'pic.a@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_SCHOOL_PIC,
        ]);
        $this->picA->schools()->attach($this->schoolA->id);

        $this->teacherB = User::create([
            'name' => 'Teacher B', 'email' => 'teacher.b@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_TEACHER_SCHOOL,
            'school_id' => $this->schoolB->id,
        ]);
        $this->teacherB->schools()->attach($this->schoolB->id);
    }

    private function makeReport(School $school, SchoolClass $class, User $coach, string $status): Report
    {
        return Report::create([
            'coach_id' => $coach->id,
            'school_id' => $school->id,
            'class_id' => $class->id,
            'report_date' => now()->toDateString(),
            'lesson_material' => 'Materi',
            'goals_materi' => 'Goals sesi',
            'activity_report' => 'Ringkasan aktivitas',
            'status' => $status,
        ]);
    }

    // ===== A. Teacher School =====

    public function test_teacher_school_only_sees_approved_reports_even_with_status_filter(): void
    {
        $approved = $this->makeReport($this->schoolB, $this->classB, $this->coachB, 'approved');
        $this->makeReport($this->schoolB, $this->classB, $this->coachB, 'submitted');
        $this->makeReport($this->schoolB, $this->classB, $this->coachB, 'rejected');

        // Filter status apa pun dipaksa ke approved oleh controller.
        $response = $this->actingAs($this->teacherB)
            ->get(route('admin.reports.index', ['status' => 'rejected']))
            ->assertOk();

        $response->assertSee((string) $approved->id);
        $response->assertDontSee('Menunggu Review');
        $response->assertDontSee('Perlu Diperbaiki');
    }

    public function test_teacher_school_cannot_open_non_approved_report_detail(): void
    {
        $submitted = $this->makeReport($this->schoolB, $this->classB, $this->coachB, 'submitted');
        $approved = $this->makeReport($this->schoolB, $this->classB, $this->coachB, 'approved');

        // Laporan belum approved pada sekolah sendiri tetap ditolak (req. A).
        $this->actingAs($this->teacherB)
            ->get(route('admin.reports.show', $submitted))
            ->assertForbidden();

        $this->actingAs($this->teacherB)
            ->get(route('admin.reports.show', $approved))
            ->assertOk();
    }

    // ===== B. Relation dashboard =====

    public function test_relation_lands_on_and_reaches_operational_dashboard(): void
    {
        $this->actingAs($this->relation)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Relation Dashboard');
    }

    // ===== C. PIC dashboard scope =====

    public function test_pic_dashboard_never_exposes_other_schools(): void
    {
        $reportA = $this->makeReport($this->schoolA, $this->classA, $this->coach, 'approved');
        $reportB = $this->makeReport($this->schoolB, $this->classB, $this->coachB, 'approved');

        $response = $this->actingAs($this->picA)
            ->get(route('pic.dashboard'))
            ->assertOk();

        $response->assertSee($reportA->report_date->format('d M Y'));
        $response->assertDontSee($this->schoolB->name);
        $response->assertDontSee($this->coachB->name);

        // Detail laporan sekolah lain ditolak.
        $this->actingAs($this->picA)
            ->get(route('pic.reports.show', $reportB))
            ->assertForbidden();
    }

    // ===== D. Reminder notifications =====

    public function test_relation_reminds_all_overdue_coaches_with_missing_count(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->relation)
            ->post(route('admin.reports.remind'))
            ->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $this->coach->id,
        ]);
        $this->assertSame(1, $this->coach->notifications()->count());
        $this->assertSame(1, $this->coach->notifications->first()->data['missing_count']);
    }

    public function test_completed_session_is_not_reminded(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            // Tanggal sama dengan report_date agar sesi dianggap terlaporkan.
            'session_date' => now()->toDateString(),
        ]);
        $this->makeReport($this->schoolA, $this->classA, $this->coach, 'submitted');

        $this->actingAs($this->relation)
            ->post(route('admin.reports.remind'))
            ->assertRedirect();

        $this->assertSame(0, $this->coach->notifications()->count());
    }

    public function test_pic_cannot_remind_cross_school_coach(): void
    {
        // Coach B hanya mengajar di sekolah B (di luar scope PIC A).
        TeachingSchedule::create([
            'school_id' => $this->schoolB->id,
            'class_id' => $this->classB->id,
            'coach_id' => $this->coachB->id,
            'session_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->picA)
            ->post(route('pic.remind', ['coach_id' => $this->coachB->id]))
            ->assertRedirect();

        $this->assertSame(0, $this->coachB->notifications()->count());
    }

    public function test_pic_can_remind_own_school_coach(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->picA)
            ->post(route('pic.remind', ['coach_id' => $this->coach->id, 'message' => 'Tolong segera']))
            ->assertRedirect();

        $this->assertSame(1, $this->coach->notifications()->count());
        $this->assertSame('Tolong segera', $this->coach->notifications->first()->data['message']);
    }

    public function test_coach_can_mark_reminder_as_read(): void
    {
        $this->coach->notify(new ReportReminderNotification(
            senderName: 'Relation', senderRole: 'Relation', missingCount: 2, message: null,
        ));

        $notification = $this->coach->unreadNotifications->first();
        $this->assertNotNull($notification);

        $this->actingAs($this->coach)
            ->post(route('coach.notifications.read', $notification->id))
            ->assertRedirect();

        $this->assertSame(0, $this->coach->refresh()->unreadNotifications->count());
    }

    public function test_repeated_reminder_is_deduplicated_while_unread(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->relation)->post(route('admin.reports.remind'));
        $this->actingAs($this->relation)->post(route('admin.reports.remind'));

        // Coach masih punya reminder unread — pengiriman kedua ditekan.
        $this->assertSame(1, $this->coach->notifications()->count());
    }

    public function test_reminder_allowed_again_after_read(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->relation)->post(route('admin.reports.remind'));
        $this->assertSame(1, $this->coach->notifications()->count());

        $notification = $this->coach->notifications()->first();
        $this->actingAs($this->coach)
            ->post(route('coach.notifications.read', $notification->id));

        // Setelah dibaca, reminder baru boleh dibuat lagi.
        $this->actingAs($this->relation)->post(route('admin.reports.remind'));
        $this->assertSame(2, $this->coach->notifications()->count());
    }

    public function test_reminder_dedupe_is_per_coach_and_cross_sender(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id,
            'session_date' => now()->subDay()->toDateString(),
        ]);
        TeachingSchedule::create([
            'school_id' => $this->schoolB->id,
            'class_id' => $this->classB->id,
            'coach_id' => $this->coachB->id,
            'session_date' => now()->subDay()->toDateString(),
        ]);

        // Relation mengingatkan semua: masing-masing coach 1 notifikasi.
        $this->actingAs($this->relation)->post(route('admin.reports.remind'));
        $this->assertSame(1, $this->coach->notifications()->count());
        $this->assertSame(1, $this->coachB->notifications()->count());

        // PIC A mengingatkan coach-nya lagi saat reminder Relation masih
        // unread — ditekan (dedupe lintas pengirim, per coach).
        $this->actingAs($this->picA)
            ->post(route('pic.remind', ['coach_id' => $this->coach->id, 'message' => 'Segera']));
        $this->assertSame(1, $this->coach->notifications()->count());

        // Coach lain tidak terpengaruh.
        $this->assertSame(1, $this->coachB->notifications()->count());
    }

    public function test_remind_without_schedule_data_shows_import_hint(): void
    {
        // Tidak ada baris teaching_schedules: tidak ada coach menunggak.
        $this->actingAs($this->relation)
            ->post(route('admin.reports.remind'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString(
            'belum ada data jadwal mengajar',
            session('error'),
        );

        // Tidak ada notifikasi yang dibuat.
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_pic_remind_without_schedule_data_shows_import_hint(): void
    {
        $this->actingAs($this->picA)
            ->post(route('pic.remind', ['coach_id' => $this->coach->id]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString(
            'belum ada data jadwal mengajar',
            session('error'),
        );
        $this->assertSame(0, $this->coach->notifications()->count());
    }

    // ===== E. Teaching schedule =====

    public function test_coach_sees_only_own_schedule(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolA->id, 'class_id' => $this->classA->id,
            'coach_id' => $this->coach->id, 'session_date' => now()->toDateString(),
        ]);
        TeachingSchedule::create([
            'school_id' => $this->schoolB->id, 'class_id' => $this->classB->id,
            'coach_id' => $this->coachB->id, 'session_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->coach)
            ->get(route('admin.schedules.index'))
            ->assertOk();

        $response->assertSee($this->coach->name);
        $response->assertDontSee($this->coachB->name);
    }

    public function test_pic_schedule_is_scoped_to_plotted_schools(): void
    {
        TeachingSchedule::create([
            'school_id' => $this->schoolB->id, 'class_id' => $this->classB->id,
            'coach_id' => $this->coachB->id, 'session_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->picA)
            ->get(route('admin.schedules.index'))
            ->assertOk();

        $response->assertDontSee($this->schoolB->name);
        $response->assertDontSee($this->coachB->name);
    }

    public function test_coach_cannot_manage_schedules(): void
    {
        $this->actingAs($this->coach)
            ->get(route('admin.schedules.template'))
            ->assertForbidden();
    }

    public function test_schedule_import_skips_invalid_time_gracefully(): void
    {
        $csv = implode("\n", [
            'tanggal,nama_sekolah,nama_kelas,email_coach,jam_mulai,jam_selesai,topik',
            '2026-09-15,SD Meeting A,Grade 1A,coach.meeting@test.test,08:00,09:30,Prompting AI',
            '2026-09-16,SD Meeting A,Grade 1A,coach.meeting@test.test,pagi,siang,Topik Invalid',
        ]);

        $this->actingAs($this->relation)
            ->post(route('admin.schedules.import'), [
                'file' => UploadedFile::fake()->createWithContent('jadwal.csv', $csv),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Baris valid masuk, baris jam tidak valid dilewati tanpa exception.
        $this->assertSame(1, TeachingSchedule::count());
        $this->assertSame('08:00', TeachingSchedule::first()->start_time->format('H:i'));
    }

    // ===== F. Goals Materi + Activity Report =====

    public function test_report_requires_goals_and_activity_fields(): void
    {
        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), [
                'class_id' => $this->classA->id,
                'report_date' => now()->toDateString(),
                'lesson_material' => 'Materi',
                // goals_materi & activity_report hilang
                'attendance' => [],
            ])
            ->assertSessionHasErrors(['goals_materi', 'activity_report']);
    }

    public function test_submitted_report_stores_goals_and_activity(): void
    {
        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), [
                'class_id' => $this->classA->id,
                'report_date' => now()->toDateString(),
                'lesson_material' => 'Materi',
                'goals_materi' => "Goals:\n- Memahami konsep.",
                'activity_report' => "Progress:\n- Murid praktik.",
                'attendance' => [$this->classA->students()->first()->id => 'present'],
            ])
            ->assertRedirectToRoute('coach.reports.index');

        $report = Report::where('class_id', $this->classA->id)->firstOrFail();
        $this->assertSame("Goals:\n- Memahami konsep.", $report->goals_materi);
        $this->assertSame("Progress:\n- Murid praktik.", $report->activity_report);
    }

    public function test_review_page_and_download_render_new_fields(): void
    {
        $report = Report::create([
            'coach_id' => $this->coach->id,
            'school_id' => $this->schoolA->id,
            'class_id' => $this->classA->id,
            'report_date' => now()->toDateString(),
            'lesson_material' => 'Materi',
            'goals_materi' => "Goals:\n- Memahami konsep.",
            'activity_report' => "Progress:\n- Murid praktik.",
            'status' => 'submitted',
        ]);

        $this->actingAs($this->relation)
            ->get(route('admin.reports.show', $report))
            ->assertOk()
            ->assertSee('Goals Materi')
            ->assertSee('Activity Report')
            ->assertSee('Memahami konsep', false);

        $report->update(['status' => 'approved']);

        $this->actingAs($this->relation)
            ->get(route('admin.reports.download', $report))
            ->assertOk()
            ->assertSee('Goals Materi')
            ->assertSee('Activity Report');
    }

    // ===== G. Attendance media =====

    public function test_attendance_media_upload_stored_as_private_media(): void
    {
        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), [
                'class_id' => $this->classA->id,
                'report_date' => now()->toDateString(),
                'lesson_material' => 'Materi',
                'goals_materi' => 'Goals',
                'activity_report' => 'Aktivitas',
                'attendance' => [$this->classA->students()->first()->id => 'present'],
                'attendance_media' => [UploadedFile::fake()->image('bukti-absensi.jpg', 600, 400)],
            ])
            ->assertRedirectToRoute('coach.reports.index');

        $report = Report::where('class_id', $this->classA->id)->firstOrFail();

        $this->assertSame(1, $report->attendanceMedia()->count());
        $media = $report->attendanceMedia()->first();
        $this->assertSame('attendance', $media->type);
        $this->assertSame('report_media', $media->disk);
        Storage::disk('report_media')->assertExists($media->path);
        $this->assertStringContainsString('/attendance/', $media->path);
    }

    public function test_attendance_media_over_cap_is_rejected(): void
    {
        $files = [];
        for ($i = 0; $i < 6; $i++) {
            $files[] = UploadedFile::fake()->image("bukti-{$i}.jpg", 100, 100);
        }

        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), [
                'class_id' => $this->classA->id,
                'report_date' => now()->toDateString(),
                'lesson_material' => 'Materi',
                'goals_materi' => 'Goals',
                'activity_report' => 'Aktivitas',
                'attendance' => [$this->classA->students()->first()->id => 'present'],
                'attendance_media' => $files,
            ])
            ->assertSessionHasErrors('attendance_media');

        $this->assertSame(0, Report::where('class_id', $this->classA->id)->count());
    }

    public function test_attendance_media_visible_on_review_page(): void
    {
        $report = $this->makeReport($this->schoolA, $this->classA, $this->coach, 'submitted');
        $report->media()->create([
            'type' => 'attendance',
            'disk' => 'report_media',
            'path' => 'reports/2026/1/attendance/bukti.jpg',
            'original_name' => 'bukti.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        $this->actingAs($this->relation)
            ->get(route('admin.reports.show', $report))
            ->assertOk()
            ->assertSee('Bukti Absensi');
    }
}
