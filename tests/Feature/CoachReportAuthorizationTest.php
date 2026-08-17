<?php

namespace Tests\Feature;

use App\Models\CoachClass;
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
 * Guards the Coach report workflow against cross-school writes.
 *
 * A Coach may only report on classes they are assigned to, and may only
 * record attendance for students that belong to the reported class.
 */
class CoachReportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;
    private SchoolClass $assignedClass;
    private SchoolClass $foreignClass;
    private Student $assignedStudent;
    private Student $foreignStudent;

    protected function setUp(): void
    {
        parent::setUp();

        $schoolA = School::create(['name' => 'School A']);
        $schoolB = School::create(['name' => 'School B']);

        $this->assignedClass = SchoolClass::create(['school_id' => $schoolA->id, 'name' => 'A-1']);
        $this->foreignClass = SchoolClass::create(['school_id' => $schoolB->id, 'name' => 'B-1']);

        $this->assignedStudent = Student::create(['class_id' => $this->assignedClass->id, 'name' => 'Student A']);
        $this->foreignStudent = Student::create(['class_id' => $this->foreignClass->id, 'name' => 'Student B']);

        $this->coach = User::create([
            'name' => 'Coach A',
            'email' => 'coach.a@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_COACH,
        ]);

        CoachClass::create([
            'coach_id' => $this->coach->id,
            'class_id' => $this->assignedClass->id,
        ]);
    }

    private function payload(int $classId, array $attendance): array
    {
        return [
            'class_id' => $classId,
            'report_date' => '2026-08-17',
            'lesson_material' => 'Materi',
            'activity_summary' => 'Ringkasan',
            'attendance' => $attendance,
        ];
    }

    public function test_coach_can_submit_report_for_assigned_class(): void
    {
        $response = $this->actingAs($this->coach)->post(
            route('coach.reports.store'),
            $this->payload($this->assignedClass->id, [$this->assignedStudent->id => 'present'])
        );

        $response->assertRedirectToRoute('coach.reports.index');
        $this->assertDatabaseHas('reports', [
            'coach_id' => $this->coach->id,
            'class_id' => $this->assignedClass->id,
        ]);
    }

    public function test_coach_cannot_submit_report_for_unassigned_class(): void
    {
        $response = $this->actingAs($this->coach)->post(
            route('coach.reports.store'),
            $this->payload($this->foreignClass->id, [$this->foreignStudent->id => 'present'])
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_coach_cannot_record_attendance_for_student_outside_the_class(): void
    {
        $response = $this->actingAs($this->coach)->post(
            route('coach.reports.store'),
            $this->payload($this->assignedClass->id, [$this->foreignStudent->id => 'present'])
        );

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('report_attendances', 0);
    }

    public function test_coach_cannot_update_report_with_foreign_student_attendance(): void
    {
        $report = Report::create([
            'coach_id' => $this->coach->id,
            'school_id' => $this->assignedClass->school_id,
            'class_id' => $this->assignedClass->id,
            'report_date' => '2026-08-17',
            'lesson_material' => 'Materi',
            'activity_summary' => 'Ringkasan',
            'status' => 'draft',
        ]);
        ReportAttendance::create([
            'report_id' => $report->id,
            'student_id' => $this->assignedStudent->id,
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->coach)->put(route('coach.reports.update', $report), [
            'report_date' => '2026-08-18',
            'lesson_material' => 'Materi',
            'activity_summary' => 'Ringkasan',
            'attendance' => [$this->foreignStudent->id => 'absent'],
        ]);

        $response->assertSessionHasErrors('attendance');
        $this->assertDatabaseMissing('report_attendances', [
            'student_id' => $this->foreignStudent->id,
        ]);
    }

    public function test_coach_cannot_read_the_global_report_review_console(): void
    {
        $foreignCoach = User::create([
            'name' => 'Coach B',
            'email' => 'coach.b@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_COACH,
        ]);

        $foreignReport = Report::create([
            'coach_id' => $foreignCoach->id,
            'school_id' => $this->foreignClass->school_id,
            'class_id' => $this->foreignClass->id,
            'report_date' => '2026-08-17',
            'lesson_material' => 'Rahasia',
            'activity_summary' => 'Rahasia',
            'status' => 'submitted',
        ]);

        $this->actingAs($this->coach)
            ->get(route('admin.reports.index'))
            ->assertForbidden();

        $this->actingAs($this->coach)
            ->get(route('admin.reports.show', $foreignReport))
            ->assertForbidden();
    }
}
