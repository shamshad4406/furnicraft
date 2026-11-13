<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Demo catalog for the Phase 2 hand-over: the six storefront categories with
 * sub-categories, a few brands, and 36 products with specifications and
 * variants. Safe to re-run — rows are matched on slug / SKU.
 */
class CatalogSeeder extends Seeder
{
    /** @var array<string, string[]> parent => children */
    protected array $tree = [
        'Steel Furniture' => ['Almirah', 'Office Storage'],
        'Sofa' => ['Fabric Sofa', 'Leather Sofa'],
        'Bed' => ['Single Bed', 'Double Bed'],
        'Chair' => ['Dining Chair', 'Accent Chair'],
        'Table' => ['Dining Table', 'Coffee Table'],
        'Office Furniture' => ['Office Chair', 'Workstation'],
    ];

    protected array $brands = ['FurniCraft Originals', 'Teakwood Studio', 'UrbanNest', 'SteelLine'];

    public function run(): void
    {
        $catalog = app(CatalogService::class);

        $categoryIds = $this->seedCategories();
        $brandIds = $this->seedBrands();

        $created = 0;

        foreach ($this->products() as $row) {
            if (Product::withTrashed()->where('sku', $row['sku'])->exists()) {
                continue;
            }

            $product = new Product([
                'category_id' => $categoryIds[$row['category']],
                'brand_id' => $brandIds[$row['brand']] ?? null,
                'name' => $row['name'],
                'short_description' => $row['short'],
                'description' => $row['short'].' Built by FurniCraft craftsmen and finished to last, '
                    .'with a '.$row['warranty'].' warranty against manufacturing defects.',
                'material' => $row['material'],
                'color' => $row['color'],
                'dimensions' => $row['dimensions'],
                'weight' => $row['weight'],
                'warranty' => $row['warranty'],
                'hsn_code' => $row['hsn'],
                'gst_rate' => 18,
                'price' => $row['price'],
                'sale_price' => $row['sale'] ?? null,
                'stock_quantity' => $row['stock'],
                'status' => 'active',
                'is_featured' => $row['featured'] ?? false,
                'is_best_seller' => $row['best'] ?? false,
                'is_new_arrival' => $row['new'] ?? false,
                'custom_order_allowed' => $row['custom'] ?? false,
                'meta_title' => $row['name'].' — FurniCraft',
                'meta_description' => Str::limit($row['short'], 150),
            ]);

            $product->sku = $row['sku'];
            $product->slug = $catalog->uniqueSlug('products', $row['name']);
            $product->save();

            $this->seedSpecifications($product, $row);
            $this->seedVariants($product, $row);

            $created++;
        }

        $this->command?->info("Seeded {$created} products across ".count($categoryIds).' categories.');
    }

    /**
     * @return array<string, int> category name => id
     */
    protected function seedCategories(): array
    {
        $ids = [];
        $order = 0;

        foreach ($this->tree as $parentName => $children) {
            $parent = Category::firstOrCreate(
                ['slug' => Str::slug($parentName)],
                [
                    'name' => $parentName,
                    'description' => $parentName.' for every Indian home and office.',
                    'meta_title' => $parentName.' — FurniCraft',
                    'meta_description' => 'Browse our range of '.Str::lower($parentName).'.',
                    'sort_order' => $order++,
                    'is_active' => true,
                ]
            );

            $ids[$parentName] = $parent->id;
            $childOrder = 0;

            foreach ($children as $childName) {
                $child = Category::firstOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childName,
                        'sort_order' => $childOrder++,
                        'is_active' => true,
                    ]
                );

                $ids[$childName] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, int>
     */
    protected function seedBrands(): array
    {
        $ids = [];
        $order = 0;

        foreach ($this->brands as $name) {
            $brand = Brand::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $order++, 'is_active' => true]
            );

            $ids[$name] = $brand->id;
        }

