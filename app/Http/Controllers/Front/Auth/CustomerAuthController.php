<?php

namespace App\Http\Controllers\Front\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Front\WishlistController;
use App\Models\Customer;
use App\Services\CartService;
use App\Services\LoginHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerAuthController extends Controller
{
    public function __construct(protected LoginHistoryService $history) {}

    public function showLogin(): View
    {
        return view('front.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::lower($request->input('email')).'|customer|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $this->history->failed($request, 'customer', $request->input('email'), 'Rate limited');

            throw ValidationException::withMessages([
                'email' => 'Too many attempts. Please try again in '.ceil($seconds / 60).' minute(s).',
            ]);
        }

        $customer = Customer::where('email', $request->input('email'))->first();

        if ($customer && ! $customer->is_active) {
            RateLimiter::hit($key);
            $this->history->failed($request, 'customer', $request->input('email'), 'Account disabled');

            throw ValidationException::withMessages(['email' => 'This account has been disabled.']);
        }

        // The guest cart and wishlist rows are keyed on the session id, and
        // attempt() migrates the session the moment it succeeds — so take a
        // note of the id those rows were written under before calling it.
        $guestSessionId = $request->session()->getId();

        if (! Auth::guard('customer')->attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($key);
            $this->history->failed($request, 'customer', $request->input('email'));

            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        /** @var Customer $authenticated */
        $authenticated = Auth::guard('customer')->user();
        $authenticated->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->saveQuietly();

        $this->history->success($request, 'customer', $authenticated);

        // Whatever they put in the cart or wishlist as a guest comes with them.
        $this->adoptGuestSession($guestSessionId, (int) $authenticated->id);

        return redirect()->intended(route('home'))->with('success', 'Welcome back, '.$authenticated->name.'.');
    }

    public function showRegister(): View
    {
        return view('front.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:customers,email'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/', 'unique:customers,phone'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $customer = Customer::create($data + ['is_active' => true]);

        $guestSessionId = $request->session()->getId();

        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();
        $this->history->success($request, 'customer', $customer);

        $this->adoptGuestSession($guestSessionId, (int) $customer->id);

        return redirect()->route('home')->with('success', 'Your account has been created.');
    }

    /**
     * Carry a guest's cart and wishlist onto their account.
     *
     * Takes the session id the guest rows were written under, not the request:
     * signing in regenerates the session, so by the time this runs the request
     * no longer knows the id those rows are keyed on.
     */
    protected function adoptGuestSession(string $guestSessionId, int $customerId): void
    {
        app(CartService::class)->mergeGuestCart($guestSessionId, $customerId);
        WishlistController::mergeGuest($guestSessionId, $customerId);
    }

    public function logout(Request $request): RedirectResponse
    {
        $customer = Auth::guard('customer')->user();

        if ($customer) {
            $this->history->markLoggedOut('customer', $customer);
        }

        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'You have been signed out.');
    }
}
