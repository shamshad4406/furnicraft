<?php

namespace App\Services;

use App\Models\Product;
use App\Models\RecentlyViewed;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Tracks the products a visitor has looked at, keyed by session for guests and
 * additionally by customer id once they sign in.
 */
class RecentlyViewedService
{
    /** How many to keep per visitor. */
    public const KEEP = 8;

    public function record(Request $request, Product $product): void
    {
        $sessionId = $request->session()->getId();

        RecentlyViewed::updateOrCreate(
            ['session_id' => $sessionId, 'product_id' => $product->id],
            [
                'customer_id' => auth('customer')->id(),
                'viewed_at' => now(),
            ]
        );

        $this->trim($sessionId);
    }

    /**
     * @return Collection<int, Product>
     */
    public function products(Request $request, ?Product $except = null): Collection
    {
        $ids = RecentlyViewed::query()
            ->where('session_id', $request->session()->getId())
            ->when($except, fn ($q) => $q->where('product_id', '!=', $except->id))
            // id breaks ties when two products are opened in the same instant.
            ->orderByDesc('viewed_at')
            ->orderByDesc('id')
            ->limit(self::KEEP)
            ->pluck('product_id');

        if ($ids->isEmpty()) {
            return new Collection();
        }

        $products = Product::query()
            ->active()
            ->whereIn('id', $ids)
            ->with(['images', 'category:id,name'])
            ->get();

        // Preserve "most recent first" rather than database order.
        return $products->sortBy(fn (Product $p) => $ids->search($p->id))->values();
    }

    /**
     * Keep the list short so the table does not grow without bound.
     */
    protected function trim(string $sessionId): void
    {
        $surplus = RecentlyViewed::query()
            ->where('session_id', $sessionId)
            ->orderByDesc('viewed_at')
            ->orderByDesc('id')
            ->pluck('id')
            ->slice(self::KEEP);

        if ($surplus->isNotEmpty()) {
            RecentlyViewed::whereIn('id', $surplus)->delete();
        }
    }
}
