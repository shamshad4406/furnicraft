<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Support\BundledArt;
use Illuminate\Database\Seeder;

/**
 * Attaches the bundled placeholder artwork to the demo catalog so the
 * storefront and admin lists are not full of empty image boxes.
 *
 * These are flat SVG illustrations, not photographs — the client replaces
 * them with real product photos, which then go through ImageService and are
 * stored as optimised WebP. Safe to re-run: rows that already have an image
 * are skipped.
 */
class CatalogDemoImageSeeder extends Seeder
{
    /** Top-level category slug => artwork in public/images. */
    protected array $categoryArt = [
        'steel-furniture' => 'images/categories/steel-almirah.svg',
        'sofa' => 'images/categories/sofa.svg',
        'bed' => 'images/categories/bed.svg',
        'chair' => 'images/categories/chair.svg',
        'table' => 'images/categories/table.svg',
        'office-furniture' => 'images/categories/office-chair.svg',
    ];

    /** Products that have their own artwork, by SKU. */
    protected array $productArt = [
        'STL-10001' => 'images/products/steel-almirah.svg',
        'SOF-20001' => 'images/products/sofa-blue.svg',
        'BED-30001' => 'images/products/double-bed.svg',
        'OFC-60001' => 'images/products/office-chair.svg',
        'TAB-50001' => 'images/products/dining-set.svg',
    ];

    public function run(): void
    {
        $categories = $this->seedCategoryImages();
        $products = $this->seedProductImages();

        $this->command?->info("Attached demo artwork to {$categories} categories and {$products} products.");
    }

    protected function seedCategoryImages(): int
    {
        $count = 0;

        foreach ($this->categoryArt as $slug => $art) {
            $category = Category::where('slug', $slug)->first();
            $source = public_path($art);

            if (! $category || $category->image || ! is_file($source)) {
                continue;
            }

            // Referenced in place, not copied into the uploads directory.
            $category->image = BundledArt::reference($art);
            $category->saveQuietly();
            $count++;
        }

        return $count;
    }

    protected function seedProductImages(): int
    {
        $count = 0;

        $products = Product::with('category.parent')
            ->whereDoesntHave('images')
            ->get();

        foreach ($products as $product) {
            // Sub-categories inherit their parent's artwork.
            $root = $product->category?->parent ?? $product->category;

            $art = $this->productArt[$product->sku]
                ?? $this->categoryArt[$root?->slug]
                ?? null;

            $source = $art ? public_path($art) : null;

            if (! $source || ! is_file($source)) {
                continue;
            }

            $path = BundledArt::reference($art);

            $product->images()->create([
                'path' => $path,
                'thumbnail_path' => $path,
                'original_name' => basename($art),
                'alt_text' => $product->name,
                'width' => 400,
                'height' => 240,
                'size' => filesize($source),
                'sort_order' => 0,
                'is_primary' => true,
            ]);

            $count++;
        }

        return $count;
    }
}
