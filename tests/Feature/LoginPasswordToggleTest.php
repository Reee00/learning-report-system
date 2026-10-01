<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * UX halaman Login: tombol tampil/sembunyikan password
 * (review meeting LRS 2026-10-01).
 *
 * Yang dikunci di sini:
 * - field password tetap `type="password"` sejak awal, sehingga karakter
 *   termasking sebelum user menyentuh apa pun;
 * - label memakai kata biasa "Password", tanpa placeholder unik/aneh;
 * - tombol mata terpasang pada field yang benar, punya label aksesibilitas,
 *   dan BUKAN tombol submit — sehingga tidak pernah mengganggu pengiriman form;
 * - `autocomplete="current-password"` dipertahankan agar password manager
 *   browser tetap bekerja;
 * - logika autentikasi tidak berubah: login benar berhasil, salah ditolak,
 *   dan throttle tetap berjalan.
 *
 * Perilaku klik (mengganti `type`) dijaga oleh skrip terpisah; di sini yang
 * diperiksa adalah kontrak markup + skripnya, termasuk jaminan bahwa nilai
 * field tidak pernah disentuh sehingga tidak hilang saat toggle.
 */
class LoginPasswordToggleTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $email, string $role = User::ROLE_COACH): User
    {
        return User::create([
            'name'     => 'User '.$email,
            'email'    => $email,
            'password' => Hash::make('password'),
            'role'     => $role,
        ]);
    }

    // =====================================================================
    // Markup
    // =====================================================================

    public function test_the_password_field_is_masked_by_default(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $response->assertSee('type="password"', false);
        $response->assertSee('id="loginPassword"', false);
        $response->assertSee('name="password"', false);
    }

    public function test_the_label_is_the_plain_word_password_without_an_odd_placeholder(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $response->assertSee('for="loginPassword"', false);
        $response->assertSee('>Password</label>', false);

        // Placeholder bulat-bulat lama dihapus — itu teks unik yang dimaksud.
        $response->assertDontSee('••••••••');
        $response->assertDontSee('placeholder="Password"', false);
    }

    public function test_the_eye_button_is_wired_to_the_password_field(): void
    {
        $response = $this->get(route('login'))->assertOk();

        // Menunjuk field yang benar, dan memberi tahu teknologi bantu field mana
        // yang dikendalikan.
        $response->assertSee('data-password-toggle="#loginPassword"', false);
        $response->assertSee('aria-controls="loginPassword"', false);
        $response->assertSee('aria-label="Tampilkan password"', false);
        $response->assertSee('aria-pressed="false"', false);

        // Ikon mata tertutup sebagai keadaan awal.
        $response->assertSee('bi bi-eye', false);
    }

    public function test_the_toggle_is_a_plain_button_and_never_submits_the_form(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $response->assertSee('type="button"', false);
        // Elemennya tombol asli, bukan <a>/<div> berperan tombol: hanya begitu
        // ia bisa dijangkau dan diaktifkan lewat keyboard tanpa skrip tambahan.
        $this->assertMatchesRegularExpression(
            '/<button[^>]*data-password-toggle="#loginPassword"/s',
            $response->getContent(),
            'Toggle password harus berupa elemen <button> asli.'
        );
        // Hanya tombol "Masuk" yang men-submit form login.
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'type="submit"'),
            'Halaman login harus punya tepat satu tombol submit.'
        );
    }

    public function test_the_field_keeps_browser_autofill_working(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $response->assertSee('autocomplete="current-password"', false);
        $response->assertSee('autocomplete="username"', false);
    }

    // =====================================================================
    // Skrip toggle
    // =====================================================================

    public function test_the_toggle_script_only_switches_the_field_type(): void
    {
        $content = $this->get(route('login'))->assertOk()->getContent();

        // Skripnya benar-benar dimuat…
        $this->assertStringContainsString('__passwordToggleBound', $content);
        $this->assertStringContainsString("input.type = revealed ? 'password' : 'text'", $content);
        // …dan menyediakan dua ikon: mata saat tersembunyi, mata-coret saat terlihat.
        $this->assertStringContainsString("ICON_HIDDEN = 'bi-eye'", $content);
        $this->assertStringContainsString("ICON_VISIBLE = 'bi-eye-off'", $content);

        // Nilai field TIDAK pernah ditulis ulang — inilah sebabnya password
        // tidak hilang saat toggle.
        $this->assertStringNotContainsString('input.value', $content);

        // Bisa dipasang lebih dari sekali tanpa menggandakan listener.
        $this->assertStringContainsString('if (window.__passwordToggleBound)', $content);
    }

    public function test_the_toggle_script_is_loaded_only_once(): void
    {
        $content = $this->get(route('login'))->assertOk()->getContent();

        $this->assertSame(
            1,
            substr_count($content, 'window.__passwordToggleBound = true;'),
            'Skrip toggle tidak boleh dimuat dua kali.'
        );
    }

    public function test_the_account_settings_page_reuses_the_same_toggle_script(): void
    {
        $user = $this->makeUser('toggle.akun@test.test', User::ROLE_RELATION);

        $content = $this->actingAs($user)->get(route('account.edit'))->assertOk()->getContent();

        // Pola UI yang sama dipakai ulang, bukan ditulis ulang.
        $this->assertStringContainsString('data-password-toggle="#currentPassword"', $content);
        $this->assertStringContainsString('data-password-toggle="#newPassword"', $content);
        $this->assertSame(1, substr_count($content, 'window.__passwordToggleBound = true;'));
    }

    // =====================================================================
    // Autentikasi tidak berubah
    // =====================================================================

    public function test_login_still_succeeds_with_correct_credentials(): void
    {
        $this->makeUser('masuk@test.test');

        $this->post('/login', [
            'email'    => 'masuk@test.test',
            'password' => 'password',
        ])->assertRedirectToRoute('coach.reports.index');

        $this->assertAuthenticated();
    }

    public function test_login_still_fails_with_a_wrong_password(): void
    {
        $this->makeUser('salah@test.test');

        $this->post('/login', [
            'email'    => 'salah@test.test',
            'password' => 'password-salah',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_login_throttle_still_applies(): void
    {
        $this->makeUser('throttle@test.test');

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', [
                'email'    => 'throttle@test.test',
                'password' => 'password-salah',
            ]);
        }

        // Percobaan ke-6 diblokir meski kredensialnya benar.
        $this->post('/login', [
            'email'    => 'throttle@test.test',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        // Setelah jendela throttle lewat, login kembali normal.
        RateLimiter::clear('throttle@test.test|127.0.0.1');

        $this->post('/login', [
            'email'    => 'throttle@test.test',
            'password' => 'password',
        ])->assertRedirectToRoute('coach.reports.index');
    }

    public function test_the_csrf_token_is_still_present_in_the_login_form(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="_token"', false);
    }
}
