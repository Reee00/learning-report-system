<?php

namespace Tests\Feature;

use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Daftar laporan admin (audit UX 2026-09-11, P2): grouping Sekolah → Kelas.
 *
 * Verifies:
 * - Grouped view only shows schools/classes with report data in scope.
 * - A school-scoped role (PIC) cannot reveal other schools by manipulating
 *   the school_id filter — scope is enforced before filters.
 */
class ReportGroupingTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private User $relation;
    private User $picA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'Sekolah Alpha']);
        $this->schoolB = School::create(['name' => 'Sekolah Beta']);

        $coach = User::create([
            'name' => 'Coach', 'email' => 'coach@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_COACH,
        ]);
        $this->relation = User::create([
            'name' => 'Relation', 'email' => 'relation@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_RELATION,
        ]);
        $this->picA = User::create([
            'name' => 'PIC A', 'email' => 'pic.a@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_SCHOOL_PIC,
        ]);
        $this->picA->schools()->sync([$this->schoolA->id]);
    }

    private function makeReport(School $school, string $className, string $status = 'approved'): Report
    {
        $class = SchoolClass::firstOrCreate(
            ['school_id' => $school->id, 'name' => $className],
        );

        return Report::create([
            'coach_id' => $this->relation->id,
            'school_id' => $school->id,
            'class_id' => $class->id,
            'report_date' => '2026-09-01',
            'lesson_material' => 'Materi', 'goals_materi' => 'Goals',
            'activity_report' => 'Ringkasan',
            'status' => $status,
            'approved_by' => $this->relation->id,
            'approved_at' => now(),
        ]);
    }

    public function test_relation_sees_grouped_school_and_class(): void
    {
        $this->makeReport($this->schoolA, 'Grade 5A');
        $this->makeReport($this->schoolB, 'Grade 6B');

        $html = $this->actingAs($this->relation)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sekolah Alpha', $html);
        $this->assertStringContainsString('Sekolah Beta', $html);
        $this->assertStringContainsString('Grade 5A', $html);
        $this->assertStringContainsString('Grade 6B', $html);
    }

    public function test_pic_scope_cannot_be_bypassed_via_school_id_filter(): void
    {
        $this->makeReport($this->schoolA, 'Grade 5A');
        $this->makeReport($this->schoolB, 'Grade 6B');

        // PIC A mencoba filter ke sekolah lain — scope tetap dipegang server.
        $html = $this->actingAs($this->picA)
            ->get(route('admin.reports.index', ['school_id' => $this->schoolB->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Sekolah Alpha', $html);
        $this->assertStringNotContainsString('Sekolah Beta', $html);
        $this->assertStringNotContainsString('Grade 6B', $html);
    }

    public function test_school_without_reports_is_not_listed(): void
    {
        $this->makeReport($this->schoolA, 'Grade 5A');
        // School B has no reports — must not appear in the grouping.
        // (Dropdown filter boleh memuat semua sekolah dalam scope; yang
        // dites adalah accordion grouping, yang ID-nya md5(nama sekolah).)

        $html = $this->actingAs($this->relation)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('school-' . md5('Sekolah Alpha'), $html);
        $this->assertStringNotContainsString('school-' . md5('Sekolah Beta'), $html);
    }

    public function test_empty_state_is_shown_when_no_reports(): void
    {
        $html = $this->actingAs($this->relation)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Tidak ada laporan', $html);
    }
}
