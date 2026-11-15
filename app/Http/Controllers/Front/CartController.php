<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\PricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cart,
        protected PricingService $pricing,
        protected CouponService $coupons,
    ) {}

    public function index(Request $request): View
    {
        $cart = $this->cart->current($request);
        $pincode = $request->session()->get('pincode') ?: $cart->pincode;

        return view('front.cart', [
            'cart' => $cart,
            'totals' => $this->pricing->totals($cart, $pincode),
            'problems' => $this->cart->problems($cart),
            'pincode' => $pincode,
            'canCheckout' => $this->cart->isCheckoutable($cart),
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ]);

        $product = Product::findOrFail($data['product_id']);
        $variant = ! empty($data['product_variant_id'])
            ? ProductVariant::find($data['product_variant_id'])
            : null;

        $result = $this->cart->add($request, $product, $variant, (int) ($data['quantity'] ?? 1));

        return $result['ok']
            ? redirect()->route('cart.index')->with('success', $result['message'])
            : back()->with('error', $result['message']);
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:'.CartService::MAX_QUANTITY],
        ]);

        $cart = $this->cart->current($request);
        $result = $this->cart->updateQuantity($cart, $item, (int) $data['quantity']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $cart = $this->cart->current($request);

        abort_unless($item->cart_id === $cart->id, 404);

        $name = $item->product?->name ?? 'Item';
        $item->delete();

        return back()->with('success', $name.' removed from your cart.');
    }

    /**
     * Remember the pincode so delivery can be costed in the cart.
     */
    public function setPincode(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pincode' => ['required', 'regex:/^[1-9][0-9]{5}$/'],
        ], ['pincode.regex' => 'Enter a valid 6-digit pincode.']);

        $cart = $this->cart->current($request);
        $cart->update(['pincode' => $data['pincode']]);
        $request->session()->put('pincode', $data['pincode']);

        return back()->with('success', 'Delivery updated for '.$data['pincode'].'.');
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:60']]);

        $cart = $this->cart->current($request);
        $coupon = $this->coupons->find($data['code']);

        if (! $coupon) {
            return back()->with('error', 'That coupon code was not recognised.');
        }

        $totals = $this->pricing->totals($cart, $cart->pincode);
        $check = $this->coupons->validate(
            $coupon,
            $totals->subtotal,
            $cart->customer_id,
            auth('customer')->user()?->email,
        );

        if (! $check['ok']) {
            return back()->with('error', $check['message']);
        }

        $this->cart->applyCoupon($cart, $coupon);

        return back()->with('success', $check['message']);
    }

    public function removeCoupon(Request $request): RedirectResponse
    {
        $this->cart->removeCoupon($this->cart->current($request));

        return back()->with('success', 'Coupon removed.');
    }
}
