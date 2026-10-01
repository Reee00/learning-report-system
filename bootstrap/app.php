<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \App\Http\Middleware\RoleMiddleware::class,
        'permission' => \App\Http\Middleware\PermissionMiddleware::class,
        'permission_any' => \App\Http\Middleware\PermissionAnyMiddleware::class,
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
    ]);
    $middleware->trustProxies(at: '*');    
})
    ->withExceptions(function (Exceptions $exceptions): void {
        // POST body melebihi post_max_size (mis. upload video > batas server)
        // menghasilkan PostTooLargeException. Tanpa handler ini user hanya
        // melihat halaman error 413 tanpa penjelasan. Redirect kembali dengan
        // pesan yang bisa ditindaklanjuti oleh Coach.
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Unggahan terlalu besar. Total ukuran file melebihi batas server (post_max_size). Kecilkan ukuran file lalu coba lagi.',
                ], 413);
            }

            return redirect()->back()
                ->with('error', 'Unggahan terlalu besar. Total ukuran file melebihi batas server. Maksimal 3 video (100 MB per video) dan 10 foto (10 MB per foto) per laporan — kecilkan atau kurangi file lalu kirim ulang.');
        });

        // =================================================================
        // HTTP 419 "Page Expired" — CSRF token tidak cocok.
        //
        // TokenMismatchException dilempar VerifyCsrfToken, yang berada di
        // middleware GROUP `web`. Middleware group berjalan SEBELUM middleware
        // route (`auth`, `role`), jadi POST dengan sesi yang sudah hilang
        // gagal di pemeriksaan CSRF lebih dahulu — dan tanpa handler ini
        // Laravel berhenti di halaman 419 bawaan: pesan "Page Expired" polos,
        // tanpa jalan kembali ke login, tanpa isian yang dikembalikan.
        //
        // Handler ini TIDAK melonggarkan apa pun: CSRF tetap diperiksa,
        // VerifyCsrfToken tetap terpasang, dan aksi yang diminta tetap TIDAK
        // dijalankan. Yang ditambahkan hanya jalan keluar sesuai penyebabnya.
        //
        // Dua cabang, karena dua penyebabnya butuh UX yang berbeda:
        //
        //  A/B. `$request->user()` null — sesi benar-benar sudah tidak ada
        //       (SESSION_LIFETIME habis, cookie hilang, atau logout di tab
        //       lain). Tidak ada yang bisa dilanjutkan; user harus masuk lagi.
        //       `redirect()->guest()` menyimpan halaman ASAL (referer, yaitu
        //       halaman form ber-metode GET) sebagai `url.intended`, sehingga
        //       sesudah login user mendarat kembali di form itu — bukan di
        //       dashboard, dan bukan di URL POST yang akan berujung 405.
        //
        //  A/F. `$request->user()` ada — sesinya hidup, tetapi token di
        //       halaman itu sudah basi: halaman dipulihkan dari bfcache
        //       browser, tab yang dibuka sebelum login ulang, atau form yang
        //       dibiarkan terbuka lebih lama dari masa berlaku token. Aksi
        //       tetap tidak dijalankan, tetapi user tidak perlu login ulang:
        //       dikembalikan ke halaman asal bersama isian yang sudah
        //       diketik, minus field rahasia dan file.
        //
        // CATATAN PENTING SOAL TYPE-HINT
        //
        // Closure ini sengaja menangkap `HttpException`, bukan
        // `TokenMismatchException`. `Handler::prepareException()` memetakan
        // TokenMismatchException menjadi `new HttpException(419, …)` SEBELUM
        // render callback dijalankan — jadi closure yang di-type-hint ke
        // TokenMismatchException tidak akan pernah dipanggil, dan halaman 419
        // bawaan kembali muncul. Status 419 tetap eksklusif milik
        // TokenMismatchException, sehingga penyaringan di bawah tidak
        // menyerobot HttpException lain (403, 404, 413, …).
        // =================================================================
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Sesi atau token keamanan sudah tidak berlaku. Muat ulang halaman lalu coba lagi.',
                ], 419);
            }

            $user = $request->user();

            if ($user === null) {
                return redirect()->guest(route('login'))
                    ->with('error', 'Sesi Anda telah berakhir, jadi permintaan tadi tidak diproses. Silakan masuk kembali — Anda akan diarahkan balik ke halaman sebelumnya.');
            }

            // Field rahasia TIDAK pernah dikembalikan ke halaman, dan file
            // tidak bisa direpopulasi ke input file, jadi keduanya dibuang
            // sebelum flash. Sisanya dikembalikan supaya user tidak perlu
            // mengetik ulang.
            $sensitive = ['_token', '_method', 'password', 'password_confirmation', 'current_password', 'new_password'];
            $safeInput = $request->except(array_merge($sensitive, array_keys($request->allFiles())));

            return redirect()
                ->back(302, [], $user->homeUrl() ?? route('login'))
                ->withInput($safeInput)
                ->with('error', 'Halaman ini sudah terlalu lama terbuka sehingga token keamanannya kedaluwarsa, dan aksi tadi tidak dijalankan. Isian Anda dikembalikan di bawah — periksa sebentar lalu kirim ulang.');
        });
    })->create();
