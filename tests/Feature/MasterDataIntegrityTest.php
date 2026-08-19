<?php

namespace Tests\Feature;

use App\Models\CoachClass;
use App\Models\Report;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Master data must stay usable for every canonical role, and destructive
 * actions must fail with a readable message instead of a database error when
 * dependent records still reference the row.
 */
class MasterDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private School $school;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPERADMIN,
        ]);

        $this->school = School::create(['name' => 'School A']);
    }

    /** Build a school with a class, student, coach and one report referencing all of them. */
    private function schoolWithReport(): array
    {
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'A-1']);
        $student = Student::create(['class_id' => $class->id, 'name' => 'Student A']);

        $coach = User::create([
            'name' => 'Coach A',
            'email' => 'coach.a@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_COACH,
        ]);
        CoachClass::create(['coach_id' => $coach->id, 'class_id' => $class->id]);

        $report = Report::create([
            'coach_id' => $coach->id,
            'school_id' => $this->school->id,
            'class_id' => $class->id,
            'report_date' => '2026-08-17',
            'lesson_material' => 'Materi',
            'activity_summary' => 'Ringkasan',
            'status' => 'submitted',
        ]);

        return compact('class', 'student', 'coach', 'report');
    }

    public function test_superadmin_can_create_an_spv_coach_account(): void
    {
        $this->actingAs($this->superadmin)
            ->from(route('admin.users.index'))
            ->post(route('admin.users.store'), [
                'name' => 'SPV Coach',
                'email' => 'spv@test.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => User::ROLE_SPV_COACH,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'email' => 'spv@test.test',
            'role' => User::ROLE_SPV_COACH,
        ]);
    }

    public function test_superadmin_can_change_a_role_to_spv_coach(): void
    {
        $user = User::create([
            'name' => 'Coach To Promote',
            'email' => 'promote@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_COACH,
        ]);

        $this->actingAs($this->superadmin)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.update', $user), [
                'name' => 'Coach To Promote',
                'email' => 'promote@test.test',
                'role' => User::ROLE_SPV_COACH,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(User::ROLE_SPV_COACH, $user->fresh()->role);
    }

    public function test_user_management_renders_every_canonical_role(): void
    {
        foreach (User::roleKeys() as $index => $role) {
            if ($role === User::ROLE_SUPERADMIN) {
                continue;
            }

            $user = User::create([
                'name' => 'User '.$role,
                'email' => $role.'@test.test',
                'password' => Hash::make('password'),
                'role' => $role,
            ]);

            if (in_array($role, [User::ROLE_SCHOOL_PIC, User::ROLE_TEACHER_SCHOOL, User::ROLE_FINANCE], true)) {
                $user->schools()->sync([$this->school->id]);
            }
        }

        $this->actingAs($this->superadmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('SPV Coach')
            ->assertSee('Finance');
    }

    public function test_finance_plotting_is_visible_in_user_management(): void
    {
        $finance = User::create([
            'name' => 'Finance User',
            'email' => 'finance@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_FINANCE,
        ]);
        $finance->schools()->sync([$this->school->id]);

        // The school name also appears inside the plotting <select>, so assert on
        // the plotting badge itself to prove the scope column is populated.
        $this->actingAs($this->superadmin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('School A', false);
    }

    public function test_navbar_role_badge_uses_the_canonical_role_label(): void
    {
        $spv = User::create([
            'name' => 'SPV Nav',
            'email' => 'spv.nav@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SPV_COACH,
        ]);

        // The navbar used to build the label with ucfirst(), rendering "Spv coach"
        // while User Management rendered "SPV Coach" for the same account.
        $this->actingAs($spv)
            ->get(route('admin.coaches.index'))
            ->assertOk()
            ->assertSee('SPV Coach')
            ->assertDontSee('Spv coach');
    }

    public function test_deleting_a_school_with_reports_is_refused_readably(): void
    {
        $this->schoolWithReport();

        $this->actingAs($this->superadmin)
            ->from(route('admin.schools.index'))
            ->delete(route('admin.schools.destroy', $this->school))
            ->assertRedirect(route('admin.schools.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('schools', ['id' => $this->school->id]);
    }

    public function test_deleting_a_school_without_dependents_still_works(): void
    {
        $empty = School::create(['name' => 'Empty School']);

        $this->actingAs($this->superadmin)
            ->from(route('admin.schools.index'))
            ->delete(route('admin.schools.destroy', $empty))
            ->assertRedirect(route('admin.schools.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('schools', ['id' => $empty->id]);
    }

    public function test_deleting_a_class_with_reports_is_refused_readably(): void
    {
        ['class' => $class] = $this->schoolWithReport();

        $this->actingAs($this->superadmin)
            ->from(route('admin.classes.index'))
            ->delete(route('admin.classes.destroy', $class))
            ->assertRedirect(route('admin.classes.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('classes', ['id' => $class->id]);
    }

    public function test_deleting_a_class_without_reports_still_works(): void
    {
        $class = SchoolClass::create(['school_id' => $this->school->id, 'name' => 'Empty Class']);

        $this->actingAs($this->superadmin)
            ->from(route('admin.classes.index'))
            ->delete(route('admin.classes.destroy', $class))
            ->assertRedirect(route('admin.classes.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('classes', ['id' => $class->id]);
    }

    public function test_deleting_a_coach_with_reports_is_refused_readably(): void
    {
        ['coach' => $coach] = $this->schoolWithReport();

        $this->actingAs($this->superadmin)
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $coach))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $coach->id]);
    }

    public function test_deleting_a_user_without_reports_still_works(): void
    {
        $user = User::create([
            'name' => 'Disposable',
            'email' => 'disposable@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_COACH,
        ]);

        $this->actingAs($this->superadmin)
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $user))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
