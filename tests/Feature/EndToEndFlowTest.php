<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Program;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Walks the complete Phase 0-12 journey through real HTTP requests:
 * SuperAdmin -> School -> PIC plotting -> Student -> Program Kelas -> Coach ->
 * assignment -> Program -> Coach report + attendance -> approval ->
 * PIC view/export -> Finance CSV -> Accident Notes.
 */
class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_full_learning_report_journey_runs_end_to_end(): void
    {
        // ---- Step 1: SuperAdmin logs in and lands on the dashboard. -------
        $superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@lrs.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPERADMIN,
        ]);

        $this->post('/login', ['email' => 'superadmin@lrs.test', 'password' => 'password'])
            ->assertRedirectToRoute('admin.dashboard');
        $this->get(route('admin.dashboard'))->assertOk();

        // ---- Step 2: create a School. -------------------------------------
        $this->actingAs($superadmin)
            ->post(route('admin.schools.store'), [
                'name' => 'SD Nusantara',
                'address' => 'Jl. Melati 1',
                'pic_name' => 'Pak Budi',
            ])
            ->assertSessionHasNoErrors();

        $school = School::where('name', 'SD Nusantara')->firstOrFail();

        // ---- Step 3: create a Program Kelas under that school. ------------
        $this->actingAs($superadmin)
            ->post(route('admin.classes.store'), [
                'school_id' => $school->id,
                'name' => 'Grade 6A',
            ])
            ->assertSessionHasNoErrors();

        $class = SchoolClass::where('name', 'Grade 6A')->firstOrFail();
        $this->assertSame($school->id, $class->school_id);

        // ---- Step 4: plot a School PIC to the school. ---------------------
        $this->actingAs($superadmin)
            ->post(route('admin.users.store'), [
                'name' => 'PIC Nusantara',
                'email' => 'pic@lrs.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => User::ROLE_SCHOOL_PIC,
                'school_ids' => [$school->id],
            ])
            ->assertSessionHasNoErrors();

        $pic = User::where('email', 'pic@lrs.test')->firstOrFail();
        $this->assertSame([$school->id], $pic->assignedSchoolIds());

        // ---- Step 5: add Students to the class. ---------------------------
        foreach (['Andi', 'Bela', 'Citra'] as $studentName) {
            $this->actingAs($superadmin)
                ->post(route('students.store', $class), ['name' => $studentName])
                ->assertSessionHasNoErrors();
        }
        $this->assertSame(3, Student::where('class_id', $class->id)->count());

        // ---- Step 6: create a Coach and assign the class. -----------------
        $this->actingAs($superadmin)
            ->post(route('admin.coaches.store'), [
                'name' => 'Coach Rina',
                'email' => 'coach@lrs.test',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $coach = User::where('email', 'coach@lrs.test')->firstOrFail();
        $this->assertSame(User::ROLE_COACH, $coach->role);

        $this->actingAs($superadmin)
            ->post(route('admin.coaches.assign', $coach), ['class_id' => $class->id])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coach_classes', [
            'coach_id' => $coach->id,
            'class_id' => $class->id,
        ]);

        // ---- Step 7: create a reusable Program bound to the class. --------
        $this->actingAs($superadmin)
            ->post(route('admin.programs.store'), [
                'name' => 'Coding Dasar',
                'code' => 'CD-01',
                'status' => 'active',
                'class_ids' => [$class->id],
            ])
            ->assertSessionHasNoErrors();

        $program = Program::where('code', 'CD-01')->firstOrFail();
        $this->assertTrue($program->classes()->where('classes.id', $class->id)->exists());

        // ---- Step 8: Coach logs in and submits a report with attendance. --
        $this->post('/login', ['email' => 'coach@lrs.test', 'password' => 'password'])
            ->assertRedirectToRoute('coach.reports.index');

        $students = Student::where('class_id', $class->id)->orderBy('name')->get();
        $attendance = [
            $students[0]->id => 'present',
            $students[1]->id => 'sick',
            $students[2]->id => 'absent',
        ];

        $this->actingAs($coach)
            ->post(route('coach.reports.store'), [
                'class_id' => $class->id,
                'report_date' => '2026-08-17',
                'lesson_material' => 'Pengenalan algoritma',
                'activity_summary' => "Baris pertama\nBaris kedua",
                'notes' => 'Bela terjatuh saat istirahat dan sudah ditangani UKS.',
                'attendance' => $attendance,
            ])
            ->assertRedirectToRoute('coach.reports.index');

        $report = Report::where('class_id', $class->id)->firstOrFail();
        $this->assertSame('submitted', $report->status);
        $this->assertSame($school->id, $report->school_id);
        $this->assertSame(3, $report->attendances()->count());

        // Coach sees the accident note on their own report list.
        $this->actingAs($coach)
            ->get(route('coach.reports.index'))
            ->assertOk()
            ->assertSee('Accident Notes')
            ->assertSee('Bela terjatuh saat istirahat dan sudah ditangani UKS.');

        // ---- Step 9: SuperAdmin reviews and approves the report. ----------
        $this->actingAs($superadmin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertSee('Coach Rina');

        $this->actingAs($superadmin)
            ->get(route('admin.reports.show', $report))
            ->assertOk()
            ->assertSee('Accident Notes');

        $this->actingAs($superadmin)
            ->patch(route('admin.reports.approve', $report))
            ->assertSessionHas('success');

        $this->assertSame('approved', $report->fresh()->status);

        // ---- Step 10: PIC logs in, reads the report and exports CSV. ------
        $this->post('/login', ['email' => 'pic@lrs.test', 'password' => 'password'])
            ->assertRedirectToRoute('pic.dashboard');

        $this->actingAs($pic)
            ->get(route('pic.dashboard'))
            ->assertOk()
            ->assertSee('Grade 6A');

        $this->actingAs($pic)
            ->get(route('pic.reports.show', $report))
            ->assertOk()
            ->assertSee('Accident Notes');

        $this->actingAs($pic)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Andi');

        $picCsv = $this->actingAs($pic)
            ->get(route('attendance.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Andi', $picCsv);
        $this->assertStringContainsString('SD Nusantara', $picCsv);

        // ---- Step 11: Finance logs in, filters and exports CSV. -----------
        $this->actingAs($superadmin)
            ->post(route('admin.users.store'), [
                'name' => 'Finance Pusat',
                'email' => 'finance@lrs.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => User::ROLE_FINANCE,
                'school_ids' => [$school->id],
            ])
            ->assertSessionHasNoErrors();

        $finance = User::where('email', 'finance@lrs.test')->firstOrFail();

        $this->post('/login', ['email' => 'finance@lrs.test', 'password' => 'password'])
            ->assertRedirectToRoute('attendance.index');

        $this->actingAs($finance)
            ->get(route('attendance.index', ['school_id' => $school->id, 'attendance_status' => 'sick']))
            ->assertOk()
            ->assertSee('Bela');

        $financeCsv = $this->actingAs($finance)
            ->get(route('attendance.export', ['school_id' => $school->id, 'attendance_status' => 'sick']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringStartsWith('tanggal,sekolah,kelas,coach,siswa,status_absensi,status_laporan', $financeCsv);
        $this->assertStringContainsString('Bela', $financeCsv);
        $this->assertStringNotContainsString('Andi', $financeCsv);

        // ---- Step 12: SPV Coach logs in and manages coaches. --------------
        $this->actingAs($superadmin)
            ->post(route('admin.users.store'), [
                'name' => 'SPV Coach',
                'email' => 'spv@lrs.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => User::ROLE_SPV_COACH,
            ])
            ->assertSessionHasNoErrors();

        $spv = User::where('email', 'spv@lrs.test')->firstOrFail();

        $this->post('/login', ['email' => 'spv@lrs.test', 'password' => 'password'])
            ->assertRedirectToRoute('admin.coaches.index');

        $this->actingAs($spv)
            ->get(route('admin.coaches.index'))
            ->assertOk()
            ->assertSee('Coach Rina');

        // SPV Coach reassignment: unassign then assign again.
        $assignment = CoachClass::where('coach_id', $coach->id)->firstOrFail();

        $this->actingAs($spv)
            ->delete(route('admin.coaches.unassign', [$coach, $assignment]))
            ->assertSessionHas('success');

        $this->actingAs($spv)
            ->post(route('admin.coaches.assign', $coach), ['class_id' => $class->id])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coach_classes', [
            'coach_id' => $coach->id,
            'class_id' => $class->id,
        ]);
    }
}
