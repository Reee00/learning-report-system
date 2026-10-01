<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * UX untuk HTTP 419 "Page Expired" (overhaul navigasi & reliabilitas LRS).
 *
 * MASALAH YANG DIKUNCI DI SINI
 *
 * `TokenMismatchException` dilempar `VerifyCsrfToken`, yang berada di
 * middleware GROUP `web`. Middleware group berjalan SEBELUM middleware route
 * (`auth`, `role`). Akibatnya, POST dengan sesi yang sudah hilang gagal di
 * pemeriksaan CSRF lebih dahulu, dan — karena `bootstrap/app.php` dulu tidak
 * punya handler untuk exception ini — Laravel berhenti di halaman 419 bawaan:
 * "Page Expired" polos, tanpa jalan kembali ke login, tanpa isian yang
 * dikembalikan, dan tanpa penjelasan apa pun.
 *
 * KLASIFIKASI ROOT CAUSE (yang diuji satu per satu di bawah)
 *
 *   A. stale CSRF token  -> halaman dipulihkan dari bfcache browser, atau tab
 *      yang dibuka sebelum login ulang. Sesi hidup, token basi.
 *   B. expired session   -> SESSION_LIFETIME habis; user sudah tidak dikenali.
 *   F. session regeneration -> `LoginController` memanggil
 *      `session()->regenerate()` saat login sukses, sehingga token di tab lain
 *      menjadi basi. Secara gejala sama dengan A.
 *
 *   C (back/repost), D (cached HTML), E (AJAX tanpa CSRF), dan G (redirect)
 *   sudah diperiksa dan BUKAN penyebab di aplikasi ini — lihat catatan di
 *   masing-masing test.
 *
 * CATATAN PENTING SOAL CARA MENGUJI
 *
 * `VerifyCsrfToken::runningUnitTests()` melewati pemeriksaan CSRF selama
 * `app()->runningUnitTests()` benar, Jadi `$this->post()` biasa TIDAK PERNAH
 * menembakkan 419. Supaya pengujian benar-benar melewati middleware asli dan
 * exception asli (bukan memanggil renderer secara langsung), env aplikasi
 * diturunkan ke `local` selama request — itulah satu-satunya tombol yang
 * mematikan bypass tersebut. Nilai env dikembalikan di `finally`.
 *
 * Yang TIDAK dilakukan di sini, sesuai instruksi: tidak mematikan CSRF, tidak
 * menghapus `VerifyCsrfToken`, tidak menambahkan URI ke `$except`.
 */
class SessionExpiryUxTest extends TestCase
{
    use RefreshDatabase;

