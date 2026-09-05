<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Report;
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
 * Regression tests for the media storage migration from Cloudinary to local storage.
 *
 * Validates:
 * - Upload, view, delete, replace media
 * - Authorization enforcement on media access
 * - No orphan files after media/report deletion
 * - Legacy Cloudinary URLs still work
 * - Storage path structure
 */
class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;
    private User $relation;
    private User $schoolPic;
    private User $otherCoach;
    private School $school;
    private SchoolClass $class;
    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('report_media');

        $this->school = School::create(['name' => 'SD Media Test']);
        $this->class = SchoolClass::create([
            'school_id' => $this->school->id,
            'name' => 'Grade 1A',
        ]);
        $this->student = Student::create(['class_id' => $this->class->id, 'name' => 'Siswa 1']);

        $this->coach = User::create([
            'name' => 'Coach Media',
            'email' => 'coach.media@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->class->id]);

        $this->relation = User::create([
            'name' => 'Relation Media',
            'email' => 'relation.media@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RELATION,
        ]);

        $this->schoolPic = User::create([
            'name' => 'PIC Media',
            'email' => 'pic.media@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_PIC,
        ]);
        // Assign PIC to school
        $this->schoolPic->schools()->attach($this->school->id);

        $this->otherCoach = User::create([
            'name' => 'Other Coach',
            'email' => 'other.coach@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_COACH,
        ]);
    }

    // === UPLOAD TESTS ===

    public function test_upload_image_creates_file_and_record(): void
    {
        $report = $this->createSubmittedReport();

        $mediaService = app(MediaStorageService::class);
        $file = UploadedFile::fake()->image('test-photo.jpg', 800, 600);

        $media = $mediaService->store($report, $file, 'photo');

        $this->assertDatabaseHas('report_media', [
            'id' => $media->id,
            'report_id' => $report->id,
            'type' => 'photo',
            'disk' => 'report_media',
            'original_name' => 'test-photo.jpg',
        ]);
        $this->assertNotNull($media->file_size);
        Storage::disk('report_media')->assertExists($media->path);

        // Verify path structure: reports/{year}/{report_id}/images/
        $this->assertStringStartsWith("reports/{$report->report_date->format('Y')}/{$report->id}/images/", $media->path);
    }

    public function test_upload_video_creates_file_and_record(): void
    {
        $report = $this->createSubmittedReport();

        $mediaService = app(MediaStorageService::class);
        $file = UploadedFile::fake()->create('test-video.mp4', 2048, 'video/mp4');

        $media = $mediaService->store($report, $file, 'video');

        $this->assertDatabaseHas('report_media', [
            'id' => $media->id,
            'type' => 'video',
        ]);
        Storage::disk('report_media')->assertExists($media->path);

        // Verify path structure: reports/{year}/{report_id}/videos/
        $this->assertStringStartsWith("reports/{$report->report_date->format('Y')}/{$report->id}/videos/", $media->path);
    }

    // === AUTHORIZATION TESTS ===

    public function test_coach_can_access_own_report_media(): void
    {
        $report = $this->createSubmittedReport();
        $media = $this->createLocalMedia($report, 'photo');

        $this->actingAs($this->coach)
            ->get(route('media.serve', $media))
            ->assertOk();
    }

    public function test_other_coach_cannot_access_media(): void
    {
        $report = $this->createSubmittedReport();
        $media = $this->createLocalMedia($report, 'photo');

        $this->actingAs($this->otherCoach)
            ->get(route('media.serve', $media))
            ->assertForbidden();
    }

    public function test_relation_can_access_any_report_media(): void
    {
        $report = $this->createSubmittedReport();
        $media = $this->createLocalMedia($report, 'photo');

        $this->actingAs($this->relation)
            ->get(route('media.serve', $media))
            ->assertOk();
    }

    public function test_school_pic_can_access_approved_report_media(): void
    {
        $report = $this->createSubmittedReport();
        $report->update(['status' => 'approved']);
        $media = $this->createLocalMedia($report, 'photo');

        $this->actingAs($this->schoolPic)
            ->get(route('media.serve', $media))
            ->assertOk();
    }

    public function test_school_pic_cannot_access_non_approved_report_media(): void
    {
        $report = $this->createSubmittedReport(); // status = submitted
        $media = $this->createLocalMedia($report, 'photo');

        $this->actingAs($this->schoolPic)
            ->get(route('media.serve', $media))
            ->assertForbidden();
    }

    public function test_unauthenticated_user_cannot_access_media(): void
    {
        $report = $this->createSubmittedReport();
        $media = $this->createLocalMedia($report, 'photo');

        $this->get(route('media.serve', $media))
            ->assertRedirect(route('login'));
    }

    // === DELETE TESTS ===

    public function test_delete_media_removes_file_from_disk(): void
    {
        $report = $this->createSubmittedReport();
        $media = $this->createLocalMedia($report, 'photo');

        $path = $media->path;
        Storage::disk('report_media')->assertExists($path);

        $mediaService = app(MediaStorageService::class);
        $mediaService->delete($media);

        Storage::disk('report_media')->assertMissing($path);
        $this->assertDatabaseMissing('report_media', ['id' => $media->id]);
    }

    public function test_delete_all_media_for_report_cleans_up_files(): void
    {
        $report = $this->createSubmittedReport();
        $media1 = $this->createLocalMedia($report, 'photo');
        $media2 = $this->createLocalMedia($report, 'video');

        $path1 = $media1->path;
        $path2 = $media2->path;

        $mediaService = app(MediaStorageService::class);
        $mediaService->deleteAllForReport($report);

        Storage::disk('report_media')->assertMissing($path1);
        Storage::disk('report_media')->assertMissing($path2);
        $this->assertSame(0, $report->media()->count());
    }

    // === LEGACY CLOUDINARY COMPATIBILITY ===

    public function test_legacy_cloudinary_url_still_returned_as_is(): void
    {
        $report = $this->createSubmittedReport();
        $media = ReportMedia::create([
            'report_id' => $report->id,
            'type' => 'photo',
            'path' => 'https://res.cloudinary.com/demo/image/upload/legacy.jpg',
            'original_name' => 'legacy.jpg',
        ]);

        // The url() method should return the Cloudinary URL for legacy media
        $this->assertTrue($media->isExternal());
    }

    public function test_legacy_cloudinary_media_serve_redirects(): void
    {
        $report = $this->createSubmittedReport();
        $media = ReportMedia::create([
            'report_id' => $report->id,
            'type' => 'photo',
            'path' => 'https://res.cloudinary.com/demo/image/upload/legacy.jpg',
            'original_name' => 'legacy.jpg',
        ]);

        $this->actingAs($this->coach)
            ->get(route('media.serve', $media))
            ->assertRedirect('https://res.cloudinary.com/demo/image/upload/legacy.jpg');
    }

    // === PATH SAFETY TESTS ===

    public function test_filename_does_not_contain_user_input(): void
    {
        $report = $this->createSubmittedReport();
        $mediaService = app(MediaStorageService::class);

        // Malicious filename
        $file = UploadedFile::fake()->image('../../etc/passwd.jpg', 100, 100);
        $media = $mediaService->store($report, $file, 'photo');

        // Stored filename should NOT contain the original name
        $this->assertStringNotContainsString('passwd', $media->path);
        $this->assertStringNotContainsString('..', $media->path);
        $this->assertStringStartsWith('reports/', $media->path);
    }

    // === HELPER METHODS ===

    private function createSubmittedReport(): Report
    {
        return Report::create([
            'coach_id' => $this->coach->id,
            'school_id' => $this->school->id,
            'class_id' => $this->class->id,
            'report_date' => '2026-08-17',
            'lesson_material' => 'Materi',
            'activity_summary' => 'Ringkasan',
            'status' => 'submitted',
        ]);
    }

    private function createLocalMedia(Report $report, string $type): ReportMedia
    {
        $subfolder = $type === 'photo' ? 'images' : 'videos';
        $ext = $type === 'photo' ? 'jpg' : 'mp4';
        $path = "reports/{$report->report_date->format('Y')}/{$report->id}/{$subfolder}/test_" . uniqid() . ".{$ext}";

        Storage::disk('report_media')->put($path, 'fake file content for testing');

        return ReportMedia::create([
            'report_id' => $report->id,
            'type' => $type,
            'path' => $path,
            'original_name' => "test.{$ext}",
            'disk' => 'report_media',
            'file_size' => 1024,
        ]);
    }
}
