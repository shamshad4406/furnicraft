<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\User;
use App\Services\LoginHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(protected LoginHistoryService $history) {}

    public function show(): View
    {
        return view('admin.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        $user = User::withTrashed()->where('email', $credentials['email'])->first();

        if ($user && ($user->trashed() || ! $user->is_active)) {
            RateLimiter::hit($request->throttleKey());
            $this->history->failed($request, 'admin', $credentials['email'], 'Account disabled');

            throw ValidationException::withMessages([
                'email' => 'This account has been disabled. Please contact the administrator.',
            ]);
        }

        if (! Auth::guard('admin')->attempt($credentials, $remember)) {
            RateLimiter::hit($request->throttleKey());
            $this->history->failed($request, 'admin', $credentials['email']);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate();

        /** @var User $authenticated */
        $authenticated = Auth::guard('admin')->user();
        $authenticated->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        $this->history->success($request, 'admin', $authenticated);

        activity('auth')
            ->causedBy($authenticated)
            ->withProperties(['ip' => $request->ip()])
            ->log('Signed in to the admin panel');

        return redirect()->intended(route('admin.dashboard'))
            ->with('success', 'Welcome back, '.$authenticated->name.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::guard('admin')->user();

        if ($user) {
            $this->history->markLoggedOut('admin', $user);
            activity('auth')->causedBy($user)->log('Signed out of the admin panel');
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('success', 'You have been signed out.');
    }

    /**
     * Allow a limited number of attempts per email + IP before locking out.
     */
    protected function ensureIsNotRateLimited(LoginRequest $request): void
    {
        $max = (int) settings('admin_login_throttle', 5);

        if (! RateLimiter::tooManyAttempts($request->throttleKey(), max($max, 1))) {
            return;
        }

        $seconds = RateLimiter::availableIn($request->throttleKey());

        $this->history->failed($request, 'admin', $request->input('email'), 'Rate limited');

        throw ValidationException::withMessages([
            'email' => 'Too many login attempts. Please try again in '.ceil($seconds / 60).' minute(s).',
        ]);
    }
}
