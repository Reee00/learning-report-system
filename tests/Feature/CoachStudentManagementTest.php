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
 * Test suite for Coach Student Management feature.
 *
 * Business rules being verified:
 *   - Coach can see assigned classes (test 1)
 *   - Coach can add a student to an assigned class (test 2)
 *   - Student is automatically linked to the correct school/class (test 3)
 *   - Coach cannot add a student to a non-assigned class (test 4)
 *   - Manipulating class_id via the request is rejected (test 5)
 *   - Coach does not get global Student CRUD (test 6)
 *   - Relation retains full Student CRUD (test 7)
 *   - Attendance still works after feature addition (test 8)
 *   - Coach Report flow still works (test 9)
 *   - Existing tests baseline (all other test classes remain green) (test 10)
 */
class CoachStudentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $coach;
    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;  // assigned to $coach
    private SchoolClass $classB;  // NOT assigned to $coach

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'Sekolah A']);
        $this->schoolB = School::create(['name' => 'Sekolah B']);

        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas A-1']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas B-1']);

        $this->coach = User::create([
            'name'     => 'Coach Test',
            'email'    => 'coach@test.test',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_COACH,
        ]);

        // Assign coach ONLY to classA
        CoachClass::create([
            'coach_id' => $this->coach->id,
            'class_id' => $this->classA->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 1: Coach dapat melihat assigned class
    // -------------------------------------------------------------------------
    public function test_coach_can_see_assigned_classes(): void
    {
        $this->actingAs($this->coach)
            ->get(route('coach.students.index'))
            ->assertOk()
            ->assertSee('Kelas A-1')
            ->assertSee('Sekolah A');
    }

    public function test_coach_does_not_see_unassigned_classes(): void
    {
        $this->actingAs($this->coach)
            ->get(route('coach.students.index'))
            ->assertOk()
            ->assertDontSee('Kelas B-1')
            ->assertDontSee('Sekolah B');
    }

    // -------------------------------------------------------------------------
    // Test 2: Coach dapat menambahkan siswa ke assigned class
    // -------------------------------------------------------------------------
    public function test_coach_can_add_student_to_assigned_class(): void
    {
        $this->actingAs($this->coach)
            ->post(route('students.store', $this->classA), ['name' => 'Siswa Baru'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('students', [
            'class_id' => $this->classA->id,
            'name'     => 'Siswa Baru',
        ]);
    }

    // -------------------------------------------------------------------------
    // Test 3: Student otomatis terhubung ke School/Class yang benar
    // -------------------------------------------------------------------------
    public function test_added_student_is_linked_to_correct_school_and_class(): void
    {
        $this->actingAs($this->coach)
            ->post(route('students.store', $this->classA), ['name' => 'Dian Pratiwi'])
            ->assertRedirect();

        $student = Student::where('name', 'Dian Pratiwi')->firstOrFail();

        $this->assertEquals($this->classA->id, $student->class_id);
        $this->assertEquals($this->schoolA->id, $student->schoolClass->school_id);
        $this->assertTrue(
            $this->schoolA->students()->whereKey($student->id)->exists()
        );
    }

    // -------------------------------------------------------------------------
    // Test 4: Coach tidak dapat menambahkan siswa ke class lain
    // -------------------------------------------------------------------------
    public function test_coach_cannot_add_student_to_unassigned_class(): void
    {
        $this->actingAs($this->coach)
            ->post(route('students.store', $this->classB), ['name' => 'Unauthorized Student'])
            ->assertForbidden();

        $this->assertDatabaseMissing('students', ['name' => 'Unauthorized Student']);
    }

    public function test_coach_cannot_view_students_of_unassigned_class(): void
    {
        $this->actingAs($this->coach)
            ->get(route('students.show', $this->classB))
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Test 5: Manipulasi school_id/class_id via request ditolak
    // -------------------------------------------------------------------------
    public function test_coach_cannot_bypass_class_scope_by_manipulating_class_in_url(): void
    {
        // Route binding uses the URL segment for SchoolClass, but StudentController
        // re-validates it through canAccessClass(). Attempt to POST to classB's
        // student store endpoint must be denied regardless of what the body says.
        $this->actingAs($this->coach)
            ->post(route('students.store', $this->classB), [
                'name'     => 'Bypassed Student',
                'class_id' => $this->classA->id, // try to override with assigned class
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('students', ['name' => 'Bypassed Student']);
    }

    public function test_coach_cannot_import_to_unassigned_class(): void
    {
        $this->actingAs($this->coach)
            ->post(route('students.import', $this->classB), [])
            ->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Test 6: Coach tidak mendapatkan CRUD Student global
    // -------------------------------------------------------------------------
    public function test_coach_cannot_delete_student(): void
    {
        $student = Student::create([
            'class_id' => $this->classA->id,
            'name'     => 'Siswa Hapus',
        ]);

        // Delete route requires students.delete permission which coach does NOT have
        $this->actingAs($this->coach)
            ->delete(route('students.destroy', [$this->classA, $student]))
            ->assertForbidden();

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_coach_cannot_reach_admin_student_management_screens(): void
    {
        // Coach cannot access Relation / admin-level school/class management
        foreach (['admin.schools.index', 'admin.classes.index'] as $route) {
            $this->actingAs($this->coach)
                ->get(route($route))
                ->assertForbidden("Coach must not reach {$route}.");
        }
    }

    // -------------------------------------------------------------------------
    // Test 7: Relation tetap dapat CRUD Student
    // -------------------------------------------------------------------------
    public function test_relation_retains_full_student_crud(): void
    {
        $relation = User::create([
            'name'     => 'Relation User',
            'email'    => 'relation@test.test',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_RELATION,
        ]);

        // Create
        $this->actingAs($relation)
            ->post(route('students.store', $this->classA), ['name' => 'Siswa Relation'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $student = Student::where('name', 'Siswa Relation')->firstOrFail();

        // Delete
        $this->actingAs($relation)
            ->delete(route('students.destroy', [$this->classA, $student]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_relation_can_view_students_of_any_class(): void
    {
        $relation = User::create([
            'name'     => 'Relation View',
            'email'    => 'relation2@test.test',
            'password' => Hash::make('password'),
            'role'     => User::ROLE_RELATION,
        ]);

        // Relation can view both classes (even classB which coach cannot)
        $this->actingAs($relation)->get(route('students.show', $this->classA))->assertOk();
        $this->actingAs($relation)->get(route('students.show', $this->classB))->assertOk();
    }

    // -------------------------------------------------------------------------
    // Test 8: Attendance existing tetap bekerja
    // -------------------------------------------------------------------------
    public function test_attendance_still_works_after_coach_adds_student(): void
    {
        // Add student via coach
        $this->actingAs($this->coach)
            ->post(route('students.store', $this->classA), ['name' => 'Siswa Absen'])
            ->assertRedirect();

        $student = Student::where('name', 'Siswa Absen')->firstOrFail();

        // Create a report with attendance
        $report = Report::create([
            'coach_id'         => $this->coach->id,
            'school_id'        => $this->schoolA->id,
            'class_id'         => $this->classA->id,
            'report_date'      => '2026-08-19',
            'lesson_material'  => 'Materi',
            'activity_summary' => 'Ringkasan',
            'status'           => 'submitted',
        ]);

        ReportAttendance::create([
            'report_id'  => $report->id,
            'student_id' => $student->id,
            'status'     => 'present',
        ]);

        // Verify attendance is retrievable via the report
        $this->assertEquals(1, $report->attendances()->count());
        $this->assertEquals($student->id, $report->attendances()->first()->student_id);
        $this->assertEquals('present', $report->attendances()->first()->status);
    }

    // -------------------------------------------------------------------------
    // Test 9: Coach Report existing tetap bekerja setelah siswa ditambahkan coach
    // -------------------------------------------------------------------------
    public function test_coach_report_works_with_student_added_by_coach(): void
    {
        // Coach adds a student
        $this->actingAs($this->coach)
            ->post(route('students.store', $this->classA), ['name' => 'Siswa Report'])
            ->assertRedirect();

        $student = Student::where('name', 'Siswa Report')->firstOrFail();

        // Coach creates a report using that student in attendance
        $this->actingAs($this->coach)
            ->post(route('coach.reports.store'), [
                'class_id'         => $this->classA->id,
                'report_date'      => '2026-08-19',
                'lesson_material'  => 'Pengenalan algoritma',
                'activity_summary' => 'Kegiatan belajar mengajar',
                'attendance'       => [$student->id => 'present'],
            ])
            ->assertRedirectToRoute('coach.reports.index');

        $report = Report::where('class_id', $this->classA->id)->firstOrFail();
        $this->assertEquals('submitted', $report->status);
        $this->assertEquals(1, $report->attendances()->count());
        $this->assertEquals($student->id, $report->attendances()->first()->student_id);
    }

    // -------------------------------------------------------------------------
    // Test 10: Coach tidak mendapatkan permission students.delete
    // -------------------------------------------------------------------------
    public function test_coach_student_create_permission_does_not_grant_delete(): void
    {
        // Verify that adding students.create to coach does NOT accidentally give delete
        $authService = app(\App\Services\AuthorizationService::class);

        $this->assertTrue($authService->allows($this->coach, 'students.view'));
        $this->assertTrue($authService->allows($this->coach, 'students.create'));
        $this->assertFalse($authService->allows($this->coach, 'students.delete'));
    }

    public function test_coach_assigned_class_student_list_is_accessible(): void
    {
        Student::create(['class_id' => $this->classA->id, 'name' => 'Visible Student']);

        $this->actingAs($this->coach)
            ->get(route('students.show', $this->classA))
            ->assertOk()
            ->assertSee('Visible Student');
    }
}
