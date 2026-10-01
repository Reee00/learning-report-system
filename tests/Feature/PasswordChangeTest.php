<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Ganti password sendiri lewat Pengaturan Akun
 * (review meeting LRS 2026-10-01).
 *
 * Aturan final yang dikunci di sini:
 * - SETIAP role boleh mengganti password akunnya SENDIRI;
 * - password SAAT INI wajib disertakan dan diperiksa dengan hashing yang sama
 *   dengan login — bukan perbandingan teks apa pun;
 * - password baru memakai aturan yang sudah berlaku (min. 6 karakter +
 *   konfirmasi) dan disimpan dengan `Hash::make`;
 * - password lama langsung tidak berlaku setelah perubahan berhasil;
 * - password (lama, baru, maupun konfirmasi) TIDAK pernah masuk activity log.
 */
class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role, string $slug, string $password = 'password-lama'): User
    {
        return User::create([
            'name'     => ucwords(str_replace('.', '_', $slug)),
            'email'    => $slug.'@test.test',
            'password' => Hash::make($password),
            'role'     => $role,
        ]);
    }

    private function changePassword(User $user, array $payload)
    {
        return $this->actingAs($user)->patch(route('account.password.update'), $payload);
    }

    // =====================================================================
    // Semua role
    // =====================================================================

    public function test_every_role_can_change_its_own_password(): void
    {
        foreach (User::roleKeys() as $role) {
            $user = $this->makeUser($role, 'sandi.'.$role);

            $this->changePassword($user, [
                'current_password'      => 'password-lama',
                'password'              => 'password-baru-'.$role,
                'password_confirmation' => 'password-baru-'.$role,
            ])->assertRedirect()->assertSessionHas('success');

            $user->refresh();
            $this->assertTrue(
                Hash::check('password-baru-'.$role, $user->password),
                "Role {$role} gagal mengganti passwordnya sendiri."
            );
            $this->assertFalse(Hash::check('password-lama', $user->password));
        }
    }

    // =====================================================================
    // Password lama wajib benar
    // =====================================================================

    public function test_the_current_password_is_required(): void
    {
        $user = $this->makeUser(User::ROLE_COACH, 'coach.kosong');

        $this->changePassword($user, [
            'password'              => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertSessionHasErrors('current_password', null, 'password');

        $this->assertTrue(Hash::check('password-lama', $user->refresh()->password));
    }

    public function test_a_wrong_current_password_is_rejected_and_changes_nothing(): void
    {
        $user = $this->makeUser(User::ROLE_FINANCE, 'finance.salah');

        $this->changePassword($user, [
            'current_password'      => 'bukan-password-saya',
            'password'              => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertSessionHasErrors('current_password', null, 'password');

        // Password lama tetap berlaku; yang baru tidak pernah tersimpan.
        $user->refresh();
        $this->assertTrue(Hash::check('password-lama', $user->password));
        $this->assertFalse(Hash::check('password-baru', $user->password));
    }

    public function test_the_old_password_stops_working_and_the_new_one_takes_over(): void
    {
        $user = $this->makeUser(User::ROLE_RELATION, 'relation.ganti');

        $this->changePassword($user, [
            'current_password'      => 'password-lama',
            'password'              => 'password-baru-sekali',
            'password_confirmation' => 'password-baru-sekali',
        ])->assertSessionHas('success');

        // Login dengan password LAMA harus gagal…
        $this->post('/login', [
            'email'    => 'relation.ganti@test.test',
            'password' => 'password-lama',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password-lama', $user->refresh()->password) === false);

        // …dan password BARU harus berhasil.
        $this->post('/login', [
            'email'    => 'relation.ganti@test.test',
            'password' => 'password-baru-sekali',
        ])->assertRedirectToRoute('admin.dashboard');
    }

    // =====================================================================
    // Validasi password baru
    // =====================================================================

    public function test_a_mismatched_confirmation_is_rejected(): void
    {
        $user = $this->makeUser(User::ROLE_SPV_COACH, 'spv.konfirmasi');

        $this->changePassword($user, [
            'current_password'      => 'password-lama',
            'password'              => 'password-baru',
            'password_confirmation' => 'password-beda',
        ])->assertSessionHasErrors('password', null, 'password');

        $this->assertTrue(Hash::check('password-lama', $user->refresh()->password));
    }

    public function test_a_short_new_password_is_rejected(): void
    {
        $user = $this->makeUser(User::ROLE_SCHOOL_PIC, 'pic.pendek');

        $this->changePassword($user, [
            'current_password'      => 'password-lama',
            'password'              => '12345',
            'password_confirmation' => '12345',
        ])->assertSessionHasErrors('password', null, 'password');

        $this->assertTrue(Hash::check('password-lama', $user->refresh()->password));
    }

    // =====================================================================
    // Target tidak dapat dialihkan, dan hanya pemilik sesi yang tersentuh
    // =====================================================================

    public function test_the_password_form_cannot_be_pointed_at_another_user(): void
    {
        $korban = $this->makeUser(User::ROLE_COACH, 'coach.korban');
        $penyerang = $this->makeUser(User::ROLE_TEACHER_SCHOOL, 'teacher.penyerang');

        // Id user lain di URL dan body — keduanya diabaikan; yang berubah
        // tetap akun pemilik sesi.
        $this->actingAs($penyerang)
            ->patch(route('account.password.update').'?user_id='.$korban->id, [
                'user_id'               => $korban->id,
                'id'                    => $korban->id,
                'current_password'      => 'password-lama',
                'password'              => 'password-baru',
                'password_confirmation' => 'password-baru',
            ])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('password-baru', $penyerang->refresh()->password));
        // Akun korban sama sekali tidak tersentuh.
        $this->assertTrue(Hash::check('password-lama', $korban->refresh()->password));
    }

    public function test_a_guest_cannot_reach_the_password_form(): void
    {
        $this->patch(route('account.password.update'), [
            'current_password'      => 'password-lama',
            'password'              => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertRedirect(route('login'));
    }

    // =====================================================================
    // Keamanan penyimpanan & pencatatan
    // =====================================================================

    public function test_the_new_password_is_stored_hashed_not_in_plain_text(): void
    {
        $user = $this->makeUser(User::ROLE_COACH, 'coach.hash');

        $this->changePassword($user, [
            'current_password'      => 'password-lama',
            'password'              => 'rahasia-sekali-123',
            'password_confirmation' => 'rahasia-sekali-123',
        ])->assertSessionHas('success');

        $stored = $user->refresh()->password;
        $this->assertNotSame('rahasia-sekali-123', $stored);
        $this->assertTrue(Hash::check('rahasia-sekali-123', $stored));
        // Hash password tidak pernah ikut terserialisasi keluar model.
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_no_password_value_ever_reaches_the_activity_log(): void
    {
        $user = $this->makeUser(User::ROLE_RELATION, 'relation.log');

        $this->changePassword($user, [
            'current_password'      => 'password-lama',
            'password'              => 'rahasia-baru-456',
            'password_confirmation' => 'rahasia-baru-456',
        ])->assertSessionHas('success');

        // Peristiwanya tercatat…
        $this->assertSame(1, ActivityLog::where('action', 'user.password_changed')->count());

        // …tetapi tidak satu pun nilai password muncul di kolom mana pun.
        foreach (ActivityLog::all() as $log) {
            $dump = json_encode($log->toArray());

            foreach (['password-lama', 'rahasia-baru-456'] as $secret) {
                $this->assertStringNotContainsString(
                    $secret,
                    (string) $dump,
                    'Nilai password tidak boleh muncul di activity log.'
                );
            }
            $this->assertStringNotContainsString('$2y$', (string) $dump, 'Hash password tidak boleh dicatat.');
        }
    }

    public function test_changing_the_password_does_not_touch_the_rest_of_the_account(): void
    {
        $user = $this->makeUser(User::ROLE_COACH, 'coach.utuh');
        $user->update(['whatsapp' => '081200000777']);

        $this->changePassword($user, [
            'current_password'      => 'password-lama',
            'password'              => 'password-baru',
            'password_confirmation' => 'password-baru',
        ])->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('081200000777', $user->whatsapp);
        $this->assertSame(User::ROLE_COACH, $user->role);
        $this->assertSame('coach.utuh@test.test', $user->email);
    }
}
