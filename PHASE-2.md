# FurniCraft — Phase 2: Catalog Management

Status: **complete**. Builds on [Phase 1](PHASE-1.md).

## What was built

| Module | Where |
|---|---|
| Category tree (parent/child, image, SEO fields, sort order, active) | `app/Http/Controllers/Admin/CategoryController.php`, `resources/views/admin/categories` |
| Brand management (logo, slug, sort order, active) | `app/Http/Controllers/Admin/BrandController.php`, `resources/views/admin/brands` |
| Product CRUD — name, slug, SKU, description, material, dimensions, weight, warranty, HSN, GST %, price, sale price, status | `app/Http/Controllers/Admin/ProductController.php` |
| Product images — multi-upload, drag-sort, primary image, alt text, auto WebP + thumbnails | `app/Http/Controllers/Admin/ProductImageController.php`, `app/Services/ImageService.php` |
| Product variants — size/colour with own SKU, price and stock | `app/Models/ProductVariant.php`, product form variant table |
| Specifications — key/value rows per product | `app/Models/ProductSpecification.php` |
| Bulk import / export — CSV with per-row validation report | `app/Services/ProductImportService.php`, `app/Http/Controllers/Admin/ProductImportController.php` |
| Product options — custom order allowed, featured, best seller, new arrival | `products` table flags |

Tables added: `categories`, `brands`, `products`, `product_images`, `product_variants`, `product_specifications`.

## Exit criteria

- [x] **Duplicate SKU or slug blocked** — unique DB indexes plus form rules on products, variants, categories and brands. Duplicates inside one variant form, and against the rest of the catalog, are both caught. Soft-deleting a product releases its slug/SKU (suffixed with `-del{id}`) so they can be reused.
- [x] **Images auto-optimised** — every upload is converted to WebP via GD, capped at 1600px on the longest edge, with a 400px thumbnail. EXIF rotation is applied and PNG transparency is preserved. Verified end to end: a 2400×1800 JPEG stored as a 1600×1200 `image/webp` plus thumbnail.
- [x] **Import of 100 products succeeds with error report** — covered by a test that imports 100 rows with zero failures, and by tests for unknown categories, bad prices, duplicate SKUs in-file, and missing required columns.
- [x] Admin can add a full product with variants and images.
- [x] Sample catalog loaded: **36 products**, 18 categories, 4 brands, 13 variants, 216 specifications.

80 feature tests pass (39 from Phase 1, 41 new).

## Bulk import

Admin → Products → **Bulk Import**. Download the template or export the current catalog, edit, upload.

- Header row is matched **by name**, so column order and letter case do not matter.
- Required columns: `name`, `sku`, `category`, `price`.
- `category` and `brand` match on name or slug and **must already exist** — the importer never invents them.
- Valid rows import even when other rows fail; every rejected row is listed with its line number and reason.
- Tick *update existing* to overwrite products whose SKU already exists; leave it off and those rows are rejected instead.
- Images and variants are not part of the CSV — add those on the product screen.
- Cap: 2,000 rows / 10 MB per upload.

Exports are UTF-8 with a BOM so `₹` and accented names open correctly in Excel.

## Storefront

The public site already reads the live catalog:

- **Homepage** — "Our Categories" lists active top-level categories; "Featured Products" lists active products flagged featured, ordered by `sort_order` then id, with sale prices struck through.
- **`/categories`** — real category tree with sub-categories and active product counts.
- **`/categories/{slug}`** — one category's products, including everything in its sub-categories. Sub-categories appear as filter chips; an inactive or unknown slug 404s.
- **`/products`** — all active products, filterable by category and brand, and the target of the header search box (`?q=` matches name, SKU, slug, material and variant SKU).
- Both listings share `app/Services/ProductBrowser.php`, so they sort and filter identically: featured, newest, price low→high, price high→low, name. Price sorting uses `COALESCE(sale_price, price)`, so a discounted product sorts at the price customers actually pay. 12 per page.
- **Header dropdown** — live categories linking to their own pages, cached for 10 minutes (`front.nav.categories`).

Still Phase 3: price/material/colour filters, live search suggestions, the product detail page and the pincode check.

`ShowcaseCatalog` now only holds the hero slides and the trust strip — static content until the banner module in Phase 8.

Demo artwork is attached to the seeded categories and products by `CatalogDemoImageSeeder` (flat SVGs, so the lists are not full of empty boxes). Real photos uploaded by staff go through `ImageService` and are stored as optimised WebP.

## Fixes made during this phase

- **`x-admin.select` rendered labels as option values.** The component used `is_int($key)` to decide whether an options array was a list, but `pluck('name', 'id')` returns integer keys — so the parent-category and brand selects submitted a *name* where an *id* was expected. Opening and saving a sub-category silently cleared its parent. Now decided with `array_is_list()`, and covered by `tests/Feature/Admin/SelectComponentTest.php`.
- **A Blade directive glued to a word character is not compiled.** `...found@else` stayed as literal text, so the whole else branch was swallowed and the result count rendered empty. Blade's directive pattern requires a non-word character before `@`. The toolbar now builds its label in PHP; `grep -rnoE "[A-Za-z0-9_]@(if|else|endif|foreach|...)" resources/views/` checks for the same mistake elsewhere.
- **`@section('name', null)` leaks an output buffer.** `Str::limit(null, 150)` returns `null`, and Blade's two-argument `@section` only skips the buffer when the value is non-null — so a category with no description left a buffer open on every page render. Meta values are now cast to string.
- **Uploaded images 404'd when the host differed from `APP_URL`.** `Storage::url()` built absolute URLs from `APP_URL`, so images broke on the dev server port, a LAN IP or staging. The public disk URL is now host-relative (`/storage`), overridable with `FILESYSTEM_PUBLIC_URL`.

## Notes for the next phases

- `Product` already has `scopeActive`, `scopeFeatured`, `scopeSearch`, `effective_price`, `discount_percent` and `thumbnail_url` for the Phase 3 listing pages.
- **Stock is provisional.** `products.stock_quantity` (simple products) and `product_variants.stock_quantity` are plain columns with no movement history. Phase 7 replaces `Product::getTotalStockAttribute()` with the inventory module; nothing decrements stock yet.
- Product `meta_title` / `meta_description` are captured now; Phase 8 wires them into the page head along with schema and Open Graph.
- Variant stock overrides the product's own quantity whenever a product has variants — see `getTotalStockAttribute()`.
- The importer is synchronous. If a client needs 10,000-row files later, move it to a queued job (`QUEUE_CONNECTION` is already `database`).
