<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ProductImageController extends Controller implements HasMiddleware
{
    public function __construct(protected ImageService $images) {}

    public static function middleware(): array
    {
        return [new Middleware('permission:products.edit')];
    }

    /**
     * Upload one or more images onto an existing product.
     */
    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:8192'],
        ], [], ['images' => 'images']);

        $nextOrder = (int) $product->images()->max('sort_order') + 1;
        $hasPrimary = $product->images()->where('is_primary', true)->exists();
        $count = 0;

        foreach ($request->file('images') as $file) {
            $stored = $this->images->storeOptimised($file, 'products/'.$product->id, $product->slug);

            $product->images()->create($stored + [
                'alt_text' => $product->name,
                'sort_order' => $nextOrder++,
                'is_primary' => ! $hasPrimary,
            ]);

            $hasPrimary = true;
            $count++;
        }

        return back()->with('success', $count.' image'.($count === 1 ? '' : 's').' uploaded and optimised to WebP.');
    }

    public function update(Request $request, Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:160'],
        ]);

        $image->update($data);

        return back()->with('success', 'Image details saved.');
    }

    /**
     * Make one image the primary one for the product.
     */
    public function primary(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        DB::transaction(function () use ($product, $image) {
            $product->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        });

        return back()->with('success', 'Primary image updated.');
    }

    public function destroy(Product $product, ProductImage $image): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);

        $wasPrimary = $image->is_primary;
        $image->delete(); // the model removes its files

        // Promote the next image so a product is never left without a primary.
        if ($wasPrimary) {
            $product->images()->orderBy('sort_order')->first()?->update(['is_primary' => true]);
        }

        return back()->with('success', 'Image removed.');
    }

    /**
     * Persist the drag-and-drop order. Called by the admin JS.
     */
    public function reorder(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        $ids = $product->images()->pluck('id')->all();

        DB::transaction(function () use ($data, $product, $ids) {
            foreach ($data['order'] as $position => $id) {
                if (in_array((int) $id, $ids, true)) {
                    $product->images()->whereKey($id)->update(['sort_order' => $position]);
                }
            }
        });

        return response()->json(['status' => 'ok']);
    }
}
