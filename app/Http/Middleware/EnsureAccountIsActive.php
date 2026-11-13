<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out an account that was disabled while its session was still alive.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next, string $guard = 'admin'): Response
    {
        $user = Auth::guard($guard)->user();

        if ($user && ! $user->is_active) {
            return $this->logout($request, $guard);
        }

        return $next($request);
    }

    protected function logout(Request $request, string $guard): Response
    {
        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route($guard === 'admin' ? 'admin.login' : 'customer.login')
            ->withErrors(['email' => 'Your account has been disabled. Please contact the administrator.']);
    }
}
