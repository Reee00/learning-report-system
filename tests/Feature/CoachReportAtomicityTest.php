<?php

namespace Tests\Feature;

use App\Http\Controllers\Coach\ReportController;
use App\Models\CoachClass;
use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\ReportMedia;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A report is one unit: the report row, its media and its attendance must land
 * together or not at all.
 *
 * Found in production data during the stabilization audit: two reports were
 * stored as "submitted" with zero attendance rows because a Cloudinary upload
 * raised `Undefined array key "secure_url"` AFTER Report::create() had already
 * committed and BEFORE the attendance loop ran. There was no transaction, so
 * the half-written report survived the 500.
 */
class CoachReportAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;
    private School $school;
    private SchoolClass $class;
    private Student $studentA;
    private Student $studentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::create(['name' => 'SD Atomic']);
        $this->class = SchoolClass::create([
            'school_id' => $this->school->id,
            'name' => 'Grade 1A',
        ]);
        $this->studentA = Student::create(['class_id' => $this->class->id, 'name' => 'Siswa A']);
        $this->studentB = Student::create(['class_id' => $this->class->id, 'name' => 'Siswa B']);

        $this->coach = User::create([
            'name' => 'Coach Atomic',
            'email' => 'coach.atomic@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->class->id]);

        // Replace only the network boundary. Everything else — validation, the
        // secure_url guard, the transaction — runs for real.
        $this->app->bind(ReportController::class, FailingUploadReportController::class);
    }

    private function fakePhoto(string $name = 'kegiatan.jpg'): UploadedFile
    {
        return UploadedFile::fake()->create($name, 64, 'image/jpeg');
    }

    private function reportPayload(array $overrides = []): array
    {
        return array_merge([
            'class_id' => $this->class->id,
            'report_date' => '2026-08-17',
            'lesson_material' => 'Materi',
            'activity_summary' => 'Ringkasan',
            'attendance' => [
                $this->studentA->id => 'present',
                $this->studentB->id => 'sick',
            ],
        ], $overrides);
    }

    public function test_a_failed_photo_upload_stores_no_report_at_all(): void
    {
        $this->actingAs($this->coach)
            ->from(route('coach.reports.create'))
            ->post(route('coach.reports.store'), $this->reportPayload([
                'photos' => [$this->fakePhoto()],
            ]))
            ->assertRedirect(route('coach.reports.create'))
            ->assertSessionHasErrors('photos');

        // The pre-fix behaviour was: 1 report, 0 attendance, 0 media, HTTP 500.
        $this->assertSame(0, Report::count(), 'A failed upload must not leave a report behind.');
        $this->assertSame(0, ReportAttendance::count());
        $this->assertSame(0, ReportMedia::count());
    }

    public function test_a_failed_video_upload_stores_no_report_at_all(): void
    {
        $this->actingAs($this->coach)
            ->from(route('coach.reports.create'))
            ->post(route('coach.reports.store'), $this->reportPayload([
                'videos' => [UploadedFile::fake()->create('kegiatan.mp4', 128, 'video/mp4')],
            ]))
            ->assertRedirect(route('coach.reports.create'))
            ->assertSessionHasErrors('videos');

        $this->assertSame(0, Report::count());
        $this->assertSame(0, ReportAttendance::count());
        $this->assertSame(0, ReportMedia::count());
    }

    public function test_a_report_without_media_still_saves_with_its_attendance(): void
    {
        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), $this->reportPayload())
            ->assertRedirect(route('coach.reports.index'))
            ->assertSessionHasNoErrors();

        $report = Report::sole();

        $this->assertSame('submitted', $report->status);
        $this->assertSame(2, $report->attendances()->count());
        $this->assertSame('present', $report->attendances()->where('student_id', $this->studentA->id)->value('status'));
        $this->assertSame('sick', $report->attendances()->where('student_id', $this->studentB->id)->value('status'));
    }

    public function test_a_failed_upload_on_update_keeps_the_previous_attendance(): void
    {
        $report = $this->rejectedReportWithAttendance();

        $this->actingAs($this->coach)
            ->from(route('coach.reports.edit', $report))
            ->put(route('coach.reports.update', $report), [
                'report_date' => '2026-08-18',
                'lesson_material' => 'Materi Baru',
                'activity_summary' => 'Ringkasan Baru',
                'attendance' => [
                    $this->studentA->id => 'absent',
                    $this->studentB->id => 'absent',
                ],
                'photos' => [$this->fakePhoto()],
            ])
            ->assertRedirect(route('coach.reports.edit', $report))
            ->assertSessionHasErrors('photos');

        $report->refresh();

        // Pre-fix: the report was already flipped to "submitted" and its
        // attendance was deleted before the upload blew up.
        $this->assertSame('rejected', $report->status);
        $this->assertSame('Materi Lama', $report->lesson_material);
        $this->assertSame(2, $report->attendances()->count());
        $this->assertSame('present', $report->attendances()->where('student_id', $this->studentA->id)->value('status'));
        $this->assertSame(0, ReportMedia::count());
    }

    public function test_exceeding_the_photo_cap_is_refused_visibly_and_changes_nothing(): void
    {
        $report = $this->rejectedReportWithAttendance();

        for ($i = 0; $i < 10; $i++) {
            ReportMedia::create([
                'report_id' => $report->id,
                'type' => 'photo',
                'path' => 'https://res.cloudinary.com/demo/image/upload/existing-'.$i.'.jpg',
                'original_name' => 'existing-'.$i.'.jpg',
            ]);
        }

        $this->actingAs($this->coach)
            ->from(route('coach.reports.edit', $report))
            ->put(route('coach.reports.update', $report), [
                'report_date' => '2026-08-18',
                'lesson_material' => 'Materi Baru',
                'activity_summary' => 'Ringkasan Baru',
                'attendance' => [
                    $this->studentA->id => 'absent',
                    $this->studentB->id => 'absent',
                ],
                'photos' => [$this->fakePhoto('kesebelas.jpg')],
            ])
            ->assertRedirect(route('coach.reports.edit', $report))
            // Used to be back()->with('error'), which the edit view never
            // renders — the coach saw a silent no-op on a mutated report.
            ->assertSessionHasErrors(['photos' => 'Total foto tidak boleh lebih dari 10.']);

        $report->refresh();

        $this->assertSame('rejected', $report->status);
        $this->assertSame('Materi Lama', $report->lesson_material);
        $this->assertSame(10, $report->photos()->count());
        $this->assertSame('present', $report->attendances()->where('student_id', $this->studentA->id)->value('status'));
    }

    /** A rejected report the coach is allowed to edit, with attendance already recorded. */
    private function rejectedReportWithAttendance(): Report
    {
        $report = Report::create([
            'coach_id' => $this->coach->id,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'report_date' => '2026-08-17',
            'lesson_material' => 'Materi Lama',
            'activity_summary' => 'Ringkasan Lama',
            'status' => 'rejected',
        ]);

        foreach ([$this->studentA->id => 'present', $this->studentB->id => 'sick'] as $studentId => $status) {
            ReportAttendance::create([
                'report_id' => $report->id,
                'student_id' => $studentId,
                'status' => $status,
            ]);
        }

        return $report;
    }
}

/**
 * Cloudinary rejecting an upload returns a decoded body with no "secure_url" —
 * this is the exact shape that produced the two damaged production reports.
 */
class FailingUploadReportController extends ReportController
{
    protected function uploadToCloudinary(string $absolutePath, string $folder)
    {
        return ['error' => ['message' => 'Invalid cloud_name']];
    }
}
