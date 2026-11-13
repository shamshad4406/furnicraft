<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogService;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller implements HasMiddleware
{
    public function __construct(
        protected CatalogService $catalog,
        protected ImageService $images,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:products.view', only: ['index', 'show']),
            new Middleware('permission:products.create', only: ['create', 'store']),
            new Middleware('permission:products.edit', only: ['edit', 'update']),
            new Middleware('permission:products.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $products = Product::query()
            ->with(['category:id,name', 'brand:id,name', 'primaryImage', 'images'])
            ->withCount('variants')
            ->search($request->input('q'))
            ->when($request->filled('category'), function ($query) use ($request) {
                // Include the sub-categories of the chosen category.
                $category = Category::find($request->input('category'));
                $query->whereIn('category_id', $category ? $category->descendantIds() : [0]);
            })
            ->when($request->filled('brand'), fn ($q) => $q->where('brand_id', $request->input('brand')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('flag'), function ($query) use ($request) {
                $column = match ($request->input('flag')) {
                    'featured' => 'is_featured',
                    'best_seller' => 'is_best_seller',
                    'new_arrival' => 'is_new_arrival',
                    'custom_order' => 'custom_order_allowed',
                    default => null,
                };

                if ($column) {
                    $query->where($column, true);
                }
            })
            ->ordered()
            ->paginate(12)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::query()->ordered()->get(),
            'brands' => Brand::query()->ordered()->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product([
                'status' => 'draft',
                'gst_rate' => settings('gst_default_rate', 18),
                'stock_quantity' => 0,
                'sort_order' => 0,
            ]),
        ] + $this->formOptions());
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $product = DB::transaction(function () use ($request, $data) {
            $product = new Product($this->productAttributes($request, $data));
            $product->save();

            $this->syncVariants($product, $request->input('variants', []));
            $this->syncSpecifications($product, $request->input('specifications', []));
            $this->storeImages($product, $request);

            return $product;
        });

        return redirect()->route('admin.products.edit', $product)
            ->with('success', 'Product "'.$product->name.'" created. Add images and variants below.');
    }

    public function show(Product $product): View
    {
        $product->load(['category', 'brand', 'images', 'variants', 'specifications']);

        return view('admin.products.show', compact('product'));
    }

    public function edit(Product $product): View
    {
        $product->load(['images', 'variants', 'specifications']);

        return view('admin.products.form', ['product' => $product] + $this->formOptions());
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data, $product) {
            $product->fill($this->productAttributes($request, $data, $product));
            $product->save();

            $this->syncVariants($product, $request->input('variants', []));
            $this->syncSpecifications($product, $request->input('specifications', []));
            $this->storeImages($product, $request);
        });

        return redirect()->route('admin.products.index')
            ->with('success', 'Product "'.$product->name.'" updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;

        // Free the slug and SKU so they can be reused, then archive the row.
        $this->catalog->releaseUniqueColumns($product, ['slug', 'sku']);
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product "'.$name.'" removed.');
    }

    /* ----------------------------------------------------------- helpers */

    protected function formOptions(): array
    {
        return [
            'categories' => Category::query()->with('children')->roots()->ordered()->get(),
            'brands' => Brand::query()->active()->ordered()->pluck('name', 'id'),
            'statuses' => Product::STATUSES,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function productAttributes(Request $request, array $data, ?Product $product = null): array
    {
        // validated() omits nullable fields that were not submitted, so every
        // optional key is read with a default.
        $data['slug'] = ($data['slug'] ?? null) ?: $this->catalog->uniqueSlug('products', $data['name'], $product?->id);
        $data['sku'] = ($data['sku'] ?? null) ?: $this->catalog->generateSku($data['name'], $product?->id);

        foreach (['custom_order_allowed', 'is_featured', 'is_best_seller', 'is_new_arrival'] as $flag) {
            $data[$flag] = $request->boolean($flag);
        }

        $data['sale_price'] = ($data['sale_price'] ?? '') === '' ? null : $data['sale_price'];
        $data['stock_quantity'] = (int) ($data['stock_quantity'] ?? 0);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        unset($data['variants'], $data['specifications'], $data['images']);

        return $data;
    }

    /**
     * Replace the variant set with what the form submitted. Rows the user
     * cleared out are deleted; the rest are created or updated in place.
     */
    protected function syncVariants(Product $product, array $rows): void
    {
        $keep = [];

        foreach (array_values($rows) as $index => $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            $size = trim((string) ($row['size'] ?? ''));
            $color = trim((string) ($row['color'] ?? ''));

            if ($sku === '' && $size === '' && $color === '') {
                continue; // blank template row
            }

            $attributes = [
                'sku' => $sku !== '' ? $sku : $this->catalog->uniqueVariantSku($product->sku.'-'.($index + 1)),
                'size' => $size ?: null,
                'color' => $color ?: null,
                'price' => ($row['price'] ?? '') === '' ? null : $row['price'],
                'sale_price' => ($row['sale_price'] ?? '') === '' ? null : $row['sale_price'],
                'stock_quantity' => (int) ($row['stock_quantity'] ?? 0),
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sort_order' => $index,
            ];

            $variant = ! empty($row['id'])
                ? $product->variants()->whereKey($row['id'])->first()
                : null;

            if ($variant) {
                $variant->update($attributes);
            } else {
                $variant = $product->variants()->create($attributes);
            }

            $keep[] = $variant->id;
        }

        $product->variants()->whereNotIn('id', $keep ?: [0])->delete();
    }

    protected function syncSpecifications(Product $product, array $rows): void
    {
        $product->specifications()->delete();

        $order = 0;

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));

            if ($name === '' || $value === '') {
                continue;
            }

            $product->specifications()->create([
                'name' => $name,
                'value' => $value,
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * Optimise and attach any newly uploaded images.
     */
    protected function storeImages(Product $product, Request $request): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        $nextOrder = (int) $product->images()->max('sort_order') + 1;
        $hasPrimary = $product->images()->where('is_primary', true)->exists();

        foreach ($request->file('images') as $file) {
            $stored = $this->images->storeOptimised($file, 'products/'.$product->id, $product->slug);

            $product->images()->create($stored + [
                'alt_text' => $product->name,
                'sort_order' => $nextOrder++,
                'is_primary' => ! $hasPrimary,
            ]);

            $hasPrimary = true;
        }
    }
}
