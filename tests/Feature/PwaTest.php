<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * PWA Phase 1 (2026-09-28) — manifest, service worker, offline fallback, dan
 * perilaku installable pada app shell.
 *
 * Fokus pengujian adalah KEAMANAN, bukan sekadar kelengkapan berkas:
 * service worker LRS tidak boleh pernah menyimpan halaman terautentikasi,
 * media privat (/storage), atau balasan ber-sesi. Karena itu beberapa
 * pengujian membaca isi public/sw.js dan memastikan strateginya tetap
 * allowlist — bukan denylist — dan bahwa navigasi selalu network-only.
 *
 * Berkas PWA dilayani langsung oleh web server dari public/ (bukan lewat
 * router Laravel), jadi yang diperiksa adalah berkas di disk; integrasi ke
 * halaman diperiksa lewat HTTP pada layout app shell dan halaman login.
 */
class PwaTest extends TestCase
{
    use RefreshDatabase;

    private const MANIFEST_PATH = 'manifest.json';
    private const SERVICE_WORKER_PATH = 'sw.js';
    private const OFFLINE_PATH = 'offline.html';

    /** Path rute aplikasi terproteksi yang tidak boleh muncul di daftar precache. */
    private const PROTECTED_ROUTE_PREFIXES = [
        '/admin', '/coach', '/attendance', '/classes', '/students', '/pic',
        '/storage', '/api', '/reports', '/schedules', '/notifications',
        '/download',
    ];

    // =========================================================
    // 1. Manifest
    // =========================================================

