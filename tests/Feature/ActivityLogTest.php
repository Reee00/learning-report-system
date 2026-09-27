<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Activity log (audit keamanan 2026-09-11).
 *
 * Verifies:
 * - Access console is SuperAdmin-only (relation/PIC get 403).
 * - Login success, master data creation, and user password reset are logged.
 * - Secrets (password fields, tokens) are never stored in metadata.
 * - Filters (action, role, keyword) narrow the result set.
 * - Purge command deletes logs older than the retention window.
 */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private User $relation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::create([
            'name' => 'Super Admin', 'email' => 'superadmin@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_SUPERADMIN,
        ]);
        $this->relation = User::create([
            'name' => 'Relation', 'email' => 'relation@test.test',
            'password' => Hash::make('password'), 'role' => User::ROLE_RELATION,
        ]);
    }

    public function test_activity_log_console_is_superadmin_only(): void
    {
        $this->actingAs($this->superadmin)
            ->get(route('admin.activity-logs.index'))
            ->assertOk();

        $this->actingAs($this->relation)
            ->get(route('admin.activity-logs.index'))
            ->assertForbidden();

        $log = ActivityLog::create([
            'user_id' => $this->superadmin->id,
            'user_role' => User::ROLE_SUPERADMIN,
            'action' => 'auth.login',
            'description' => 'x',
        ]);

        $this->actingAs($this->relation)
            ->get(route('admin.activity-logs.show', $log))
            ->assertForbidden();
    }

    public function test_successful_login_is_logged(): void
    {
        $this->post('/login', [
            'email'    => 'superadmin@test.test',
            'password' => 'password',
        ])->assertRedirectToRoute('admin.dashboard');

        $log = ActivityLog::where('action', 'auth.login')->first();
        $this->assertNotNull($log, 'Successful login must be logged.');
        $this->assertSame($this->superadmin->id, $log->user_id);
        $this->assertSame(User::ROLE_SUPERADMIN, $log->user_role);
        $this->assertNotNull($log->ip_address);
    }

    public function test_master_data_creation_is_logged(): void
    {
        $this->actingAs($this->superadmin)
            ->post(route('admin.schools.store'), [
                'name' => 'Sekolah Audit',
            ])
            ->assertSessionHas('success');

        $log = ActivityLog::where('action', 'school.created')->first();
        $this->assertNotNull($log, 'School creation must be logged.');
        $this->assertSame('school', $log->subject_type);
        $this->assertStringContainsString('Sekolah Audit', $log->description);
    }

    public function test_password_reset_is_logged_without_storing_the_password(): void
    {
        $target = User::create([
            'name' => 'Target', 'email' => 'target@test.test',
            'password' => Hash::make('old-password'), 'role' => User::ROLE_COACH,
        ]);

        $this->actingAs($this->superadmin)
            ->patch(route('admin.users.reset-password', $target), [
                'password' => 'new-secret-password',
                'password_confirmation' => 'new-secret-password',
            ])
            ->assertSessionHas('success');

        $log = ActivityLog::where('action', 'user.password_reset')->first();
        $this->assertNotNull($log, 'Password reset must be logged.');

        // The stored row must never contain the plaintext password.
        $this->assertStringNotContainsString(
            'new-secret-password',
            json_encode($log->metadata) . $log->description,
        );
    }

    public function test_scrubber_drops_secret_keys_from_metadata(): void
    {
        app(\App\Services\ActivityLogService::class)->log(
            $this->superadmin,
            'test.action',
            metadata: [
                'password' => 'leak-me',
                'token' => 'leak-me-too',
                'safe' => 'keep-me',
                'nested' => ['api_token' => 'leak', 'ok' => 1],
            ],
        );

        $json = ActivityLog::query()->latest('id')->first()->metadata;

        $this->assertSame('keep-me', $json['safe']);
        $this->assertSame(1, $json['nested']['ok']);
        $this->assertArrayNotHasKey('password', $json);
        $this->assertArrayNotHasKey('token', $json);
        $this->assertArrayNotHasKey('api_token', $json['nested']);
    }

    public function test_index_filters_by_action_and_keyword(): void
    {
        ActivityLog::create([
            'user_id' => $this->superadmin->id, 'user_role' => User::ROLE_SUPERADMIN,
            'action' => 'report.approved', 'description' => 'Laporan #1 disetujui',
        ]);
        ActivityLog::create([
            'user_id' => $this->relation->id, 'user_role' => User::ROLE_RELATION,
            'action' => 'school.created', 'description' => 'Sekolah X dibuat',
        ]);

        $this->actingAs($this->superadmin)
            ->get(route('admin.activity-logs.index', ['action' => 'school']))
            ->assertOk()
            ->assertSee('Sekolah X dibuat')
            ->assertDontSee('Laporan #1 disetujui');

        $this->actingAs($this->superadmin)
            ->get(route('admin.activity-logs.index', ['search' => 'disetujui']))
            ->assertOk()
            ->assertSee('Laporan #1 disetujui')
            ->assertDontSee('Sekolah X dibuat');
    }

    public function test_purge_command_deletes_logs_older_than_retention(): void
    {
        $old = ActivityLog::create([
            'user_id' => $this->superadmin->id, 'user_role' => User::ROLE_SUPERADMIN,
            'action' => 'old.action', 'description' => 'old',
        ]);
        $old->created_at = now()->subDays(8);
        $old->save();

        ActivityLog::create([
            'user_id' => $this->superadmin->id, 'user_role' => User::ROLE_SUPERADMIN,
            'action' => 'fresh.action', 'description' => 'fresh',
        ]);

        $this->artisan('activity-logs:purge');

        $this->assertDatabaseMissing('activity_logs', ['action' => 'old.action']);
        $this->assertDatabaseHas('activity_logs', ['action' => 'fresh.action']);
    }
}
