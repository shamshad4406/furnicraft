<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ImageService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->category = Category::create(['name' => 'Sofa', 'slug' => 'sofa']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '3 Seater Fabric Sofa',
            'category_id' => $this->category->id,
            'price' => 18999,
            'gst_rate' => 18,
            'status' => 'active',
        ], $overrides);
    }

    /* ----------------------------------------------------------- CRUD */

    public function test_admin_can_create_a_product_with_a_generated_sku_and_slug(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $this->payload())
            ->assertRedirect();

        $product = Product::first();

        $this->assertNotNull($product);
        $this->assertSame('3-seater-fabric-sofa', $product->slug);
        $this->assertNotEmpty($product->sku);
        $this->assertSame('active', $product->status);
    }

    public function test_a_product_can_be_created_with_variants_and_specifications(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $this->payload([
                'variants' => [
                    ['sku' => 'SOF-A', 'size' => '3 Seater', 'color' => 'Beige', 'stock_quantity' => 5, 'is_active' => 1],
                    ['sku' => 'SOF-B', 'size' => '2 Seater', 'color' => 'Blue', 'price' => 14999, 'stock_quantity' => 3, 'is_active' => 1],
                ],
                'specifications' => [
                    ['name' => 'Seating Capacity', 'value' => '3 Seater'],
                    ['name' => 'Frame', 'value' => 'Sheesham Wood'],
                    ['name' => '', 'value' => 'ignored because the name is blank'],
                ],
            ]))
            ->assertRedirect();

        $product = Product::with(['variants', 'specifications'])->first();

        $this->assertCount(2, $product->variants);
        $this->assertCount(2, $product->specifications);
        $this->assertSame(8, $product->total_stock);
    }

    public function test_editing_a_product_removes_variants_that_were_deleted_in_the_form(): void
    {
        $product = Product::create($this->payload(['slug' => 'sofa-x', 'sku' => 'SOF-X']));
        $keep = $product->variants()->create(['sku' => 'SOF-KEEP', 'size' => 'Large', 'stock_quantity' => 2]);
        $product->variants()->create(['sku' => 'SOF-DROP', 'size' => 'Small', 'stock_quantity' => 1]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.products.update', $product), $this->payload([
                'slug' => 'sofa-x',
                'sku' => 'SOF-X',
                'variants' => [
                    ['id' => $keep->id, 'sku' => 'SOF-KEEP', 'size' => 'Large', 'stock_quantity' => 7, 'is_active' => 1],
                ],
            ]))
            ->assertRedirect(route('admin.products.index'));

        $this->assertCount(1, $product->fresh()->variants);
        $this->assertSame(7, $keep->fresh()->stock_quantity);
        $this->assertDatabaseMissing('product_variants', ['sku' => 'SOF-DROP']);
    }

    public function test_deleting_a_product_is_a_soft_delete_that_frees_its_sku(): void
    {
        $product = Product::create($this->payload(['slug' => 'sofa-x', 'sku' => 'SOF-X']));

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $this->assertSoftDeleted('products', ['id' => $product->id]);

        // The SKU is released, so it can be used again.
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $this->payload(['sku' => 'SOF-X']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['sku' => 'SOF-X', 'deleted_at' => null]);
    }

    /* ----------------------------------- exit criterion: duplicates blocked */

    public function test_duplicate_sku_is_blocked(): void
    {
        Product::create($this->payload(['slug' => 'existing', 'sku' => 'SOF-DUP']));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $this->payload(['sku' => 'SOF-DUP']))
            ->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::where('sku', 'SOF-DUP')->count());
    }

    public function test_duplicate_slug_is_blocked(): void
    {
        Product::create($this->payload(['slug' => 'taken-slug', 'sku' => 'SOF-1']));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $this->payload(['slug' => 'taken-slug']))
            ->assertSessionHasErrors('slug');
    }

    public function test_duplicate_variant_sku_is_blocked_within_the_same_form(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $this->payload([
                'variants' => [
                    ['sku' => 'SAME', 'size' => 'A', 'stock_quantity' => 1],
                    ['sku' => 'SAME', 'size' => 'B', 'stock_quantity' => 1],
                ],
            ]))
            ->assertSessionHasErrors('variants.1.sku');

        $this->assertSame(0, ProductVariant::count());
    }

    public function test_a_variant_sku_already_used_elsewhere_is_blocked(): void
    {
        $other = Product::create($this->payload(['slug' => 'other', 'sku' => 'OTHER']));
        $other->variants()->create(['sku' => 'TAKEN', 'size' => 'A']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $this->payload([
                'variants' => [['sku' => 'TAKEN', 'size' => 'B', 'stock_quantity' => 1]],
            ]))
            ->assertSessionHasErrors('variants.0.sku');
    }

    public function test_sale_price_must_be_lower_than_the_price(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $this->payload(['price' => 1000, 'sale_price' => 1200]))
            ->assertSessionHasErrors('sale_price');
    }

    public function test_an_invalid_hsn_code_is_rejected(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $this->payload(['hsn_code' => 'ABC']))
            ->assertSessionHasErrors('hsn_code');
    }

    /* -------------------------------- exit criterion: images optimised */

    public function test_uploaded_images_are_converted_to_webp_with_a_thumbnail(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), $this->payload([
                'images' => [UploadedFile::fake()->image('sofa.jpg', 2400, 1600)],
            ]))
            ->assertRedirect();

        $image = Product::first()->images()->first();

        $this->assertNotNull($image);
        $this->assertStringEndsWith('.webp', $image->path);
        $this->assertStringEndsWith('.webp', $image->thumbnail_path);
        Storage::disk('public')->assertExists($image->path);
        Storage::disk('public')->assertExists($image->thumbnail_path);

        // Oversized uploads are scaled down to the configured maximum.
        $this->assertSame(ImageService::MAX_EDGE, $image->width);
        $this->assertTrue($image->is_primary, 'The first image should become the primary one.');
        $this->assertGreaterThan(0, $image->size);
    }

    public function test_the_first_uploaded_image_becomes_primary_and_later_ones_do_not(): void
    {
        Storage::fake('public');

        $product = Product::create($this->payload(['slug' => 'sofa-x', 'sku' => 'SOF-X']));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.images.store', $product), [
                'images' => [
                    UploadedFile::fake()->image('one.jpg', 800, 600),
                    UploadedFile::fake()->image('two.jpg', 800, 600),
                ],
            ])
            ->assertRedirect();

        $images = $product->fresh()->images;

        $this->assertCount(2, $images);
        $this->assertSame(1, $images->where('is_primary', true)->count());
    }

    public function test_the_primary_image_can_be_changed_and_deleting_it_promotes_another(): void
    {
        Storage::fake('public');

        $product = Product::create($this->payload(['slug' => 'sofa-x', 'sku' => 'SOF-X']));

        $this->actingAs($this->admin, 'admin')->post(route('admin.products.images.store', $product), [
            'images' => [
                UploadedFile::fake()->image('one.jpg', 800, 600),
                UploadedFile::fake()->image('two.jpg', 800, 600),
            ],
        ]);

        [$first, $second] = $product->fresh()->images->all();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.products.images.primary', [$product, $second]))
            ->assertRedirect();

        $this->assertTrue($second->fresh()->is_primary);
        $this->assertFalse($first->fresh()->is_primary);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.products.images.destroy', [$product, $second]))
            ->assertRedirect();

        $this->assertTrue($first->fresh()->is_primary, 'Deleting the primary image should promote another.');
        Storage::disk('public')->assertMissing($second->path);
    }

    public function test_images_can_be_reordered(): void
    {
        Storage::fake('public');

        $product = Product::create($this->payload(['slug' => 'sofa-x', 'sku' => 'SOF-X']));

        $this->actingAs($this->admin, 'admin')->post(route('admin.products.images.store', $product), [
            'images' => [
                UploadedFile::fake()->image('one.jpg', 400, 300),
                UploadedFile::fake()->image('two.jpg', 400, 300),
            ],
        ]);

        $ids = $product->fresh()->images->pluck('id')->all();

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.products.images.reorder', $product), ['order' => array_reverse($ids)])
            ->assertOk();

        $this->assertSame(array_reverse($ids), $product->fresh()->images->pluck('id')->all());
    }

    /* -------------------------------------------------------- listing */

    public function test_products_can_be_searched_by_sku_and_filtered_by_status(): void
    {
        Product::create($this->payload(['name' => 'Blue Sofa', 'slug' => 'blue', 'sku' => 'FIND-ME', 'status' => 'active']));
        Product::create($this->payload(['name' => 'Red Sofa', 'slug' => 'red', 'sku' => 'OTHER-1', 'status' => 'draft']));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.products.index', ['q' => 'FIND-ME']))
            ->assertOk()
            ->assertSee('Blue Sofa')
            ->assertDontSee('Red Sofa');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.products.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('Red Sofa')
            ->assertDontSee('Blue Sofa');
    }

    public function test_sales_role_can_view_products_but_not_create_them(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('Sales');

        $this->actingAs($sales, 'admin')->get(route('admin.products.index'))->assertOk();
        $this->actingAs($sales, 'admin')->get(route('admin.products.create'))->assertForbidden();
    }
}
