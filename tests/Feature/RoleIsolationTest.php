<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Backend role isolation: every denial below must come from middleware or
 * controller authorization, never from a hidden navigation item.
 */
class RoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    private School $school;

    protected function setUp(): void
    {
        parent::setUp();
        $this->school = School::create(['name' => 'School A']);
    }

    private function user(string $role, bool $withSchool = false): User
    {
        $user = User::create([
            'name' => 'User '.$role,
            'email' => $role.'@test.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);

        if ($withSchool) {
            $user->schools()->sync([$this->school->id]);
        }

        return $user;
    }

    public static function deniedUserManagementRoles(): array
    {
        return [
            'relation' => [User::ROLE_RELATION],
            'spv_coach' => [User::ROLE_SPV_COACH],
            'coach' => [User::ROLE_COACH],
            'school_pic' => [User::ROLE_SCHOOL_PIC],
            'finance' => [User::ROLE_FINANCE],
        ];
    }

    #[DataProvider('deniedUserManagementRoles')]
    public function test_user_management_is_superadmin_only(string $role): void
    {
        $this->actingAs($this->user($role, true))
            ->get(route('admin.users.index'))
            ->assertForbidden();

        $this->actingAs(User::where('role', $role)->firstOrFail())
            ->post(route('admin.users.store'), [
                'name' => 'Escalated',
                'email' => 'escalated@test.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => User::ROLE_SUPERADMIN,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'escalated@test.test']);
    }

    public function test_superadmin_reaches_every_master_data_screen(): void
    {
        $superadmin = $this->user(User::ROLE_SUPERADMIN);

        foreach ([
            'admin.dashboard',
            'admin.users.index',
            'admin.schools.index',
            'admin.classes.index',
            'admin.programs.index',
            'admin.coaches.index',
            'admin.reports.index',
            'attendance.index',
        ] as $route) {
            $this->actingAs($superadmin)
                ->get(route($route))
                ->assertOk("Route {$route} must be reachable by SuperAdmin.");
        }
    }

    public function test_relation_reaches_its_operational_screens_only(): void
    {
        $relation = $this->user(User::ROLE_RELATION);

        foreach (['admin.schools.index', 'admin.classes.index', 'admin.programs.index', 'attendance.index', 'admin.reports.index'] as $route) {
            $this->actingAs($relation)->get(route($route))->assertOk("Relation needs {$route}.");
        }

        foreach (['admin.users.index', 'admin.coaches.index'] as $route) {
            $this->actingAs($relation)->get(route($route))->assertForbidden("Relation must not reach {$route}.");
        }
    }

    public function test_spv_coach_reaches_coach_management_but_not_relation_master_data(): void
    {
        $spv = $this->user(User::ROLE_SPV_COACH);

        foreach (['admin.coaches.index', 'attendance.index', 'admin.reports.index'] as $route) {
            $this->actingAs($spv)->get(route($route))->assertOk("SPV Coach needs {$route}.");
        }

        foreach (['admin.schools.index', 'admin.classes.index', 'admin.programs.index', 'admin.users.index'] as $route) {
            $this->actingAs($spv)->get(route($route))->assertForbidden("SPV Coach must not reach {$route}.");
        }
    }

    public function test_finance_reaches_attendance_only(): void
    {
        $finance = $this->user(User::ROLE_FINANCE, true);

        $this->actingAs($finance)->get(route('attendance.index'))->assertOk();

        foreach ([
            'admin.users.index',
            'admin.schools.index',
            'admin.classes.index',
            'admin.programs.index',
            'admin.coaches.index',
            'admin.reports.index',
        ] as $route) {
            $this->actingAs($finance)->get(route($route))->assertForbidden("Finance must not reach {$route}.");
        }
    }

    public function test_school_pic_cannot_reach_school_master_data(): void
    {
        $pic = $this->user(User::ROLE_SCHOOL_PIC, true);

        $this->actingAs($pic)->get(route('pic.dashboard'))->assertOk();
        $this->actingAs($pic)->get(route('admin.schools.index'))->assertForbidden();
        $this->actingAs($pic)->post(route('admin.schools.store'), ['name' => 'Rogue School'])->assertForbidden();
        $this->assertDatabaseMissing('schools', ['name' => 'Rogue School']);
    }

    public function test_coach_cannot_reach_admin_master_data(): void
    {
        $coach = $this->user(User::ROLE_COACH);

        foreach ([
            'admin.users.index',
            'admin.schools.index',
            'admin.classes.index',
            'admin.programs.index',
            'admin.coaches.index',
        ] as $route) {
            $this->actingAs($coach)->get(route($route))->assertForbidden("Coach must not reach {$route}.");
        }
    }

    public function test_teacher_school_reaches_attendance_and_reports_only(): void
    {
        $teacher = $this->user(User::ROLE_TEACHER_SCHOOL, true);

        foreach (['attendance.index', 'admin.reports.index'] as $route) {
            $this->actingAs($teacher)->get(route($route))->assertOk("Teacher School needs {$route}.");
        }

        foreach ([
            'admin.users.index',
            'admin.schools.index',
            'admin.classes.index',
            'admin.programs.index',
            'admin.coaches.index',
        ] as $route) {
            $this->actingAs($teacher)->get(route($route))->assertForbidden("Teacher School must not reach {$route}.");
        }
    }

    public function test_guests_are_redirected_to_login_for_protected_routes(): void
    {
        foreach (['attendance.index', 'admin.schools.index', 'pic.dashboard', 'coach.reports.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $this->user(User::ROLE_SUPERADMIN);

        $this->post('/login', ['email' => 'superadmin@test.test', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_logout_clears_the_session(): void
    {
        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_every_role_can_login_and_land_on_a_reachable_page(): void
    {
        $expected = [
            User::ROLE_SUPERADMIN => 'admin.dashboard',
            User::ROLE_RELATION => 'admin.schools.index',
            User::ROLE_SPV_COACH => 'admin.coaches.index',
            User::ROLE_COACH => 'coach.reports.index',
            User::ROLE_SCHOOL_PIC => 'pic.dashboard',
            User::ROLE_TEACHER_SCHOOL => 'attendance.index',
            User::ROLE_FINANCE => 'attendance.index',
        ];

        foreach ($expected as $role => $route) {
            $needsSchool = in_array($role, [User::ROLE_SCHOOL_PIC, User::ROLE_TEACHER_SCHOOL, User::ROLE_FINANCE], true);
            $this->user($role, $needsSchool);

            $this->post('/login', ['email' => $role.'@test.test', 'password' => 'password'])
                ->assertRedirectToRoute($route);

            $this->get(route($route))->assertOk("Landing page {$route} for {$role} must not error.");
            $this->post(route('logout'));
        }
    }
}
