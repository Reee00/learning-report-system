<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\ReportAttendance;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mandatory security gate: attendance view and export must never leak data
 * from a school the acting user is not plotted to, including through
 * query-parameter manipulation.
 */
class CrossSchoolSecurityTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private Student $studentA;
    private Student $studentB;
    private User $picA;
    private User $financeA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'School A']);
        $this->schoolB = School::create(['name' => 'School B']);

        $coach = User::create([
            'name' => 'Coach',
            'email' => 'coach@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_COACH,
        ]);

        [$this->studentA, $this->studentB] = collect([$this->schoolA, $this->schoolB])
            ->map(function (School $school) use ($coach): Student {
                $class = SchoolClass::create(['school_id' => $school->id, 'name' => $school->name.'-1']);
                $student = Student::create(['class_id' => $class->id, 'name' => 'Student '.$school->name]);

                $report = Report::create([
                    'coach_id' => $coach->id,
                    'school_id' => $school->id,
                    'class_id' => $class->id,
                    'report_date' => '2026-08-17',
                    'lesson_material' => 'Materi',
                    'activity_summary' => 'Ringkasan',
                    'status' => 'approved',
                ]);
                ReportAttendance::create([
                    'report_id' => $report->id,
                    'student_id' => $student->id,
                    'status' => 'present',
                ]);

                return $student;
            })
            ->all();

        $this->picA = User::create([
            'name' => 'PIC A',
            'email' => 'pic.a@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SCHOOL_PIC,
        ]);
        $this->picA->schools()->sync([$this->schoolA->id]);

        $this->financeA = User::create([
            'name' => 'Finance A',
            'email' => 'finance.a@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FINANCE,
        ]);
        $this->financeA->schools()->sync([$this->schoolA->id]);
    }

    public function test_pic_only_sees_the_plotted_school_attendance(): void
    {
        $this->actingAs($this->picA)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Student School A')
            ->assertDontSee('Student School B');
    }

    public function test_pic_cannot_pivot_to_another_school_via_query_parameter(): void
    {
        $this->actingAs($this->picA)
            ->get(route('attendance.index', ['school_id' => $this->schoolB->id]))
            ->assertOk()
            ->assertDontSee('Student School B');
    }

    public function test_pic_export_cannot_leak_another_school(): void
    {
        $csv = $this->actingAs($this->picA)
            ->get(route('attendance.export', ['school_id' => $this->schoolB->id]))
            ->assertOk()
            ->streamedContent();

        $this->assertStringNotContainsString('Student School B', $csv);
        $this->assertStringNotContainsString('School B', $csv);
    }

    public function test_pic_export_contains_the_plotted_school_rows(): void
    {
        $csv = $this->actingAs($this->picA)
            ->get(route('attendance.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Student School A', $csv);
        $this->assertStringNotContainsString('Student School B', $csv);
    }

    public function test_finance_export_is_scoped_and_csv_shaped(): void
    {
        $response = $this->actingAs($this->financeA)->get(route('attendance.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringStartsWith('tanggal,sekolah,kelas,coach,siswa,status_absensi,status_laporan', $csv);
        $this->assertStringContainsString('Student School A', $csv);
        $this->assertStringNotContainsString('Student School B', $csv);
    }

    public function test_pic_cannot_open_a_report_of_another_school(): void
    {
        $foreignReport = Report::where('school_id', $this->schoolB->id)->firstOrFail();

        $this->actingAs($this->picA)
            ->get(route('pic.reports.show', $foreignReport))
            ->assertForbidden();
    }

    public function test_pic_cannot_open_a_class_of_another_school(): void
    {
        $foreignClass = SchoolClass::where('school_id', $this->schoolB->id)->firstOrFail();

        $this->actingAs($this->picA)
            ->get(route('students.show', $foreignClass))
            ->assertForbidden();

        $this->actingAs($this->picA)
            ->get('/api/classes/'.$foreignClass->id.'/students')
            ->assertForbidden();
    }

    public function test_export_dataset_matches_the_filtered_table(): void
    {
        $rows = $this->actingAs($this->picA)
            ->get(route('attendance.export', ['attendance_status' => 'absent']))
            ->assertOk()
            ->streamedContent();

        // Only 'present' rows exist for School A, so an 'absent' filter is empty.
        $lines = array_values(array_filter(explode("\n", trim($rows))));
        $this->assertCount(1, $lines, 'Only the CSV header should be present for an empty filter result.');
    }
}
