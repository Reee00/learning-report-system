<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;

class PermissionAnyMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): mixed
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $authorization = app(AuthorizationService::class);
        $allowed = collect($permissions)->contains(
            fn (string $permission): bool => $authorization->allows($user, $permission)
        );

        abort_unless($allowed, 403, 'Permission tidak mencukupi.');

        return $next($request);
    }
}
