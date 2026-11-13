<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Shared storefront product query: used by the category pages and the
 * all-products listing so both filter and sort identically.
 */
class ProductBrowser
{
    public const SORTS = [
        'featured' => 'Featured',
        'newest' => 'Newest first',
        'price_low' => 'Price: low to high',
        'price_high' => 'Price: high to low',
        'name' => 'Name: A to Z',
    ];

    /** Filter keys the listing understands, for building "clear" links. */
    public const FILTER_KEYS = [
        'q', 'category', 'brand', 'material', 'color', 'size',
        'min_price', 'max_price', 'discount', 'in_stock',
        'best_seller', 'new_arrival',
    ];

    /**
     * Rules for the listing query string. Everything here reaches the database
     * through bound parameters, but bounding the input keeps a crafted URL from
     * forcing an expensive scan.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:180'],
            'brand' => ['nullable', 'string', 'max:180'],
            'material' => ['nullable', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:60'],
            'size' => ['nullable', 'string', 'max:60'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'discount' => ['nullable', 'boolean'],
            'in_stock' => ['nullable', 'boolean'],
            'best_seller' => ['nullable', 'boolean'],
            'new_arrival' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::SORTS))],
            'page' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @param  Category|null  $category  limits to this category and its descendants
     */
    public function query(Request $request, ?Category $category = null): Builder
    {
        $query = Product::query()
            ->active()
            ->with(['images', 'category:id,name,slug']);

        $this->applyCategory($query, $request, $category);

        if ($request->filled('q')) {
            $query->search((string) $request->input('q'));
        }

        if ($request->filled('brand')) {
            $query->whereHas('brand', fn ($b) => $b->where('slug', $request->input('brand')));
        }

        if ($request->filled('material')) {
            $query->where('material', $request->input('material'));
        }

        if ($request->filled('color')) {
            $query->where('color', $request->input('color'));
        }

        // Size lives on the variants, not the product.
        if ($request->filled('size')) {
            $query->whereHas('variants', fn ($v) => $v->where('is_active', true)->where('size', $request->input('size')));
        }

        // Price filters compare the price the customer actually pays.
        if ($request->filled('min_price')) {
            $query->whereRaw('COALESCE(sale_price, price) >= ?', [(float) $request->input('min_price')]);
        }

        if ($request->filled('max_price')) {
            $query->whereRaw('COALESCE(sale_price, price) <= ?', [(float) $request->input('max_price')]);
        }

        if ($request->boolean('best_seller')) {
            $query->where('is_best_seller', true);
        }

        if ($request->boolean('new_arrival')) {
            $query->where('is_new_arrival', true);
        }

        if ($request->boolean('discount')) {
            $query->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price');
        }

        if ($request->boolean('in_stock')) {
            $query->where(function (Builder $q) {
                $q->where('stock_quantity', '>', 0)
                    ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where('stock_quantity', '>', 0));
            });
        }

        return $this->applySort($query, (string) $request->input('sort', 'featured'));
    }

    protected function applyCategory(Builder $query, Request $request, ?Category $category): void
    {
        if ($category) {
            $query->whereIn('category_id', $category->descendantIds());

            return;
        }

        if ($request->filled('category')) {
            $chosen = Category::query()->active()->where('slug', $request->input('category'))->first();
            $query->whereIn('category_id', $chosen ? $chosen->descendantIds() : [0]);
        }
    }

    protected function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'newest' => $query->orderByDesc('id'),
            'price_low' => $query->orderByRaw('COALESCE(sale_price, price) ASC')->orderBy('id'),
            'price_high' => $query->orderByRaw('COALESCE(sale_price, price) DESC')->orderBy('id'),
            'name' => $query->orderBy('name'),
            // "Featured" puts flagged products first, then the staff sort order.
            default => $query->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('id'),
        };
    }

    /**
     * Values that actually exist in the catalog, so the sidebar never offers a
     * filter that would return nothing. Cached — it only changes when staff
     * edit the catalog.
     *
     * @return array{materials: array<int, string>, colors: array<int, string>, sizes: array<int, string>, price: array{min: int, max: int}}
     */
    public function filterOptions(): array
    {
        return Cache::remember('front.filter-options', now()->addMinutes(15), function () {
            $materials = Product::query()->active()
                ->whereNotNull('material')->where('material', '!=', '')
                ->distinct()->orderBy('material')->pluck('material')->all();

            $colors = Product::query()->active()
                ->whereNotNull('color')->where('color', '!=', '')
                ->distinct()->orderBy('color')->pluck('color')->all();

            $sizes = DB::table('product_variants')
                ->join('products', 'products.id', '=', 'product_variants.product_id')
                ->where('products.status', 'active')
                ->whereNull('products.deleted_at')
                ->where('product_variants.is_active', true)
                ->whereNotNull('product_variants.size')
                ->where('product_variants.size', '!=', '')
                ->distinct()->orderBy('product_variants.size')
                ->pluck('product_variants.size')->all();

            $bounds = Product::query()->active()
                ->selectRaw('MIN(COALESCE(sale_price, price)) as min_price, MAX(COALESCE(sale_price, price)) as max_price')
                ->first();

            return [
                'materials' => $materials,
                'colors' => $colors,
                'sizes' => $sizes,
                'price' => [
                    'min' => (int) floor((float) ($bounds->min_price ?? 0)),
                    'max' => (int) ceil((float) ($bounds->max_price ?? 0)),
                ],
            ];
        });
    }

    /**
     * True when any filter beyond sorting/pagination is applied.
     */
    public function hasFilters(Request $request): bool
    {
        foreach (self::FILTER_KEYS as $key) {
            if ($request->filled($key)) {
                return true;
            }
        }

        return false;
    }
}
