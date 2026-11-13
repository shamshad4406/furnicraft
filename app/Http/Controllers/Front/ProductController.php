<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductBrowser;
use App\Services\RecentlyViewedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(
        protected ProductBrowser $browser,
        protected RecentlyViewedService $recentlyViewed,
    ) {}

    /**
     * All products, also used for header search results.
     */
    public function index(Request $request): View
    {
        $request->validate(ProductBrowser::rules());

        $products = $this->browser->query($request)->paginate(12)->withQueryString();

        return view('front.products', [
            'products' => $products,
            'sorts' => ProductBrowser::SORTS,
            'categories' => Category::query()->active()->roots()->ordered()->get(['id', 'name', 'slug']),
            'filters' => $this->browser->filterOptions(),
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $product = Product::query()
            ->active()
            ->where('slug', $slug)
            ->with([
                'images',
                'variants' => fn ($q) => $q->where('is_active', true),
                'specifications',
                'category.parent',
                'brand',
            ])
            ->firstOrFail();

        $this->recentlyViewed->record($request, $product);

        return view('front.product-show', [
            'product' => $product,
            'related' => $this->related($product),
            'recentlyViewed' => $this->recentlyViewed->products($request, $product),
        ]);
    }

    /**
     * Live search suggestions for the header box.
     */
    public function suggest(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120']]);

        $term = trim((string) $request->query('q', ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['products' => [], 'categories' => []]);
        }

        $products = Product::query()
            ->active()
            ->search($term)
            ->with('images')
            ->orderByDesc('is_featured')
            ->limit(6)
            ->get()
            ->map(fn (Product $p) => [
                'name' => $p->name,
                'sku' => $p->sku,
                'price' => money($p->effective_price),
                'url' => route('product.show', $p->slug),
                'image' => $p->thumbnail_url,
            ]);

        $categories = Category::query()
            ->active()
            ->where('name', 'like', '%'.$term.'%')
            ->limit(4)
            ->get()
            ->map(fn (Category $c) => [
                'name' => $c->name,
                'url' => route('category.show', $c->slug),
            ]);

        return response()->json([
            'products' => $products,
            'categories' => $categories,
            'all_url' => route('products', ['q' => $term]),
        ]);
    }

    /**
     * Other products from the same category, falling back to featured ones
     * so the row is never empty on a thin category.
     */
    protected function related(Product $product)
    {
        $related = Product::query()
            ->active()
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->with(['images', 'category:id,name'])
            ->orderByDesc('is_featured')
            ->limit(4)
            ->get();

        if ($related->count() < 4) {
            $filler = Product::query()
                ->active()
                ->whereKeyNot($product->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->with(['images', 'category:id,name'])
                ->orderByDesc('is_featured')
                ->limit(4 - $related->count())
                ->get();

            $related = $related->concat($filler);
        }

        return $related;
    }
}