    public function test_manifest_is_present_and_valid_json(): void
    {
        $manifest = $this->readPublicJson(self::MANIFEST_PATH);

        $this->assertSame('Learning Report System', $manifest['name']);
        $this->assertSame('LRS', $manifest['short_name']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('id', $manifest['lang']);
        $this->assertSame('#4f46e5', $manifest['theme_color']);
        $this->assertSame('#f4f7f9', $manifest['background_color']);
    }

    /**
     * start_url harus berada di dalam scope, dan scope harus root supaya
     * seluruh rute aplikasi (yang semuanya di bawah /) tercakup PWA.
     */
    public function test_manifest_scope_covers_the_whole_application(): void
    {
        $manifest = $this->readPublicJson(self::MANIFEST_PATH);

        $this->assertStringStartsWith('/', $manifest['start_url']);
        $this->assertStringStartsWith($manifest['scope'], $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
    }

    public function test_manifest_exposes_192_and_512_icons_including_maskable(): void
    {
        $manifest = $this->readPublicJson(self::MANIFEST_PATH);
        $this->assertNotEmpty($manifest['icons']);

        $byPurpose = [];
        foreach ($manifest['icons'] as $icon) {
            $this->assertArrayHasKey('src', $icon);
            $this->assertArrayHasKey('sizes', $icon);
            // Ikon harus same-origin: path root-relative, bukan URL absolut
            // ke host lain (yang bisa membuat manifest menunjuk ke luar aplikasi).
            $this->assertStringStartsWith('/', $icon['src']);
            $this->assertStringNotContainsString('//', $icon['src']);
            $byPurpose[$icon['purpose'] ?? 'any'][] = $icon['sizes'];
        }

        $this->assertContains('192x192', $byPurpose['any'] ?? []);
        $this->assertContains('512x512', $byPurpose['any'] ?? []);
        $this->assertContains('192x192', $byPurpose['maskable'] ?? []);
        $this->assertContains('512x512', $byPurpose['maskable'] ?? []);
    }

    /**
     * Setiap ikon yang didaftarkan harus benar-benar ada, berupa PNG asli, dan
     * berdimensi sama dengan yang dijanjikan manifest — manifest yang
     * menjanjikan ikon tidak ada membuat Chrome menolak install.
     */
    public function test_every_declared_icon_exists_with_the_declared_dimensions(): void
    {
        $manifest = $this->readPublicJson(self::MANIFEST_PATH);

        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($path, "Ikon manifest tidak ditemukan: {$icon['src']}");

            [$width, $height] = $this->pngDimensions($path);
            $this->assertSame($icon['sizes'], "{$width}x{$height}", "Ukuran ikon tidak cocok: {$icon['src']}");
        }
    }

    public function test_apple_touch_icon_is_available_for_ios(): void
    {
        $path = public_path('icons/apple-touch-icon.png');
        $this->assertFileExists($path);

        [$width, $height] = $this->pngDimensions($path);
        $this->assertSame(180, $width);
        $this->assertSame(180, $height);
    }

    // =========================================================
    // 2. Service worker — strategi cache
    // =========================================================

    public function test_service_worker_is_served_from_the_application_root(): void
    {
        // Scope service worker dibatasi direktori tempat berkasnya disajikan.
        // Di public/ berarti scope '/', sehingga seluruh aplikasi tercakup tanpa
        // perlu header Service-Worker-Allowed.
        $this->assertFileExists(public_path(self::SERVICE_WORKER_PATH));
    }

    public function test_service_worker_never_serves_authenticated_pages_from_cache(): void
    {
        $sw = $this->withoutJsComments(file_get_contents(public_path(self::SERVICE_WORKER_PATH)));

        // Navigasi (HTML) harus punya jalur khusus dan tidak pernah menyentuh cache.
        $this->assertStringContainsString("request.mode === 'navigate'", $sw);
        $this->assertStringContainsString('handleNavigation', $sw);
        // Hanya GET yang ditangani; POST/PUT/DELETE (form & CSRF) tidak disentuh.
        $this->assertStringContainsString("request.method !== 'GET'", $sw);
    }

    public function test_service_worker_blocks_private_paths_and_private_media(): void
    {
        $sw = $this->withoutJsComments(file_get_contents(public_path(self::SERVICE_WORKER_PATH)));

        // Media privat laporan (foto/video bukti) tersimpan di bawah /storage —
        // ekstensinya gambar/video, jadi tanpa guard eksplisit ia akan lolos
        // allowlist aset statis dan ikut ter-cache. Ini yang dicegah.
        $this->assertStringContainsString("'/storage'", $sw);
        $this->assertStringContainsString('PROTECTED_PATH_PREFIXES', $sw);
        $this->assertStringContainsString('isProtectedPath', $sw);

        foreach (['/admin', '/coach', '/attendance', '/classes', '/api', '/reports'] as $prefix) {
            $this->assertStringContainsString("'{$prefix}'", $sw, "Prefix terproteksi hilang: {$prefix}");
        }
    }

    public function test_service_worker_does_not_precache_any_protected_route(): void
    {
        $sw = file_get_contents(public_path(self::SERVICE_WORKER_PATH));

        $precache = $this->precacheList($sw);
        $this->assertNotEmpty($precache, 'Daftar precache tidak boleh kosong.');

        foreach ($precache as $url) {
            foreach (self::PROTECTED_ROUTE_PREFIXES as $prefix) {
                $this->assertStringStartsNotWith(
                    $prefix,
                    $url,
                    "Rute terproteksi tidak boleh di-precache: {$url}"
                );
            }
            // Tidak ada dokumen HTML aplikasi di precache (hanya fallback offline).
            if (str_ends_with($url, '.html')) {
                $this->assertSame('/offline.html', $url);
            }
        }
    }

    public function test_service_worker_precaches_the_public_assets_and_offline_fallback(): void
    {
        $precache = $this->precacheList(file_get_contents(public_path(self::SERVICE_WORKER_PATH)));

        $this->assertContains('/offline.html', $precache);
        $this->assertContains('/manifest.json', $precache);
        $this->assertContains('/icons/icon-192.png', $precache);
        $this->assertContains('/icons/icon-512.png', $precache);
    }

    public function test_service_worker_refuses_to_cache_session_bearing_responses(): void
    {
        $sw = $this->withoutJsComments(file_get_contents(public_path(self::SERVICE_WORKER_PATH)));

        // Balasan dengan Set-Cookie / no-store / private, opaque, dan non-200
        // tidak pernah masuk Cache Storage.
        $this->assertStringContainsString('isCacheable', $sw);
        $this->assertStringContainsString("headers.has('Set-Cookie')", $sw);
        $this->assertStringContainsString('no-store|no-cache|private', $sw);
        $this->assertStringContainsString("response.type === 'opaque'", $sw);
        $this->assertStringContainsString('response.status !== 200', $sw);
    }

    public function test_service_worker_caching_is_allowlist_based(): void
    {
        $sw = $this->withoutJsComments(file_get_contents(public_path(self::SERVICE_WORKER_PATH)));

        // Allowlist prefix + ekstensi: apa pun yang tidak cocok tidak disimpan.
        $this->assertStringContainsString('STATIC_PATH_PREFIXES', $sw);
        $this->assertStringContainsString('STATIC_EXTENSION', $sw);
        // Data API tidak boleh ikut ter-cache lewat ekstensi .json.
        $this->assertStringNotContainsString('|json', $sw);
    }

    // =========================================================
    // 3. Update strategy
    // =========================================================

    public function test_service_worker_cleans_old_cache_versions_on_activate(): void
    {
        $sw = $this->withoutJsComments(file_get_contents(public_path(self::SERVICE_WORKER_PATH)));

        $this->assertStringContainsString('SW_VERSION', $sw);
        $this->assertStringContainsString("'activate'", $sw);
        $this->assertStringContainsString('caches.keys()', $sw);
        $this->assertStringContainsString('caches.delete(', $sw);
        // Versi baru mengambil alih tanpa menunggu seluruh tab ditutup.
        $this->assertStringContainsString('skipWaiting', $sw);
        $this->assertStringContainsString('clients.claim()', $sw);
    }

    public function test_registration_bypasses_the_http_cache_so_updates_are_detected(): void
    {
        $partial = file_get_contents(resource_path('views/partials/pwa-scripts.blade.php'));

        $this->assertStringContainsString("updateViaCache: 'none'", $partial);
        $this->assertStringContainsString("scope: '/'", $partial);
        $this->assertStringContainsString('registration.update()', $partial);
    }

    public function test_runtime_caches_can_be_cleared_on_logout(): void
    {
        $sw = $this->withoutJsComments(file_get_contents(public_path(self::SERVICE_WORKER_PATH)));
        $partial = file_get_contents(resource_path('views/partials/pwa-scripts.blade.php'));

        $this->assertStringContainsString('LRS_CLEAR_CACHES', $sw);
        $this->assertStringContainsString('LRS_CLEAR_CACHES', $partial);
        // Hanya cache runtime yang dibuang; precache aset publik tetap utuh.
        $this->assertStringContainsString('caches.delete(RUNTIME_CACHE)', $sw);
    }

    // =========================================================
    // 4. Offline fallback
    // =========================================================

    public function test_offline_page_is_static_and_free_of_application_data(): void
    {
        $path = public_path(self::OFFLINE_PATH);
        $this->assertFileExists($path);
        $html = file_get_contents($path);

        // Tanpa aset eksternal: halaman offline harus bisa dirender tanpa jaringan.
        $this->assertStringNotContainsString('cdn.jsdelivr.net', $html);
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);
        $this->assertStringNotContainsString('<script src=', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);

        // Tanpa tautan ke halaman terproteksi (tidak boleh jadi pintu masuk data).
        foreach (self::PROTECTED_ROUTE_PREFIXES as $prefix) {
            $this->assertStringNotContainsString('href="'.$prefix, $html);
        }

        // Menjelaskan kenapa data tidak tersedia offline.
        $this->assertStringContainsString('offline', strtolower($html));
    }

