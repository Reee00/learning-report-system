<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\ReportMedia;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Download laporan (audit UX 2026-09-11, P0).
 *
 * Verifies the print/PDF output includes media:
 * - Photos and attendance media render inline via the authorized media URL.
 * - Videos appear as a poster placeholder + filename reference (a video
 *   cannot be played inside a printed document).
 * - Reports without media show the empty state.
 */
class ReportDownloadMediaTest extends TestCase
{
    use RefreshDatabase;

    private User $relation;
    private Report $report;

    protected function setUp(): void
    {
        parent::setUp();

        $school = School::create(['name' => 'School Foto']);
        $class = SchoolClass::create(['school_id' => $school->id, 'name' => 'Kelas 5A']);
        $coach = User::create([
            'name' => 'Coach Foto', 'email' => 'coach.foto@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        $this->relation = User::create([
            'name' => 'Relation', 'email' => 'relation@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_RELATION,
        ]);

        $student = Student::create(['class_id' => $class->id, 'name' => 'Murid Satu']);
        $this->report = Report::create([
            'coach_id' => $coach->id,
            'school_id' => $school->id,
            'class_id' => $class->id,
            'report_date' => '2026-09-01',
            'lesson_material' => 'Materi', 'goals_materi' => 'Goals',
            'activity_report' => 'Ringkasan',
            'status' => 'approved',
            'approved_by' => $this->relation->id,
            'approved_at' => now(),
        ]);
        ReportAttendance::create([
            'report_id' => $this->report->id, 'student_id' => $student->id, 'status' => 'present',
        ]);
    }

    private function addMedia(string $type, string $name): ReportMedia
    {
        return ReportMedia::create([
            'report_id'     => $this->report->id,
            'type'          => $type,
            'path'          => "reports/test/{$name}",
            'original_name' => $name,
            'disk'          => 'local',
            'file_size'     => 1024,
        ]);
    }

    public function test_download_with_photo_and_video_renders_both(): void
    {
        $photo = $this->addMedia('photo', 'sesi-peraga.jpg');
        $attendance = $this->addMedia('attendance', 'bukti-absensi.jpg');
        $video = $this->addMedia('video', 'penampilan-akhir.mp4');

        $response = $this->actingAs($this->relation)
            ->get(route('admin.reports.download', $this->report))
            ->assertOk();

        $html = $response->getContent();

        // Photos render inline via the authorized route (private media —
        // never a public URL).
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString($photo->url(), $html);
        $this->assertStringContainsString($attendance->url(), $html);

        // Video is a placeholder + filename reference, not a playable embed.
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringContainsString('penampilan-akhir.mp4', $html);
    }

    public function test_download_without_media_shows_empty_state(): void
    {
        $html = $this->actingAs($this->relation)
            ->get(route('admin.reports.download', $this->report))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('Tidak ada', $html);
    }

    public function test_media_serve_route_is_authorized(): void
    {
        $photo = $this->addMedia('photo', 'sesi-peraga.jpg');

        // Anonymous visitor is redirected to login, not served the file.
        $this->get($photo->url())->assertRedirectToRoute('login');
    }
}
