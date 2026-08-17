<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SchoolManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_relation_can_open_school_management(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_RELATION))
            ->get('/admin/schools')
            ->assertOk();
    }

    public function test_relation_can_create_a_school(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_RELATION))
            ->post('/admin/schools', [
                'name' => 'SD Merdeka',
                'address' => 'Jl. Merdeka No. 1',
                'pic_name' => 'PIC Test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('schools', [
            'name' => 'SD Merdeka',
            'address' => 'Jl. Merdeka No. 1',
            'pic_name' => 'PIC Test',
        ]);
    }

    public function test_school_name_is_required(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_RELATION))
            ->from('/admin/schools')
            ->post('/admin/schools', ['name' => ''])
            ->assertRedirect('/admin/schools')
            ->assertSessionHasErrors('name');
    }

    public function test_superadmin_can_open_school_management(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_SUPERADMIN))
            ->get('/admin/schools')
            ->assertOk();
    }

    public function test_coach_cannot_open_school_management(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_COACH))
            ->get('/admin/schools')
            ->assertForbidden();
    }

    public function test_relation_can_update_and_delete_school(): void
    {
        $school = School::create(['name' => 'SD Existing']);
        $relation = $this->makeUser(User::ROLE_RELATION);

        $this->actingAs($relation)
            ->put('/admin/schools/'.$school->id, ['name' => 'SD Updated'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('schools', ['id' => $school->id, 'name' => 'SD Updated']);

        $this->actingAs($relation)
            ->delete('/admin/schools/'.$school->id)
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('schools', ['id' => $school->id]);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' Test',
            'email' => $role.'@test.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
