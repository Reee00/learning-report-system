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
use App\Services\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A report is one unit: the report row, its media and its attendance must land
 * together or not at all.
 *
 * Found in production data during the stabilization audit: two reports were
 * stored as "submitted" with zero attendance rows because a media upload
 * raised an error AFTER Report::create() had already committed and BEFORE
 * the attendance loop ran. There was no transaction, so the half-written
 * report survived the 500.
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

        // Use a fake disk for tests
        Storage::fake('report_media');

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

    public function test_successful_photo_upload_stores_report_with_media(): void
    {
        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), $this->reportPayload([
                'photos' => [$this->fakePhoto()],
            ]))
            ->assertRedirect(route('coach.reports.index'))
            ->assertSessionHasNoErrors();

        $report = Report::sole();
        $this->assertSame('submitted', $report->status);
        $this->assertSame(1, $report->photos()->count());
        $this->assertSame(2, $report->attendances()->count());

        // Verify file was stored on the fake disk
        $media = $report->photos->first();
        Storage::disk('report_media')->assertExists($media->path);
    }

    public function test_successful_video_upload_stores_report_with_media(): void
    {
        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), $this->reportPayload([
                'videos' => [UploadedFile::fake()->create('kegiatan.mp4', 128, 'video/mp4')],
            ]))
            ->assertRedirect(route('coach.reports.index'))
            ->assertSessionHasNoErrors();

        $report = Report::sole();
        $this->assertSame('submitted', $report->status);
        $this->assertSame(1, $report->videos()->count());
        $this->assertSame(2, $report->attendances()->count());

        $media = $report->videos->first();
        Storage::disk('report_media')->assertExists($media->path);
    }

    public function test_exceeding_the_photo_cap_is_refused_visibly_and_changes_nothing(): void
    {
        $report = $this->rejectedReportWithAttendance();

        for ($i = 0; $i < 10; $i++) {
            ReportMedia::create([
                'report_id' => $report->id,
                'type' => 'photo',
                'path' => "reports/2026/{$report->id}/images/existing-{$i}.jpg",
                'original_name' => 'existing-'.$i.'.jpg',
                'disk' => 'report_media',
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

    public function test_delete_media_removes_file_from_disk(): void
    {
        $report = $this->rejectedReportWithAttendance();

        // Create a file on the fake disk
        $path = "reports/2026/{$report->id}/images/to-delete.jpg";
        Storage::disk('report_media')->put($path, 'fake image content');

        $media = ReportMedia::create([
            'report_id' => $report->id,
            'type' => 'photo',
            'path' => $path,
            'original_name' => 'to-delete.jpg',
            'disk' => 'report_media',
        ]);

        $this->actingAs($this->coach)
            ->put(route('coach.reports.update', $report), [
                'report_date' => '2026-08-18',
                'lesson_material' => 'Materi Baru',
                'activity_summary' => 'Ringkasan Baru',
                'attendance' => [
                    $this->studentA->id => 'present',
                    $this->studentB->id => 'present',
                ],
                'delete_media' => [$media->id],
            ])
            ->assertRedirect(route('coach.reports.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ReportMedia::count());
        Storage::disk('report_media')->assertMissing($path);
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