    public function test_navigation_falls_back_to_the_offline_page(): void
    {
        $sw = $this->withoutJsComments(file_get_contents(public_path(self::SERVICE_WORKER_PATH)));

        $this->assertStringContainsString("OFFLINE_URL = '/offline.html'", $sw);
        $this->assertStringContainsString('cache.match(OFFLINE_URL)', $sw);
    }

    // =========================================================
    // 5. Integrasi app shell
    // =========================================================

    public function test_authenticated_app_shell_declares_the_pwa(): void
    {
        $this->actingAs($this->makeUser(User::ROLE_RELATION))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('/manifest.json', false)
            ->assertSee('name="theme-color"', false)
            ->assertSee('#4f46e5', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('apple-touch-icon', false)
            // Registrasi service worker disertakan di app shell.
            ->assertSee("navigator.serviceWorker.register('/sw.js'", false)
            // Tombol install ada di topbar, tersembunyi sampai browser menawarkan.
            ->assertSee('id="pwaInstallBtn"', false)
            ->assertSee('viewport-fit=cover', false);
    }

    public function test_login_page_declares_the_pwa(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('/manifest.json', false)
            ->assertSee('name="theme-color"', false)
            ->assertSee("navigator.serviceWorker.register('/sw.js'", false);
    }

    public function test_registration_is_skipped_outside_a_secure_context(): void
    {
        $partial = file_get_contents(resource_path('views/partials/pwa-scripts.blade.php'));

        // Di http:// biasa (mis. domain .test tanpa TLS) registrasi di-skip tanpa
        // error, sehingga aplikasi tetap berjalan normal tanpa PWA.
        $this->assertStringContainsString("'serviceWorker' in navigator", $partial);
        $this->assertStringContainsString('isSecureContext', $partial);
        $this->assertStringContainsString("if (supportsServiceWorker)", $partial);
    }

    // =========================================================
    // 6. Security — tidak ada rahasia yang bocor
    // =========================================================

    public function test_pwa_assets_do_not_leak_secrets_or_credentials(): void
    {
        $appKey = (string) config('app.key');

        $files = [
            public_path(self::MANIFEST_PATH),
            public_path(self::SERVICE_WORKER_PATH),
            public_path(self::OFFLINE_PATH),
            resource_path('views/partials/pwa-head.blade.php'),
            resource_path('views/partials/pwa-scripts.blade.php'),
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            $this->assertStringNotContainsString('APP_KEY', $contents, basename($file));
            $this->assertStringNotContainsString('DB_PASSWORD', $contents, basename($file));
            $this->assertStringNotContainsString('csrf-token', $contents, basename($file));

            if ($appKey !== '') {
                $this->assertStringNotContainsString($appKey, $contents, basename($file));

                // Nilai key tanpa prefix "base64:" — dijaga minimal 8 karakter
                // agar pencarian tidak pernah memakai needle kosong/pendek yang
                // selalu cocok.
                $keyBody = substr($appKey, 7);
                if (strlen($keyBody) >= 8) {
                    $this->assertStringNotContainsString($keyBody, $contents, basename($file).' membocorkan APP_KEY.');
                }
            }
        }
    }

    public function test_manifest_does_not_reference_authenticated_endpoints_as_assets(): void
    {
        $raw = file_get_contents(public_path(self::MANIFEST_PATH));
        $manifest = json_decode($raw, true);

        foreach (self::PROTECTED_ROUTE_PREFIXES as $prefix) {
            // Shortcut memang boleh menunjuk rute aplikasi (itu pintasan navigasi),
            // tetapi tidak boleh dipakai sebagai sumber ikon/aset statis.
            foreach ($manifest['icons'] as $icon) {
                $this->assertStringStartsNotWith($prefix, $icon['src']);
            }
        }
    }

    // =========================================================
    // Helpers
    // =========================================================

    /** @return array<string, mixed> */
    private function readPublicJson(string $relativePath): array
    {
        $path = public_path($relativePath);
        $this->assertFileExists($path);

        $decoded = json_decode(file_get_contents($path), true);
        $this->assertIsArray($decoded, "{$relativePath} bukan JSON yang valid.");
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), "{$relativePath}: ".json_last_error_msg());

