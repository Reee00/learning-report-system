<?php

namespace App\Http\Middleware;

use App\Services\AuthorizationService;
use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        abort_unless(
            app(AuthorizationService::class)->allows($user, $permission),
            403,
            'Permission tidak mencukupi.'
        );

        return $next($request);
    }
}
