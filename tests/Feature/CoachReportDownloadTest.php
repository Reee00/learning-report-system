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
 * Tests the Approved Coach Report download feature.
 *
 * Verifies:
 * - Only APPROVED reports can be downloaded.
 * - Finance is forbidden regardless.
 * - Each role's school scope is enforced on the download endpoint.
 * - Direct URL access without auth is redirected to login.
 * - Existing Coach report workflow remains unaffected.
 */
class CoachReportDownloadTest extends TestCase
{
    use RefreshDatabase;

    private School $schoolA;
    private School $schoolB;
    private SchoolClass $classA;
    private SchoolClass $classB;
    private User $coach;
    private User $superadmin;
    private User $relation;
    private User $spvCoach;
    private User $picA;     // PIC of School A only
    private User $picB;     // PIC of School B only
    private User $teacher;
    private User $finance;
    private Report $approvedReport;   // belongs to School A, Coach
    private Report $submittedReport;  // belongs to School A, Coach
    private Report $rejectedReport;   // belongs to School A, Coach

    protected function setUp(): void
    {
        parent::setUp();

        $this->schoolA = School::create(['name' => 'School A']);
        $this->schoolB = School::create(['name' => 'School B']);

        $this->classA = SchoolClass::create(['school_id' => $this->schoolA->id, 'name' => 'Kelas A']);
        $this->classB = SchoolClass::create(['school_id' => $this->schoolB->id, 'name' => 'Kelas B']);

        $this->coach = $this->makeUser(User::ROLE_COACH);
        CoachClass::create(['coach_id' => $this->coach->id, 'class_id' => $this->classA->id]);

        $this->superadmin = $this->makeUser(User::ROLE_SUPERADMIN);
        $this->relation   = $this->makeUser(User::ROLE_RELATION);
        $this->spvCoach   = $this->makeUser(User::ROLE_SPV_COACH);
        $this->finance    = $this->makeUser(User::ROLE_FINANCE, $this->schoolA);
        $this->teacher    = $this->makeUser(User::ROLE_TEACHER_SCHOOL, $this->schoolA);

        $this->picA = $this->makeUser(User::ROLE_SCHOOL_PIC, $this->schoolA);
        $this->picB = $this->makeUser(User::ROLE_SCHOOL_PIC, $this->schoolB, 'pic.b@test.test');

        // Create reports with various statuses
        $base = [
            'coach_id'         => $this->coach->id,
            'school_id'        => $this->schoolA->id,
            'class_id'         => $this->classA->id,
            'report_date'      => '2026-08-01',
            'lesson_material'  => 'Materi Tes',
            'activity_summary' => 'Ringkasan Tes',
        ];

        $this->approvedReport = Report::create(array_merge($base, [
            'status'      => 'approved',
            'approved_by' => $this->relation->id,
            'approved_at' => now(),
        ]));

        $this->submittedReport = Report::create(array_merge($base, [
            'report_date' => '2026-08-02',
            'status'      => 'submitted',
        ]));

        $this->rejectedReport = Report::create(array_merge($base, [
            'report_date' => '2026-08-03',
            'status'      => 'rejected',
        ]));
    }

    private function makeUser(string $role, ?School $school = null, ?string $email = null): User
    {
        $user = User::create([
            'name'     => 'User ' . $role . ($email ?? ''),
            'email'    => $email ?? ($role . '@test.test'),
            'password' => Hash::make('password'),
            'role'     => $role,
        ]);

        if ($school !== null) {
            $user->schools()->sync([$school->id]);
        }

        return $user;
    }

    // =====================================================================
    // APPROVED report — roles that SHOULD succeed
    // =====================================================================

    public function test_superadmin_can_download_approved_report(): void
    {
        $this->actingAs($this->superadmin)
            ->get(route('admin.reports.download', $this->approvedReport))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function test_relation_can_download_approved_report(): void
    {
        $this->actingAs($this->relation)
            ->get(route('admin.reports.download', $this->approvedReport))
            ->assertOk();
    }

    public function test_spv_coach_can_download_approved_report(): void
    {
        $this->actingAs($this->spvCoach)
            ->get(route('admin.reports.download', $this->approvedReport))
            ->assertOk();
    }

    public function test_coach_can_download_own_approved_report(): void
    {
        $this->actingAs($this->coach)
            ->get(route('coach.reports.download', $this->approvedReport))
            ->assertOk();
    }

    public function test_school_pic_can_download_approved_report_from_own_school(): void
    {
        $this->actingAs($this->picA)
            ->get(route('pic.reports.download', $this->approvedReport))
            ->assertOk();
    }

    public function test_teacher_school_can_download_approved_report_from_own_school(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('admin.reports.download', $this->approvedReport))
            ->assertOk();
    }

