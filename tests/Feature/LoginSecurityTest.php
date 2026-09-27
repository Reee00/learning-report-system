<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Hardening login (audit keamanan 2026-09-11).
 *
 * Verifies:
 * - Generic error for wrong email AND wrong password (anti enumeration).
 * - Progressive throttle per email+IP after 5 failed attempts.
 * - Correct credentials are also blocked while throttled.
 * - Throttle clears after successful login (via RateLimiter::clear).
 * - Other accounts from the same IP are NOT throttled (key is email|ip).
 * - Session ID is regenerated after successful login (anti fixation).
 */
class LoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, string $role = User::ROLE_COACH): User
    {
        return User::create([
            'name'     => 'User ' . $email,
            'email'    => $email,
            'password' => Hash::make('password'),
            'role'     => $role,
        ]);
    }

    public function test_wrong_password_and_wrong_email_show_identical_generic_error(): void
    {
        $this->makeUser('real@test.test');

        $wrongPassword = $this->post('/login', [
            'email'    => 'real@test.test',
            'password' => 'wrong-password',
        ]);
        $wrongPasswordMessage = session('errors')->get('email')[0];

        $wrongEmail = $this->post('/login', [
            'email'    => 'nonexistent@test.test',
            'password' => 'wrong-password',
        ]);
        $wrongEmailMessage = session('errors')->get('email')[0];

        // Pesan identik — penebak tidak bisa membedakan email terdaftar.
        $this->assertSame($wrongPasswordMessage, $wrongEmailMessage);
        $this->assertSame('Email atau password salah.', $wrongPasswordMessage);
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $this->makeUser('victim@test.test');

        foreach (range(1, 5) as $i) {
            $this->post('/login', [
                'email'    => 'victim@test.test',
                'password' => 'wrong-password',
            ]);
        }

        // Attempt 6 — even with CORRECT credentials the request is blocked.
        $response = $this->post('/login', [
            'email'    => 'victim@test.test',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $errors = session('errors')->get('email');
        // Pesan throttle menampilkan sisa detik (trans('auth.throttle')).
        $this->assertStringContainsString('Too many login attempts', $errors[0]);
    }

    public function test_throttle_does_not_affect_other_accounts_from_same_ip(): void
    {
        $this->makeUser('victim@test.test');
        $this->makeUser('other@test.test', User::ROLE_RELATION);

        foreach (range(1, 6) as $i) {
            $this->post('/login', [
                'email'    => 'victim@test.test',
                'password' => 'wrong-password',
            ]);
        }

        // Different account from the same IP logs in fine — the throttle key
        // is email|ip, not ip alone.
        $this->post('/login', [
            'email'    => 'other@test.test',
            'password' => 'password',
        ])->assertRedirectToRoute('admin.dashboard');
    }

    public function test_login_succeeds_after_throttle_is_cleared(): void
    {
        $this->makeUser('victim@test.test');

        foreach (range(1, 6) as $i) {
            $this->post('/login', [
                'email'    => 'victim@test.test',
                'password' => 'wrong-password',
            ]);
        }

        // Simulate the decay window elapsing (progressive decay is time-based).
        RateLimiter::clear('victim@test.test|127.0.0.1');

        $this->post('/login', [
            'email'    => 'victim@test.test',
            'password' => 'password',
        ])->assertRedirectToRoute('coach.reports.index');
    }

    public function test_session_id_is_regenerated_on_successful_login(): void
    {
        $this->makeUser('session@test.test');

        $session = $this->app['session'];
        $this->session(['marker' => 'before-login']);
        $before = $session->getId();

        $this->post('/login', [
            'email'    => 'session@test.test',
            'password' => 'password',
        ])->assertRedirectToRoute('coach.reports.index');

        $this->assertNotEquals($before, $session->getId(), 'Session ID must change on login (anti session fixation).');
    }
}
