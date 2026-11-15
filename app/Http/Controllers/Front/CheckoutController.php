<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Requests\Front\CheckoutRequest;
use App\Models\Address;
use App\Models\Order;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Services\RazorpayService;
use App\Services\StockUnavailable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected PricingService $pricing,
        protected OrderService $orders,
        protected RazorpayService $razorpay,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $cart = $this->cart->current($request);

        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        if (! $this->cart->isCheckoutable($cart)) {
            return redirect()->route('cart.index')
                ->with('error', 'Some items need attention before you can check out.');
        }

        if (! settings()->bool('guest_checkout_enabled', true) && ! auth('customer')->check()) {
            return redirect()->route('customer.login')
                ->with('error', 'Please sign in to complete your order.');
        }

        $customer = auth('customer')->user();
        $pincode = $request->session()->get('pincode') ?: $cart->pincode;
        $totals = $this->pricing->totals($cart, $pincode, $customer?->email);

        $minimum = (float) settings('min_order_value', 0);

        if ($minimum > 0 && $totals->subtotal < $minimum) {
            return redirect()->route('cart.index')
                ->with('error', 'The minimum order value is '.money($minimum).'.');
        }

        // One key per visit to this page; it survives a refresh of the POST.
        $request->session()->put('checkout_key', $request->session()->get('checkout_key') ?: (string) Str::uuid());

        return view('front.checkout', [
            'cart' => $cart,
            'totals' => $totals,
            'customer' => $customer,
            'addresses' => $customer?->addresses()->get() ?? collect(),
            'pincode' => $pincode,
            'codAllowed' => $this->pricing->codAllowed($pincode),
            'codAdvance' => $this->pricing->codAdvance($totals->grandTotal),
            'razorpayReady' => $this->razorpay->configured(),
        ]);
    }

    /**
     * Create the order, hold the stock, then hand off to payment.
     */
    public function store(CheckoutRequest $request): RedirectResponse
    {
        $cart = $this->cart->current($request);

        if ($cart->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $shipping = $request->validated()['shipping'];
        $billing = $request->boolean('billing_same') ? $shipping : $request->validated()['billing'];

        $method = $request->input('payment_method');

        if ($method === 'cod' && ! $this->pricing->codAllowed($shipping['pincode'])) {
            return back()->withInput()->with('error', 'Cash on delivery is not available for that pincode.');
        }

        if ($method === 'razorpay' && ! $this->razorpay->configured()) {
            return back()->withInput()->with('error', 'Online payment is unavailable right now. Please choose cash on delivery.');
        }

        $key = $request->session()->get('checkout_key') ?: (string) Str::uuid();
        $request->session()->put('checkout_key', $key);

        try {
            $order = $this->orders->place($cart, [
                'customer_name' => $request->input('customer_name'),
                'customer_email' => $request->input('customer_email'),
                'customer_phone' => $request->input('customer_phone'),
                'gst_number' => $request->input('gst_number'),
                'shipping_address' => $shipping,
                'billing_address' => $billing,
                'payment_method' => $method,
                'notes' => $request->input('notes'),
            ], $key);
        } catch (StockUnavailable $e) {
            return redirect()->route('cart.index')
                ->with('error', 'Your cart changed while you were checking out.')
                ->with('cart_problems', $e->problems);
        }

        if (auth('customer')->check() && $request->boolean('save_address')) {
            $this->saveAddress((int) auth('customer')->id(), $shipping);
        }

        $this->rememberOrder($request, $order);

        return redirect()->route('checkout.pay', $order);
    }

    /**
     * The payment step. Razorpay opens here; a COD order with no advance is
     * confirmed straight away.
     */
    public function pay(Request $request, string $token): View|RedirectResponse
    {
        $order = $this->findOwnOrder($request, $token);

        if ($order->payment_status === 'paid' || $order->status === 'confirmed') {
            return redirect()->route('checkout.success', $order);
        }

        $payable = $this->orders->amountPayableNow($order);

        if ($order->payment_method === 'cod' && $payable <= 0) {
            $this->orders->confirmCashOnDelivery($order);
            $this->cart->clear($this->cart->current($request));
            $request->session()->forget('checkout_key');

            return redirect()->route('checkout.success', $order);
        }

        return view('front.checkout-pay', [
            'order' => $order->load('items'),
            'payable' => $payable,
            'isAdvance' => $order->payment_method === 'cod',
            'razorpayKey' => $this->razorpay->publicKey(),
            'testMode' => $this->razorpay->isTestMode(),
        ]);
    }

    public function success(Request $request, string $token): View|RedirectResponse
    {
        $order = $this->findOwnOrder($request, $token);

        if ($order->status === 'pending' && $order->payment_status === 'pending') {
            return redirect()->route('checkout.pay', $order);
        }

        return view('front.checkout-success', ['order' => $order->load('items')]);
    }

    public function failed(Request $request, string $token): View
    {
        $order = $this->findOwnOrder($request, $token);

        return view('front.checkout-failed', [
            'order' => $order,
            'canRetry' => $order->payment_status !== 'paid' && $order->status !== 'cancelled',
        ]);
    }

    /**
     * An order is visible to the customer who placed it, or to the guest whose
     * session created it. The token alone is not enough.
     */
    protected function findOwnOrder(Request $request, string $token): Order
    {
        $order = Order::where('public_token', $token)->firstOrFail();

        $customerId = auth('customer')->id();

        $owns = ($customerId && $order->customer_id === $customerId)
            || $request->session()->get('current_order_id') === $order->id
            || in_array($order->id, (array) $request->session()->get('guest_order_ids', []), true);

        abort_unless($owns, 404);

        return $order;
    }

    protected function rememberOrder(Request $request, Order $order): void
    {
        $request->session()->put('current_order_id', $order->id);

        $guestOrders = (array) $request->session()->get('guest_order_ids', []);
        $guestOrders[] = $order->id;
        $request->session()->put('guest_order_ids', array_slice(array_unique($guestOrders), -10));
    }

    /**
     * @param  array<string, mixed>  $shipping
     */
    protected function saveAddress(int $customerId, array $shipping): void
    {
        $exists = Address::where('customer_id', $customerId)
            ->where('line1', $shipping['line1'])
            ->where('pincode', $shipping['pincode'])
            ->exists();

        if ($exists) {
            return;
        }

        $isFirst = ! Address::where('customer_id', $customerId)->exists();

        Address::create($shipping + [
            'customer_id' => $customerId,
            'type' => 'home',
            'is_default_shipping' => $isFirst,
            'is_default_billing' => $isFirst,
        ]);
    }
}
