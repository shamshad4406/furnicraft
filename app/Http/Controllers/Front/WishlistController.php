<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function __construct(protected CartService $cart) {}

    public function index(Request $request): View
    {
        return view('front.wishlist', [
            'products' => $this->query($request)
                ->with(['product.images', 'product.category:id,name'])
                ->latest('id')
                ->get()
                ->pluck('product')
                ->filter(fn (?Product $p) => $p && $p->status === 'active')
                ->values(),
        ]);
    }

    /**
     * Toggle from the heart button. Returns JSON for the inline control and a
     * redirect for a plain form submit.
     */
    public function toggle(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'integer', 'exists:products,id']]);

        $existing = $this->query($request)->where('product_id', $data['product_id'])->first();

        if ($existing) {
            $existing->delete();
            $added = false;
        } else {
            Wishlist::create($this->owner($request) + ['product_id' => $data['product_id']]);
            $added = true;
        }

        $count = $this->query($request)->count();

        if ($request->expectsJson()) {
            return response()->json(['added' => $added, 'count' => $count]);
        }

        return back()->with('success', $added ? 'Saved to your wishlist.' : 'Removed from your wishlist.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->query($request)->where('product_id', $product->id)->delete();

        return back()->with('success', $product->name.' removed from your wishlist.');
    }

    /**
     * Wishlist → cart in one step.
     */
    public function moveToCart(Request $request, Product $product): RedirectResponse
    {
        $result = $this->cart->add($request, $product, null, 1);

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        $this->query($request)->where('product_id', $product->id)->delete();

        return redirect()->route('cart.index')->with('success', $product->name.' moved to your cart.');
    }

    /**
     * Sign-in: adopt the guest wishlist.
     *
     * Takes the pre-sign-in session id: signing in regenerates the session,
     * while the guest rows stay keyed on the id they were written under.
     */
    public static function mergeGuest(string $guestSessionId, int $customerId): void
    {
        $guestRows = Wishlist::whereNull('customer_id')->where('session_id', $guestSessionId)->get();

        foreach ($guestRows as $row) {
            $alreadySaved = Wishlist::where('customer_id', $customerId)
                ->where('product_id', $row->product_id)
                ->exists();

            $alreadySaved
                ? $row->delete()
                : $row->update(['customer_id' => $customerId, 'session_id' => null]);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Wishlist>
     */
    protected function query(Request $request)
    {
        $customerId = auth('customer')->id();

        return $customerId
            ? Wishlist::where('customer_id', $customerId)
            : Wishlist::whereNull('customer_id')->where('session_id', $request->session()->getId());
    }

    /**
     * @return array<string, mixed>
     */
    protected function owner(Request $request): array
    {
        return auth('customer')->check()
            ? ['customer_id' => auth('customer')->id(), 'session_id' => null]
            : ['customer_id' => null, 'session_id' => $request->session()->getId()];
    }
}