        return $ids;
    }

    protected function seedSpecifications(Product $product, array $row): void
    {
        $specs = [
            ['Material', $row['material']],
            ['Dimensions (W x D x H)', $row['dimensions']],
            ['Primary Colour', $row['color']],
            ['Weight', $row['weight'].' kg'],
            ['Warranty', $row['warranty']],
            ['Assembly', $row['assembly'] ?? 'Carpenter assembly included'],
        ];

        foreach ($specs as $i => [$name, $value]) {
            $product->specifications()->create([
                'name' => $name,
                'value' => (string) $value,
                'sort_order' => $i,
            ]);
        }
    }

    protected function seedVariants(Product $product, array $row): void
    {
        if (empty($row['variants'])) {
            return;
        }

        foreach (array_values($row['variants']) as $i => [$size, $color, $delta, $stock]) {
            $product->variants()->create([
                'sku' => $product->sku.'-'.Str::upper(Str::substr(Str::slug($size.$color, ''), 0, 4)).($i + 1),
                'size' => $size ?: null,
                'color' => $color ?: null,
                'price' => $delta ? round($product->price + $delta, 2) : null,
                'stock_quantity' => $stock,
                'is_active' => true,
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * 36 demo products.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function products(): array
    {
        return [
            // ---------------------------------------------------- Almirah
            ['sku' => 'STL-10001', 'name' => 'Steel Almirah 3 Door', 'category' => 'Almirah', 'brand' => 'SteelLine',
             'short' => 'Powder-coated three-door steel almirah with a locker and hanging rail.',
             'material' => 'CRCA Steel', 'color' => 'Grey', 'dimensions' => '122 x 48 x 198 cm', 'weight' => 62,
             'warranty' => '5 Years', 'hsn' => '94032010', 'price' => 14500, 'sale' => 12500, 'stock' => 18,
             'featured' => true, 'best' => true,
             'variants' => [['3 Door', 'Grey', 0, 10], ['3 Door', 'Beige', 500, 8]]],

            ['sku' => 'STL-10002', 'name' => 'Steel Almirah 2 Door', 'category' => 'Almirah', 'brand' => 'SteelLine',
             'short' => 'Compact two-door almirah that fits smaller bedrooms.',
             'material' => 'CRCA Steel', 'color' => 'Grey', 'dimensions' => '92 x 48 x 183 cm', 'weight' => 48,
             'warranty' => '5 Years', 'hsn' => '94032010', 'price' => 10500, 'stock' => 22],

            ['sku' => 'STL-10003', 'name' => 'Steel Locker 6 Compartment', 'category' => 'Office Storage', 'brand' => 'SteelLine',
             'short' => 'Six-compartment staff locker with individual latches.',
             'material' => 'CRCA Steel', 'color' => 'Blue Grey', 'dimensions' => '90 x 45 x 180 cm', 'weight' => 55,
             'warranty' => '3 Years', 'hsn' => '94032010', 'price' => 16800, 'stock' => 9],

            ['sku' => 'STL-10004', 'name' => 'Filing Cabinet 4 Drawer', 'category' => 'Office Storage', 'brand' => 'SteelLine',
             'short' => 'Four-drawer filing cabinet with a central locking system.',
             'material' => 'CRCA Steel', 'color' => 'Dark Grey', 'dimensions' => '47 x 62 x 132 cm', 'weight' => 41,
             'warranty' => '3 Years', 'hsn' => '94032010', 'price' => 12900, 'sale' => 11500, 'stock' => 14],

            ['sku' => 'STL-10005', 'name' => 'Steel Book Rack', 'category' => 'Office Storage', 'brand' => 'SteelLine',
             'short' => 'Open five-shelf steel rack for files and books.',
             'material' => 'CRCA Steel', 'color' => 'Grey', 'dimensions' => '90 x 30 x 180 cm', 'weight' => 34,
             'warranty' => '3 Years', 'hsn' => '94032010', 'price' => 8900, 'stock' => 16, 'new' => true],

            ['sku' => 'STL-10006', 'name' => 'Steel Shoe Cabinet', 'category' => 'Steel Furniture', 'brand' => 'SteelLine',
             'short' => 'Ventilated shoe cabinet with three tilting drawers.',
             'material' => 'CRCA Steel', 'color' => 'Ivory', 'dimensions' => '80 x 24 x 100 cm', 'weight' => 21,
             'warranty' => '2 Years', 'hsn' => '94032010', 'price' => 6400, 'stock' => 25],

            // ------------------------------------------------------- Sofa
            ['sku' => 'SOF-20001', 'name' => '3 Seater Fabric Sofa', 'category' => 'Fabric Sofa', 'brand' => 'UrbanNest',
             'short' => 'Three-seater sofa with high-density foam and a solid wood frame.',
             'material' => 'Sheesham Wood + Fabric', 'color' => 'Beige', 'dimensions' => '198 x 86 x 82 cm', 'weight' => 54,
             'warranty' => '3 Years', 'hsn' => '94016100', 'price' => 22999, 'sale' => 18999, 'stock' => 12,
             'featured' => true, 'best' => true, 'custom' => true,
             'variants' => [['3 Seater', 'Beige', 0, 6], ['3 Seater', 'Blue', 1200, 4], ['2 Seater', 'Beige', -5000, 7]]],

            ['sku' => 'SOF-20002', 'name' => '2 Seater Fabric Sofa', 'category' => 'Fabric Sofa', 'brand' => 'UrbanNest',
             'short' => 'Compact two-seater for apartments and study rooms.',
             'material' => 'Sheesham Wood + Fabric', 'color' => 'Grey', 'dimensions' => '148 x 86 x 82 cm', 'weight' => 41,
             'warranty' => '3 Years', 'hsn' => '94016100', 'price' => 15999, 'stock' => 15],

            ['sku' => 'SOF-20003', 'name' => 'L Shape Corner Sofa', 'category' => 'Fabric Sofa', 'brand' => 'UrbanNest',
             'short' => 'Five-seater L-shaped sofa with a reversible chaise.',
             'material' => 'Hardwood + Fabric', 'color' => 'Charcoal', 'dimensions' => '265 x 180 x 84 cm', 'weight' => 92,
             'warranty' => '3 Years', 'hsn' => '94016100', 'price' => 42999, 'sale' => 37999, 'stock' => 6,
             'featured' => true, 'custom' => true],

            ['sku' => 'SOF-20004', 'name' => 'Leather Recliner Sofa', 'category' => 'Leather Sofa', 'brand' => 'UrbanNest',
             'short' => 'Two-seater recliner in top-grain leather with a manual lever.',
             'material' => 'Top Grain Leather', 'color' => 'Tan Brown', 'dimensions' => '160 x 95 x 100 cm', 'weight' => 78,
             'warranty' => '5 Years', 'hsn' => '94016100', 'price' => 58999, 'stock' => 4, 'new' => true],

            ['sku' => 'SOF-20005', 'name' => 'Leather Chesterfield Sofa', 'category' => 'Leather Sofa', 'brand' => 'Teakwood Studio',
             'short' => 'Classic deep-buttoned Chesterfield with rolled arms.',
             'material' => 'Genuine Leather', 'color' => 'Oxblood', 'dimensions' => '210 x 90 x 76 cm', 'weight' => 86,
             'warranty' => '5 Years', 'hsn' => '94016100', 'price' => 74999, 'stock' => 3, 'custom' => true],

            ['sku' => 'SOF-20006', 'name' => 'Sofa Cum Bed', 'category' => 'Sofa', 'brand' => 'UrbanNest',
             'short' => 'Converts from a three-seater sofa to a double bed in one pull.',
             'material' => 'Engineered Wood + Fabric', 'color' => 'Olive Green', 'dimensions' => '190 x 90 x 80 cm', 'weight' => 62,
             'warranty' => '2 Years', 'hsn' => '94016100', 'price' => 27999, 'sale' => 24499, 'stock' => 8, 'best' => true],

            // -------------------------------------------------------- Bed
            ['sku' => 'BED-30001', 'name' => 'Double Bed with Storage', 'category' => 'Double Bed', 'brand' => 'Teakwood Studio',
             'short' => 'Queen bed with a hydraulic lift and full-width storage box.',
             'material' => 'Sheesham Wood', 'color' => 'Walnut Brown', 'dimensions' => '198 x 152 x 110 cm', 'weight' => 96,
             'warranty' => '5 Years', 'hsn' => '94035000', 'price' => 27999, 'sale' => 22999, 'stock' => 10,
             'featured' => true, 'best' => true, 'custom' => true,
             'variants' => [['Queen', 'Walnut Brown', 0, 6], ['King', 'Walnut Brown', 6000, 4]]],

            ['sku' => 'BED-30002', 'name' => 'King Size Platform Bed', 'category' => 'Double Bed', 'brand' => 'Teakwood Studio',
             'short' => 'Low-profile king platform bed with an upholstered headboard.',
             'material' => 'Solid Teak', 'color' => 'Honey Oak', 'dimensions' => '213 x 183 x 95 cm', 'weight' => 112,
             'warranty' => '5 Years', 'hsn' => '94035000', 'price' => 46999, 'stock' => 5, 'new' => true],

            ['sku' => 'BED-30003', 'name' => 'Single Bed with Drawers', 'category' => 'Single Bed', 'brand' => 'FurniCraft Originals',
             'short' => 'Single bed with two pull-out drawers, ideal for kids rooms.',
             'material' => 'Engineered Wood', 'color' => 'Maple', 'dimensions' => '190 x 92 x 85 cm', 'weight' => 48,
             'warranty' => '3 Years', 'hsn' => '94035000', 'price' => 14999, 'sale' => 12999, 'stock' => 20],

            ['sku' => 'BED-30004', 'name' => 'Bunk Bed', 'category' => 'Single Bed', 'brand' => 'FurniCraft Originals',
             'short' => 'Space-saving bunk bed with a ladder and safety rail.',
             'material' => 'Mango Wood', 'color' => 'White', 'dimensions' => '198 x 98 x 165 cm', 'weight' => 68,
             'warranty' => '3 Years', 'hsn' => '94035000', 'price' => 24999, 'stock' => 7, 'new' => true],

            ['sku' => 'BED-30005', 'name' => 'Bedside Table', 'category' => 'Bed', 'brand' => 'Teakwood Studio',
             'short' => 'Two-drawer bedside table that matches our bed range.',
             'material' => 'Sheesham Wood', 'color' => 'Walnut Brown', 'dimensions' => '45 x 40 x 55 cm', 'weight' => 12,
             'warranty' => '3 Years', 'hsn' => '94035000', 'price' => 6499, 'stock' => 30],

            ['sku' => 'BED-30006', 'name' => 'Wooden Wardrobe 3 Door', 'category' => 'Bed', 'brand' => 'Teakwood Studio',
             'short' => 'Three-door wardrobe with a mirror and internal drawers.',
             'material' => 'Sheesham Wood', 'color' => 'Walnut Brown', 'dimensions' => '150 x 60 x 210 cm', 'weight' => 128,
             'warranty' => '5 Years', 'hsn' => '94035000', 'price' => 38999, 'sale' => 34999, 'stock' => 6, 'custom' => true],

            // ------------------------------------------------------ Chair
            ['sku' => 'CHR-40001', 'name' => 'Wooden Dining Chair', 'category' => 'Dining Chair', 'brand' => 'Teakwood Studio',
             'short' => 'Solid wood dining chair with a cushioned seat.',
             'material' => 'Sheesham Wood', 'color' => 'Natural Teak', 'dimensions' => '45 x 50 x 95 cm', 'weight' => 7,
             'warranty' => '2 Years', 'hsn' => '94016900', 'price' => 3499, 'stock' => 60, 'best' => true,
             'variants' => [['Standard', 'Natural Teak', 0, 35], ['Standard', 'Walnut', 300, 25]]],

            ['sku' => 'CHR-40002', 'name' => 'Upholstered Dining Chair', 'category' => 'Dining Chair', 'brand' => 'UrbanNest',
             'short' => 'Fabric-wrapped dining chair with tapered legs.',
             'material' => 'Rubber Wood + Fabric', 'color' => 'Dove Grey', 'dimensions' => '48 x 52 x 92 cm', 'weight' => 8,
             'warranty' => '2 Years', 'hsn' => '94016900', 'price' => 4299, 'sale' => 3799, 'stock' => 44],

            ['sku' => 'CHR-40003', 'name' => 'Accent Arm Chair', 'category' => 'Accent Chair', 'brand' => 'UrbanNest',
             'short' => 'Wing-back accent chair for living rooms and reading corners.',
             'material' => 'Hardwood + Velvet', 'color' => 'Emerald', 'dimensions' => '72 x 78 x 100 cm', 'weight' => 18,
             'warranty' => '3 Years', 'hsn' => '94016900', 'price' => 12999, 'stock' => 11, 'new' => true, 'featured' => true],

            ['sku' => 'CHR-40004', 'name' => 'Rocking Chair', 'category' => 'Accent Chair', 'brand' => 'Teakwood Studio',
             'short' => 'Hand-finished teak rocking chair with a woven cane back.',
             'material' => 'Solid Teak + Cane', 'color' => 'Honey', 'dimensions' => '62 x 95 x 105 cm', 'weight' => 15,
             'warranty' => '3 Years', 'hsn' => '94016900', 'price' => 15999, 'sale' => 13999, 'stock' => 8, 'custom' => true],

            ['sku' => 'CHR-40005', 'name' => 'Bar Stool Set of 2', 'category' => 'Chair', 'brand' => 'UrbanNest',
             'short' => 'Height-adjustable bar stools with a chrome footrest.',
             'material' => 'Metal + Faux Leather', 'color' => 'Black', 'dimensions' => '40 x 40 x 95 cm', 'weight' => 14,
             'warranty' => '1 Year', 'hsn' => '94016900', 'price' => 8999, 'stock' => 18],

            ['sku' => 'CHR-40006', 'name' => 'Folding Chair', 'category' => 'Chair', 'brand' => 'SteelLine',
             'short' => 'Powder-coated folding chair for events and extra seating.',
             'material' => 'Steel', 'color' => 'Black', 'dimensions' => '44 x 48 x 88 cm', 'weight' => 5,
             'warranty' => '1 Year', 'hsn' => '94016900', 'price' => 1899, 'stock' => 120],

            // ------------------------------------------------------ Table
            ['sku' => 'TAB-50001', 'name' => '6 Seater Dining Table Set', 'category' => 'Dining Table', 'brand' => 'Teakwood Studio',
             'short' => 'Six-seater dining set with a solid wood top and six chairs.',
             'material' => 'Sheesham Wood', 'color' => 'Walnut Brown', 'dimensions' => '180 x 90 x 76 cm', 'weight' => 85,
             'warranty' => '5 Years', 'hsn' => '94036000', 'price' => 18999, 'sale' => 15999, 'stock' => 9,
             'featured' => true, 'best' => true, 'custom' => true,
             'variants' => [['4 Seater', 'Walnut Brown', -4000, 5], ['6 Seater', 'Walnut Brown', 0, 4]]],

            ['sku' => 'TAB-50002', 'name' => '4 Seater Dining Table', 'category' => 'Dining Table', 'brand' => 'FurniCraft Originals',
             'short' => 'Compact four-seater dining table for apartments.',
             'material' => 'Mango Wood', 'color' => 'Natural', 'dimensions' => '120 x 75 x 76 cm', 'weight' => 38,
             'warranty' => '3 Years', 'hsn' => '94036000', 'price' => 11999, 'stock' => 14],

            ['sku' => 'TAB-50003', 'name' => 'Teak Coffee Table', 'category' => 'Coffee Table', 'brand' => 'Teakwood Studio',
             'short' => 'Solid teak coffee table with a lower magazine shelf.',
             'material' => 'Solid Teak', 'color' => 'Honey Oak', 'dimensions' => '110 x 60 x 45 cm', 'weight' => 22,
             'warranty' => '3 Years', 'hsn' => '94036000', 'price' => 9499, 'sale' => 8299, 'stock' => 17, 'new' => true],

            ['sku' => 'TAB-50004', 'name' => 'Nesting Table Set of 3', 'category' => 'Coffee Table', 'brand' => 'UrbanNest',
             'short' => 'Three nesting side tables that tuck away when not in use.',
             'material' => 'Engineered Wood + Metal', 'color' => 'Oak & Black', 'dimensions' => '55 x 40 x 55 cm', 'weight' => 13,
             'warranty' => '2 Years', 'hsn' => '94036000', 'price' => 6999, 'stock' => 21],

            ['sku' => 'TAB-50005', 'name' => 'Study Table with Shelf', 'category' => 'Table', 'brand' => 'FurniCraft Originals',
             'short' => 'Study table with an overhead bookshelf and cable cut-out.',
             'material' => 'Engineered Wood', 'color' => 'Maple', 'dimensions' => '120 x 60 x 140 cm', 'weight' => 34,
             'warranty' => '3 Years', 'hsn' => '94036000', 'price' => 10499, 'sale' => 8999, 'stock' => 19, 'best' => true],

            ['sku' => 'TAB-50006', 'name' => 'Console Table', 'category' => 'Table', 'brand' => 'Teakwood Studio',
             'short' => 'Slim entryway console with two drawers.',
             'material' => 'Sheesham Wood', 'color' => 'Walnut Brown', 'dimensions' => '110 x 35 x 80 cm', 'weight' => 24,
             'warranty' => '3 Years', 'hsn' => '94036000', 'price' => 13999, 'stock' => 10],

            // -------------------------------------------- Office Furniture
            ['sku' => 'OFC-60001', 'name' => 'Ergonomic Office Chair', 'category' => 'Office Chair', 'brand' => 'UrbanNest',
             'short' => 'Mesh-back office chair with lumbar support and a tilt lock.',
             'material' => 'Mesh + Nylon Base', 'color' => 'Black', 'dimensions' => '66 x 66 x 120 cm', 'weight' => 16,
             'warranty' => '2 Years', 'hsn' => '94013000', 'price' => 7499, 'sale' => 6499, 'stock' => 32,
             'featured' => true, 'best' => true,
             'variants' => [['Standard', 'Black', 0, 20], ['High Back', 'Black', 1500, 12]]],

            ['sku' => 'OFC-60002', 'name' => 'Executive Leather Chair', 'category' => 'Office Chair', 'brand' => 'UrbanNest',
             'short' => 'High-back executive chair in padded faux leather.',
             'material' => 'Faux Leather', 'color' => 'Dark Brown', 'dimensions' => '70 x 72 x 128 cm', 'weight' => 22,
             'warranty' => '3 Years', 'hsn' => '94013000', 'price' => 14999, 'stock' => 13],

            ['sku' => 'OFC-60003', 'name' => 'Visitor Chair Set of 2', 'category' => 'Office Chair', 'brand' => 'SteelLine',
             'short' => 'Cantilever visitor chairs with a chrome frame.',
             'material' => 'Steel + Fabric', 'color' => 'Charcoal', 'dimensions' => '55 x 58 x 88 cm', 'weight' => 17,
             'warranty' => '2 Years', 'hsn' => '94013000', 'price' => 9299, 'stock' => 16],

            ['sku' => 'OFC-60004', 'name' => 'Office Workstation Desk', 'category' => 'Workstation', 'brand' => 'FurniCraft Originals',
             'short' => 'Single workstation with a modesty panel and CPU trolley.',
             'material' => 'Engineered Wood + Steel', 'color' => 'Oak & Grey', 'dimensions' => '140 x 70 x 75 cm', 'weight' => 46,
             'warranty' => '3 Years', 'hsn' => '94033000', 'price' => 16999, 'sale' => 14999, 'stock' => 11, 'custom' => true],

            ['sku' => 'OFC-60005', 'name' => 'Conference Table 8 Seater', 'category' => 'Workstation', 'brand' => 'FurniCraft Originals',
             'short' => 'Boat-shaped conference table with wire management.',
             'material' => 'Engineered Wood', 'color' => 'Walnut', 'dimensions' => '240 x 120 x 76 cm', 'weight' => 98,
             'warranty' => '3 Years', 'hsn' => '94033000', 'price' => 42999, 'stock' => 3, 'custom' => true, 'new' => true],

            ['sku' => 'OFC-60006', 'name' => 'Reception Desk', 'category' => 'Office Furniture', 'brand' => 'FurniCraft Originals',
             'short' => 'Curved reception desk with a raised transaction counter.',
             'material' => 'Engineered Wood', 'color' => 'White & Oak', 'dimensions' => '180 x 80 x 110 cm', 'weight' => 72,
             'warranty' => '3 Years', 'hsn' => '94033000', 'price' => 32999, 'sale' => 28999, 'stock' => 4, 'custom' => true],
        ];
    }
}
