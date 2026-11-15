<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Where is my order?" for people who checked out as guests.
 *
 * A signed-in customer has My Orders. A guest has nothing once the session
 * that placed the order is gone — a closed browser, a different phone, a week
 * later. This gives them two ways back in:
 *
 *   1. the link in their confirmation email, which carries the order token;
 *   2. this page, where the order number plus the email they ordered with
 *      proves the order is theirs.
 *
 * The status page is deliberately thinner than the account one: it shows what
 * the order is and where it has got to, but only the city and pincode of the
 * delivery address. A token can be forwarded or left in a shared browser's
 * history, and a street address and phone number should not travel with it.
 * The full address stays behind the account, or behind the session that
 * placed the order.
 */
class OrderTrackingController extends Controller
{
    /** Lookup attempts allowed per IP per minute. */
    protected const ATTEMPTS = 6;

    public function show(): View
    {
        return view('front.track-order');
    }

    /**
     * Find the order, then hand over to the status page by its token.
     *
     * Deliberately vague on failure: saying "no such order number" would let
     * someone confirm which numbers exist, and saying "wrong email" would
     * confirm one order's email address. Either way it is one message.
     */
    public function find(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:255'],
        ], [
            'order_number.required' => 'Please enter your order number.',
            'email.required' => 'Please enter the email address you ordered with.',
        ]);

        $this->guardAgainstGuessing($request);

        $order = Order::query()
            ->whereRaw('LOWER(order_number) = ?', [Str::lower(trim($data['order_number']))])
            ->whereRaw('LOWER(customer_email) = ?', [Str::lower(trim($data['email']))])
            ->first();

        if (! $order) {
            RateLimiter::hit($this->throttleKey($request));

            throw ValidationException::withMessages([
                'order_number' => 'We could not find an order with those details. Check the order number and the email address on your confirmation.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        return redirect()->route('track.status', $order->public_token);
    }

    /**
     * The status page. The token is the key, so the email link works on any
     * device without a sign-in.
     */
    public function status(string $token): View
    {
        $order = Order::with('items')->where('public_token', $token)->firstOrFail();

        return view('front.track-status', [
            'order' => $order,
            'steps' => $this->timeline($order),
        ]);
    }

    /**
     * Where the order has got to, as a list of steps.
     *
     * A cancelled or returned order does not belong on the normal line, so it
     * gets its own short one rather than being shown as "almost delivered".
     *
     * @return array<int, array{label: string, state: string}>
     */
    protected function timeline(Order $order): array
    {
        if (in_array($order->status, ['cancelled', 'returned'], true)) {
            return [
                ['label' => 'Order placed', 'state' => 'done'],
                ['label' => Order::STATUSES[$order->status], 'state' => 'stopped'],
            ];
        }

        $order_of_events = ['pending', 'confirmed', 'processing', 'shipped', 'delivered'];
        $labels = [
            'pending' => 'Order placed',
            'confirmed' => 'Confirmed',
            'processing' => 'Being prepared',
            'shipped' => 'On its way',
            'delivered' => 'Delivered',
        ];

        $reached = array_search($order->status, $order_of_events, true);
        $reached = $reached === false ? 0 : $reached;

        return array_map(fn (string $step, int $index) => [
            'label' => $labels[$step],
            'state' => match (true) {
                $index < $reached => 'done',
                $index === $reached => 'current',
                default => 'todo',
            },
        ], $order_of_events, array_keys($order_of_events));
    }

    protected function guardAgainstGuessing(Request $request): void
    {
        $key = $this->throttleKey($request);

        if (! RateLimiter::tooManyAttempts($key, self::ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'order_number' => 'Too many attempts. Please try again in '.ceil($seconds / 60).' minute(s).',
        ]);
    }

    protected function throttleKey(Request $request): string
    {
        return 'track-order|'.$request->ip();
    }
}
