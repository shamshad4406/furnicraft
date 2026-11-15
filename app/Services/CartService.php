<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The visitor's cart: one row per session for guests, adopted by the customer
 * on sign-in so nothing is lost at the login step.
 */
class CartService
{
    /** Most units of one line a customer may add without talking to us. */
    public const MAX_QUANTITY = 20;

    public function __construct(
        protected StockService $stock,
        protected DeliveryService $delivery,
    ) {}

    /**
     * The current cart, created on demand.
     */
    public function current(Request $request, bool $create = true): ?Cart
    {
        $customerId = auth('customer')->id();
        $sessionId = $request->session()->getId();

        $cart = $customerId
            ? Cart::firstWhere('customer_id', $customerId)
            : Cart::whereNull('customer_id')->firstWhere('session_id', $sessionId);

        if (! $cart && $create) {
            $cart = Cart::create([
                'customer_id' => $customerId,
                'session_id' => $sessionId,
                'last_activity_at' => now(),
            ]);
        }

        return $cart?->load(['items.product.images', 'items.variant', 'coupon']);
    }

    /**
     * Sign-in: move the guest cart onto the customer, merging quantities if
     * they already had one.
     *
     * Takes the session id rather than the request because the caller signs
     * in first, and signing in regenerates the session: the guest rows are
     * still keyed on the id from before that.
     */
    public function mergeGuestCart(string $guestSessionId, int $customerId): void
    {
        $guestCart = Cart::whereNull('customer_id')->where('session_id', $guestSessionId)->with('items')->first();

        if (! $guestCart) {
            return;
        }

        $customerCart = Cart::firstWhere('customer_id', $customerId);

        if (! $customerCart) {
            $guestCart->update(['customer_id' => $customerId, 'last_activity_at' => now()]);

            return;
        }

        DB::transaction(function () use ($guestCart, $customerCart) {
            foreach ($guestCart->items as $item) {
                $existing = $customerCart->items()
                    ->where('product_id', $item->product_id)
                    ->where('product_variant_id', $item->product_variant_id)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'quantity' => min(self::MAX_QUANTITY, $existing->quantity + $item->quantity),
                    ]);
                } else {
                    $item->update(['cart_id' => $customerCart->id]);
                }
            }

            $guestCart->delete();
            $customerCart->update(['last_activity_at' => now()]);
        });
    }

    /**
     * @return array{ok: bool, message: string}
     */
    public function add(Request $request, Product $product, ?ProductVariant $variant, int $quantity): array
    {
        if ($product->status !== 'active') {
            return ['ok' => false, 'message' => 'That product is not available.'];
        }

        if ($variant && ($variant->product_id !== $product->id || ! $variant->is_active)) {
            return ['ok' => false, 'message' => 'That option is not available.'];
        }

        $cart = $this->current($request);

        $line = $cart->items()
            ->where('product_id', $product->id)
            ->where('product_variant_id', $variant?->id)
            ->first();

        $wanted = min(self::MAX_QUANTITY, ($line?->quantity ?? 0) + $quantity);
        $available = $this->stock->available($product, $variant);

        if ($available < 1) {
            return ['ok' => false, 'message' => $product->name.' is out of stock.'];
        }

        if ($wanted > $available) {
            $wanted = $available;
        }

        $price = (float) ($variant?->effective_price ?? $product->effective_price);

        if ($line) {
            $line->update(['quantity' => $wanted, 'unit_price' => $price]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'quantity' => $wanted,
                'unit_price' => $price,
            ]);
        }

        $cart->update(['last_activity_at' => now()]);

        return [
            'ok' => true,
            'message' => $wanted < ($line?->quantity ?? 0) + $quantity
                ? 'Added — we only had '.$wanted.' in stock.'
                : $product->name.' added to your cart.',
        ];
    }

    public function updateQuantity(Cart $cart, CartItem $item, int $quantity): array
    {
        if ($item->cart_id !== $cart->id) {
            return ['ok' => false, 'message' => 'That item is not in your cart.'];
        }

        if ($quantity < 1) {
            $item->delete();

            return ['ok' => true, 'message' => 'Item removed.'];
        }

        $quantity = min(self::MAX_QUANTITY, $quantity);
        $available = $this->stock->available($item->product, $item->variant);

        if ($quantity > $available) {
            if ($available < 1) {
                $item->delete();

                return ['ok' => false, 'message' => $item->product->name.' is out of stock and was removed.'];
            }

            $item->update(['quantity' => $available]);

            return ['ok' => false, 'message' => 'Only '.$available.' left of '.$item->product->name.'.'];
        }

        $item->update(['quantity' => $quantity]);
        $cart->update(['last_activity_at' => now()]);

        return ['ok' => true, 'message' => 'Cart updated.'];
    }

    /**
     * Re-check every line against live stock and price. Returns the warnings
     * to show above the cart.
     *
     * @return array<int, string>
     */
    public function problems(Cart $cart): array
    {
        $problems = [];

        foreach ($cart->items as $item) {
            if (! $item->product || $item->product->status !== 'active') {
                $problems[] = ($item->product?->name ?? 'An item').' is no longer available.';
                continue;
            }

            if ($item->variant && ! $item->variant->is_active) {
                $problems[] = $item->product->name.': that option is no longer available.';
                continue;
            }

            $available = $this->stock->available($item->product, $item->variant);

            if ($available < $item->quantity) {
                $problems[] = $available > 0
                    ? sprintf('Only %d left of %s — reduce the quantity to continue.', $available, $item->product->name)
                    : sprintf('%s is out of stock.', $item->product->name);
            }

            if ($item->price_changed) {
                $problems[] = sprintf(
                    'The price of %s changed from %s to %s.',
                    $item->product->name,
                    money($item->unit_price),
                    money($item->current_price),
                );
            }
        }

        return $problems;
    }

    public function isCheckoutable(Cart $cart): bool
    {
        if ($cart->isEmpty()) {
            return false;
        }

        foreach ($cart->items as $item) {
            if (! $item->product || $item->product->status !== 'active') {
                return false;
            }

            if ($this->stock->available($item->product, $item->variant) < $item->quantity) {
                return false;
            }
        }

        return true;
    }

    public function applyCoupon(Cart $cart, Coupon $coupon): void
    {
        $cart->update(['coupon_id' => $coupon->id, 'coupon_code' => $coupon->code]);
    }

    public function removeCoupon(Cart $cart): void
    {
        $cart->update(['coupon_id' => null, 'coupon_code' => null]);
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->update(['coupon_id' => null, 'coupon_code' => null]);
    }
}
