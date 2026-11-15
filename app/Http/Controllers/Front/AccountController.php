<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * The customer's own area. Everything here is scoped to the signed-in
 * customer; nothing accepts an id that selects somebody else's data.
 */
class AccountController extends Controller
{
    public function dashboard(): View
    {
        $customer = auth('customer')->user();

        return view('front.account.dashboard', [
            'customer' => $customer,
            'recentOrders' => $customer->orders()->with('items')->limit(3)->get(),
            'orderCount' => $customer->orders()->count(),
            'addressCount' => $customer->addresses()->count(),
            'wishlistCount' => $customer->wishlist()->count(),
        ]);
    }

    public function profile(): View
    {
        return view('front.account.profile', ['customer' => auth('customer')->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $customer = auth('customer')->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('customers', 'email')->ignore($customer->id)->whereNull('deleted_at')],
            'phone' => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/', Rule::unique('customers', 'phone')->ignore($customer->id)->whereNull('deleted_at')],
        ]);

        $customer->fill($data)->save();

        return back()->with('success', 'Your details have been updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $customer = auth('customer')->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        if (! Hash::check($request->input('current_password'), $customer->password)) {
            return back()->withErrors(['current_password' => 'Your current password is incorrect.']);
        }

        $customer->password = $request->input('password');
        $customer->save();

        return back()->with('success', 'Your password has been changed.');
    }

    /* --------------------------------------------------------- orders */

    public function orders(Request $request): View
    {
        $orders = auth('customer')->user()
            ->orders()
            ->with('items')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->paginate(10)
            ->withQueryString();

        return view('front.account.orders', [
            'orders' => $orders,
            'statuses' => Order::STATUSES,
        ]);
    }

    public function order(string $token): View
    {
        // Scoped to the signed-in customer, so the token alone is not enough.
        $order = auth('customer')->user()
            ->orders()
            ->with(['items', 'payments', 'shipments', 'returns'])
            ->where('public_token', $token)
            ->firstOrFail();

        return view('front.account.order', compact('order'));
    }
}