        return $decoded;
    }

    /**
     * Dimensi PNG diambil dari blok IHDR (byte 16-23) tanpa perlu ekstensi GD.
     *
     * @return array{0: int, 1: int}
     */
    private function pngDimensions(string $path): array
    {
        $contents = file_get_contents($path);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($contents, 0, 8), "Bukan berkas PNG: {$path}");
        $this->assertSame('IHDR', substr($contents, 12, 4));

        $parts = unpack('Nwidth/Nheight', substr($contents, 16, 8));

        return [(int) $parts['width'], (int) $parts['height']];
    }

    /** @return list<string> */
    private function precacheList(string $sw): array
    {
        $this->assertSame(1, preg_match('/const PRECACHE_URLS = \[(.*?)\];/s', $sw, $matches), 'PRECACHE_URLS tidak ditemukan.');

        // Sebagian entri ditulis sebagai konstanta (mis. OFFLINE_URL), sebagian
        // literal. Keduanya di-resolve ke nilai sebenarnya supaya pengujian
        // membaca daftar yang benar-benar di-precache.
        preg_match_all("/const\s+([A-Z_][A-Z0-9_]*)\s*=\s*'([^']*)'/", $sw, $constants, PREG_SET_ORDER);
        $resolved = [];
        foreach ($constants as $constant) {
            $resolved[$constant[1]] = $constant[2];
        }

        preg_match_all("/'([^']+)'|([A-Z_][A-Z0-9_]*)/", $matches[1], $tokens, PREG_SET_ORDER);

        $urls = [];
        foreach ($tokens as $token) {
            if (($token[1] ?? '') !== '') {
                $urls[] = $token[1];
                continue;
            }
            $identifier = $token[2];
            if (isset($resolved[$identifier])) {
                $urls[] = $resolved[$identifier];
            }
        }

        return $urls;
    }

    private function withoutJsComments(string $contents): string
    {
        return (string) preg_replace('#/\*.*?\*/#s', '', $contents);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' PWA',
            'email' => $role.'-pwa@test.test',
            'password' => Hash::make('password'),
            'role' => $role,
        ]);
    }
}