    // =====================================================================
    // Helper
    // =====================================================================

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name'     => 'User '.$email,
            'email'    => $email,
            'password' => Hash::make('password'),
            'role'     => $role,
        ]);
    }

    /**
     * Kirim request TANPA melewati bypass CSRF milik framework.
     *
     * `$this->app['env']` dipulihkan di `finally`, jadi test lain dalam satu
     * proses tidak terpengaruh.
     */
    private function callWithRealCsrf(callable $callback): mixed
    {
        $original = $this->app['env'];
        $this->app['env'] = 'local';

        try {
            return $callback();
        } finally {
            $this->app['env'] = $original;
        }
    }

    // =====================================================================
    // Root cause B — sesi benar-benar habis
    // =====================================================================

    public function test_guest_post_with_stale_token_lands_on_login_instead_of_page_expired(): void
    {
        // Kasus paling sering: halaman login dibiarkan terbuka melewati masa
        // berlaku sesi, lalu user menekan "Masuk". Referer-nya halaman login
        // itu sendiri.
        $response = $this->callWithRealCsrf(fn () => $this->from('/login')->post('/login', [
            'email'    => 'siapa.saja@test.test',
            'password' => 'apa-saja',
        ]));

        // Bukan halaman 419 polos: user dikirim ke login…
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');

        // …dan halaman ASAL dicatat sebagai tujuan sesudah login. Ini yang
        // mencegah "Back membuang user ke dashboard": begitu masuk lagi, ia
        // mendarat di halaman yang tadi sedang ia kerjakan.
        $this->assertSame(url('/login'), session('url.intended'));
    }

    public function test_a_guest_whose_session_expired_on_a_deep_page_returns_there_after_login(): void
    {
        // Sesi habis saat user berada di halaman dalam, bukan di dashboard.
        // `url.intended` harus menunjuk halaman ITU — bukan '/' dan bukan
        // dashboard — supaya Back sesudah login tidak membuang konteksnya.
        $this->callWithRealCsrf(fn () => $this->from(route('coach.reports.create'))
            ->post(route('coach.reports.store'), ['lesson_material' => 'Materi']));

        $this->assertSame(url(route('coach.reports.create')), session('url.intended'));
    }

    public function test_the_expired_session_message_is_actually_shown_on_the_login_page(): void
    {
        $this->callWithRealCsrf(fn () => $this->post('/login', [
            'email'    => 'siapa.saja@test.test',
            'password' => 'apa-saja',
        ]));

        // Flash-nya harus dirender; sebelumnya halaman login hanya membaca
        // `$errors`, sehingga pesan `error` tidak pernah terlihat.
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Sesi Anda telah berakhir', false);
    }

    public function test_the_rejected_action_is_never_performed(): void
    {
        $coach = $this->makeUser('coach.tolak@test.test', User::ROLE_COACH);

        $before = \App\Models\Report::count();

        $this->actingAs($coach);

        $response = $this->callWithRealCsrf(fn () => $this->from(route('coach.reports.create'))
            ->post(route('coach.reports.store'), [
                'lesson_material' => 'Materi yang tidak boleh tersimpan',
            ]));

        $response->assertRedirect(route('coach.reports.create'));

        // Inti keamanannya: token tidak cocok = aksi TIDAK dijalankan.
        $this->assertSame($before, \App\Models\Report::count());
        $this->assertDatabaseMissing('reports', ['lesson_material' => 'Materi yang tidak boleh tersimpan']);
    }

    // =====================================================================
    // Root cause A / F — sesi hidup, token basi
    // =====================================================================

    public function test_authenticated_post_with_stale_token_returns_to_the_form_with_input_intact(): void
    {
        $coach = $this->makeUser('coach.basi@test.test', User::ROLE_COACH);

        $this->actingAs($coach);

        $response = $this->callWithRealCsrf(fn () => $this->from(route('coach.reports.create'))
            ->post(route('coach.reports.store'), [
                'lesson_material' => 'Materi yang sudah diketik coach',
                'notes'           => 'Catatan yang sudah diketik coach',
            ]));

        // User TIDAK dipaksa login ulang — sesinya masih sah. Ia kembali ke
        // halaman form…
        $response->assertRedirect(route('coach.reports.create'));
        $response->assertSessionHas('error');
        $response->assertSessionHasNoErrors();

        // …bersama isian yang tadi sudah diketik, sehingga tidak ada
        // pekerjaan yang hilang.
        $response->assertSessionHasInput('lesson_material', 'Materi yang sudah diketik coach');
        $response->assertSessionHasInput('notes', 'Catatan yang sudah diketik coach');
    }

    public function test_secret_fields_are_never_flashed_back_into_the_page(): void
    {
        $user = $this->makeUser('rahasia@test.test', User::ROLE_RELATION);
        $this->actingAs($user);

        $response = $this->callWithRealCsrf(fn () => $this->from(route('account.edit'))
            ->patch(route('account.password.update'), [
                'current_password'      => 'password-lama-yang-asli',
                'password'              => 'password-baru-rahasia',
                'password_confirmation' => 'password-baru-rahasia',
            ]));

        $response->assertRedirect(route('account.edit'));

        // Nilai rahasia tidak boleh ikut dipantulkan kembali ke HTML.
        $this->assertNull(old('current_password'));
        $this->assertNull(old('password'));
        $this->assertNull(old('password_confirmation'));

        $html = $this->get(route('account.edit'))->assertOk()->getContent();
        $this->assertStringNotContainsString('password-baru-rahasia', $html);
        $this->assertStringNotContainsString('password-lama-yang-asli', $html);
    }

    public function test_the_csrf_token_field_itself_is_not_flashed_back(): void
    {
        $coach = $this->makeUser('token@test.test', User::ROLE_COACH);
        $this->actingAs($coach);

        $this->callWithRealCsrf(fn () => $this->from(route('coach.reports.create'))
            ->post(route('coach.reports.store'), [
                '_token'          => 'token-basi-yang-salah',
                'lesson_material' => 'Materi',
            ]));

        // Memantulkan `_token` lama hanya akan mengulang kegagalan yang sama.
        $this->assertNull(old('_token'));
    }

    public function test_a_stale_token_does_not_log_the_user_out(): void
    {
        $coach = $this->makeUser('tetap.masuk@test.test', User::ROLE_COACH);
        $this->actingAs($coach);

        $this->callWithRealCsrf(fn () => $this->from(route('coach.reports.create'))
            ->post(route('coach.reports.store'), ['lesson_material' => 'Materi']));

        // Root cause A/F bukan sesi yang hilang, jadi user tetap terautentikasi.
        $this->assertAuthenticatedAs($coach);
    }

    // =====================================================================
    // Respons JSON (AJAX / fetch)
    // =====================================================================

    public function test_json_requests_receive_a_419_json_body_not_an_html_page(): void
    {
        $coach = $this->makeUser('ajax@test.test', User::ROLE_COACH);
        $this->actingAs($coach);

        $response = $this->callWithRealCsrf(fn () => $this->postJson(
            route('coach.reports.store'),
            ['lesson_material' => 'Materi']
        ));

        $response->assertStatus(419);
        $response->assertJsonStructure(['message']);
        $this->assertStringContainsString('token keamanan', $response->json('message'));
    }

    // =====================================================================
    // Root cause E — TIDAK ADA fetch/AJAX yang lupa mengirim CSRF
    //
    // Diperiksa langsung ke sumbernya, bukan diasumsikan. Kalau suatu hari
    // ada `fetch()` baru yang menembak endpoint POST tanpa header CSRF, test
    // ini gagal dan menunjuk berkasnya.
    // =====================================================================

    public function test_every_mutating_fetch_in_the_views_sends_a_csrf_header(): void
    {
        $violations = [];

        foreach ($this->viewFiles() as $path) {
            $source = file_get_contents($path);

            if (! preg_match_all('/fetch\s*\(/', $source, $matches)) {
                continue;
            }

            // Hanya berkas yang juga memuat request non-GET yang perlu header.
            $hasMutatingCall = preg_match(
                '/method\s*:\s*[\'"](POST|PUT|PATCH|DELETE)[\'"]/i',
                $source
            ) === 1;

            if ($hasMutatingCall && ! str_contains($source, 'X-CSRF-TOKEN')) {
                $violations[] = $path;
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Berkas berikut memanggil fetch() non-GET tanpa header X-CSRF-TOKEN:\n".implode("\n", $violations)
        );
    }

    /**
     * @return array<int, string>
     */
    private function viewFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    // =====================================================================
    // Tidak ada workaround: CSRF tetap terpasang
    // =====================================================================

    public function test_csrf_middleware_is_still_registered_in_the_web_group(): void
    {
        $group = $this->app[\Illuminate\Contracts\Http\Kernel::class]->getMiddlewareGroups()['web'] ?? [];

        $this->assertContains(
            ValidateCsrfToken::class,
            $group,
            'VerifyCsrfToken tidak boleh dihapus dari group web sebagai workaround 419.'
        );
    }

    public function test_no_uri_was_added_to_the_csrf_except_list(): void
    {
        $middleware = $this->app->make(ValidateCsrfToken::class);

        $property = new \ReflectionProperty($middleware, 'except');
        $property->setAccessible(true);

        $this->assertSame(
            [],
            $property->getValue($middleware),
            'Daftar pengecualian CSRF harus tetap kosong — 419 diperbaiki lewat UX, bukan dengan melewati pemeriksaan.'
        );
    }

    // =====================================================================
    // Tujuan redirect sesudah sesi berakhir
    // =====================================================================

    public function test_every_role_has_a_reachable_home_route(): void
    {
        foreach (User::roleKeys() as $role) {
            $name = User::homeRouteName($role);

            $this->assertNotNull($name, "Role {$role} tidak punya route halaman awal.");
            $this->assertTrue(Route::has($name), "Route halaman awal '{$name}' untuk role {$role} tidak terdaftar.");
        }
    }

    public function test_no_role_falls_back_to_the_redirect_loop_root(): void
    {
        // '/' me-redirect ke login. Kalau fallback sesi berakhir menunjuk ke
        // sana, user yang MASIH login akan terjebak loop login <-> '/'.
        foreach (User::roleKeys() as $role) {
            $user = new User(['role' => $role]);

            $this->assertNotSame(
                url('/'),
                $user->homeUrl(),
                "Fallback role {$role} tidak boleh menunjuk ke '/'."
            );
        }
    }

    public function test_the_role_home_route_is_the_same_one_used_after_login(): void
    {
        // Satu definisi untuk dua jalur: redirect sesudah login dan fallback
        // sesi berakhir. Kalau keduanya berbeda, salah satunya pasti salah.
        $coach = $this->makeUser('samakan@test.test', User::ROLE_COACH);

        $this->post('/login', [
            'email'    => 'samakan@test.test',
            'password' => 'password',
        ])->assertRedirect(route('coach.reports.index'));

        $this->assertSame(route('coach.reports.index'), $coach->homeUrl());
    }
}
