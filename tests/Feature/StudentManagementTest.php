<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_relation_can_create_a_student_for_a_class_school(): void
    {
        [$school, $class] = $this->makeClass();

        $this->actingAs($this->makeUser(User::ROLE_RELATION))
            ->post(route('students.store', $class), ['name' => 'Andi Pratama'])
            ->assertRedirect();

        $student = Student::where('name', 'Andi Pratama')->firstOrFail();

        $this->assertEquals($class->id, $student->class_id);
        $this->assertEquals($school->id, $student->schoolClass->school_id);
        $this->assertTrue($school->students()->whereKey($student->id)->exists());
    }

    public function test_student_name_is_required(): void
    {
        [, $class] = $this->makeClass();

        $this->actingAs($this->makeUser(User::ROLE_RELATION))
            ->from(route('students.show', $class))
            ->post(route('students.store', $class), ['name' => ''])
            ->assertRedirect(route('students.show', $class))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('students', 0);
    }

    public function test_user_without_student_permission_is_rejected(): void
    {
        [, $class] = $this->makeClass();

        $finance = $this->makeUser(User::ROLE_FINANCE);

        $this->actingAs($finance)
            ->get(route('students.show', $class))
            ->assertForbidden();

        $this->actingAs($finance)
            ->post(route('students.store', $class), ['name' => 'Unauthorized Student'])
            ->assertForbidden();

        $this->assertDatabaseCount('students', 0);
    }

    public function test_coach_without_class_assignment_is_rejected(): void
    {
        [, $class] = $this->makeClass();

        $this->actingAs($this->makeUser(User::ROLE_COACH))
            ->get(route('students.show', $class))
            ->assertForbidden();
    }

    private function makeClass(): array
    {
        $school = School::create(['name' => 'SD Student Test']);
        $class = SchoolClass::create([
            'school_id' => $school->id,
            'name' => 'Kelas Student Test',
        ]);

        return [$school, $class];
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' Student Test',
            'email' => $role.'-student@test.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