    // =====================================================================
    // APPROVED report — roles / scopes that SHOULD be DENIED
    // =====================================================================

    public function test_finance_cannot_download_via_admin_route(): void
    {
        $this->actingAs($this->finance)
            ->get(route('admin.reports.download', $this->approvedReport))
            ->assertForbidden();
    }

    public function test_pic_cannot_download_report_from_different_school(): void
    {
        // picB only has access to schoolB; approvedReport is from schoolA
        $this->actingAs($this->picB)
            ->get(route('pic.reports.download', $this->approvedReport))
            ->assertForbidden();
    }

    public function test_coach_cannot_download_another_coach_report(): void
    {
        $otherCoach = $this->makeUser(User::ROLE_COACH, null, 'other.coach@test.test');

        $this->actingAs($otherCoach)
            ->get(route('coach.reports.download', $this->approvedReport))
            ->assertForbidden();
    }

    // =====================================================================
    // NON-APPROVED statuses — all roles should be denied
    // =====================================================================

    public function test_submitted_report_cannot_be_downloaded_by_superadmin(): void
    {
        $this->actingAs($this->superadmin)
            ->get(route('admin.reports.download', $this->submittedReport))
            ->assertForbidden();
    }

    public function test_rejected_report_cannot_be_downloaded_by_superadmin(): void
    {
        $this->actingAs($this->superadmin)
            ->get(route('admin.reports.download', $this->rejectedReport))
            ->assertForbidden();
    }

    public function test_submitted_report_cannot_be_downloaded_by_coach(): void
    {
        $this->actingAs($this->coach)
            ->get(route('coach.reports.download', $this->submittedReport))
            ->assertForbidden();
    }

    public function test_rejected_report_cannot_be_downloaded_by_coach(): void
    {
        $this->actingAs($this->coach)
            ->get(route('coach.reports.download', $this->rejectedReport))
            ->assertForbidden();
    }

    // =====================================================================
    // Unauthenticated access
    // =====================================================================

    public function test_unauthenticated_download_is_redirected_to_login(): void
    {
        $this->get(route('admin.reports.download', $this->approvedReport))
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_coach_download_is_redirected_to_login(): void
    {
        $this->get(route('coach.reports.download', $this->approvedReport))
            ->assertRedirect(route('login'));
    }

    public function test_unauthenticated_pic_download_is_redirected_to_login(): void
    {
        $this->get(route('pic.reports.download', $this->approvedReport))
            ->assertRedirect(route('login'));
    }

    // =====================================================================
    // Download filename contains meaningful info
    // =====================================================================

    public function test_download_response_has_informative_filename(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->get(route('admin.reports.download', $this->approvedReport));

        $response->assertOk();
        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('Coach-Report-', $contentDisposition);
        $this->assertStringContainsString('school-a', $contentDisposition);
        $this->assertStringContainsString('.html', $contentDisposition);
    }

    // =====================================================================
    // Existing workflow regression: approval/rejection unaffected
    // =====================================================================

    public function test_downloading_does_not_change_report_status(): void
    {
        $this->actingAs($this->superadmin)
            ->get(route('admin.reports.download', $this->approvedReport));

        $this->assertDatabaseHas('reports', [
            'id'     => $this->approvedReport->id,
            'status' => 'approved',
        ]);
    }

    public function test_relation_can_still_approve_submitted_report(): void
    {
        $this->actingAs($this->relation)
            ->patch(route('admin.reports.approve', $this->submittedReport))
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'id'     => $this->submittedReport->id,
            'status' => 'approved',
        ]);
    }

    public function test_relation_can_still_reject_submitted_report(): void
    {
        $this->actingAs($this->relation)
            ->patch(route('admin.reports.reject', $this->submittedReport), [
                'admin_notes' => 'Perlu diperbaiki.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'id'     => $this->submittedReport->id,
            'status' => 'rejected',
        ]);
    }
}
