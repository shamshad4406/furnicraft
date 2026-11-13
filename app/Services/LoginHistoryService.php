<?php

namespace App\Services;

use App\Models\LoginHistory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class LoginHistoryService
{
    public function success(Request $request, string $guard, Authenticatable $user): LoginHistory
    {
        return LoginHistory::create([
            'guard' => $guard,
            'authenticatable_type' => $user::class,
            'authenticatable_id' => $user->getAuthIdentifier(),
            'email' => $user->email ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'successful' => true,
            'logged_in_at' => now(),
        ]);
    }

    public function failed(Request $request, string $guard, ?string $email, string $reason = 'Invalid credentials'): LoginHistory
    {
        return LoginHistory::create([
            'guard' => $guard,
            'email' => $email,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'successful' => false,
            'reason' => $reason,
        ]);
    }

    public function markLoggedOut(string $guard, Authenticatable $user): void
    {
        LoginHistory::query()
            ->where('guard', $guard)
            ->where('authenticatable_type', $user::class)
            ->where('authenticatable_id', $user->getAuthIdentifier())
            ->where('successful', true)
            ->whereNull('logged_out_at')
            ->latest('id')
            ->limit(1)
            ->update(['logged_out_at' => now()]);
    }
}
