<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     * Contoh penggunaan: middleware('role:relation,superadmin') atau middleware('role:coach')
     */
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        // Jika belum login, redirect ke halaman login
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // Jika role tidak sesuai, tampilkan error 403 (Forbidden)
        if (!in_array(Auth::user()->role, $roles, true)) {
            abort(403, 'Akses tidak diizinkan.');
        }

        return $next($request);
    }
}
