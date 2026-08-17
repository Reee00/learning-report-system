<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_relation_can_login_through_the_compatibility_dashboard(): void
    {
        User::create([
            'name' => 'Relation Test',
            'email' => 'relation@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_RELATION,
        ]);

        $response = $this->post('/login', [
            'email' => 'relation@test.test',
            'password' => 'password',
        ]);

        $response->assertRedirectToRoute('admin.schools.index');
    }

    public function test_superadmin_can_login_through_the_compatibility_dashboard(): void
    {
        User::create([
            'name' => 'SuperAdmin Test',
            'email' => 'superadmin@test.test',
            'password' => Hash::make('password'),
            'role' => User::ROLE_SUPERADMIN,
        ]);

        $response = $this->post('/login', [
            'email' => 'superadmin@test.test',
            'password' => 'password',
        ]);

        $response->assertRedirectToRoute('admin.dashboard');
    }
}
