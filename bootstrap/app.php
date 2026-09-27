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
    })->create();
