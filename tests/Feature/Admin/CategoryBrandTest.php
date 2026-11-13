<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryBrandTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /* ----------------------------------------------------- categories */

    public function test_admin_can_create_a_category_with_a_generated_slug(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.categories.store'), [
                'name' => 'Steel Furniture',
                'is_active' => 1,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'name' => 'Steel Furniture',
            'slug' => 'steel-furniture',
            'is_active' => 1,
        ]);
    }

    public function test_duplicate_category_slug_is_blocked(): void
    {
        Category::create(['name' => 'Sofa', 'slug' => 'sofa']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.categories.store'), [
                'name' => 'Another Sofa',
                'slug' => 'sofa',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Category::where('slug', 'sofa')->count());
    }

    public function test_a_second_category_with_the_same_name_gets_a_suffixed_slug(): void
    {
        Category::create(['name' => 'Sofa', 'slug' => 'sofa']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.categories.store'), ['name' => 'Sofa']);

        $this->assertDatabaseHas('categories', ['slug' => 'sofa-2']);
    }

    public function test_sub_categories_can_be_nested_under_a_parent(): void
    {
        $parent = Category::create(['name' => 'Sofa', 'slug' => 'sofa']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.categories.store'), [
                'name' => 'Fabric Sofa',
                'parent_id' => $parent->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', ['slug' => 'fabric-sofa', 'parent_id' => $parent->id]);
    }

    public function test_a_category_cannot_be_moved_inside_its_own_child(): void
    {
        $parent = Category::create(['name' => 'Sofa', 'slug' => 'sofa']);
        $child = Category::create(['name' => 'Fabric Sofa', 'slug' => 'fabric-sofa', 'parent_id' => $parent->id]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.categories.update', $parent), [
                'name' => 'Sofa',
                'parent_id' => $child->id,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_a_category_holding_products_cannot_be_deleted(): void
    {
        $category = Category::create(['name' => 'Sofa', 'slug' => 'sofa']);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Test Sofa', 'slug' => 'test-sofa', 'sku' => 'SOF-1', 'price' => 100,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.categories.destroy', $category))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_a_category_image_is_converted_to_webp(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.categories.store'), [
                'name' => 'Sofa',
                'image' => UploadedFile::fake()->image('sofa.jpg', 1200, 800),
            ]);

        $path = Category::where('slug', 'sofa')->value('image');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_category_visibility_can_be_toggled(): void
    {
        $category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.categories.toggle', $category))
            ->assertRedirect();

        $this->assertFalse($category->fresh()->is_active);
    }

    /* --------------------------------------------------------- brands */

    public function test_admin_can_create_a_brand(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.brands.store'), ['name' => 'Teakwood Studio', 'is_active' => 1])
            ->assertRedirect(route('admin.brands.index'));

        $this->assertDatabaseHas('brands', ['slug' => 'teakwood-studio']);
    }

    public function test_duplicate_brand_slug_is_blocked(): void
    {
        Brand::create(['name' => 'UrbanNest', 'slug' => 'urbannest']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.brands.store'), ['name' => 'Urban Nest', 'slug' => 'urbannest'])
            ->assertSessionHasErrors('slug');
    }

    public function test_a_brand_with_products_cannot_be_deleted(): void
    {
        $category = Category::create(['name' => 'Sofa', 'slug' => 'sofa']);
        $brand = Brand::create(['name' => 'UrbanNest', 'slug' => 'urbannest']);

        Product::create([
            'category_id' => $category->id, 'brand_id' => $brand->id,
            'name' => 'Test Sofa', 'slug' => 'test-sofa', 'sku' => 'SOF-1', 'price' => 100,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.brands.destroy', $brand))
            ->assertSessionHas('error');
    }

    /* --------------------------------------------------- permissions */

    public function test_the_delivery_role_cannot_open_the_catalog(): void
    {
        $delivery = User::factory()->create();
        $delivery->assignRole('Delivery');

        $this->actingAs($delivery, 'admin')->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($delivery, 'admin')->get(route('admin.brands.index'))->assertForbidden();
    }

    public function test_the_inventory_role_can_view_but_not_create_categories(): void
    {
        $inventory = User::factory()->create();
        $inventory->assignRole('Inventory');

        $this->actingAs($inventory, 'admin')->get(route('admin.categories.index'))->assertOk();
        $this->actingAs($inventory, 'admin')->get(route('admin.categories.create'))->assertForbidden();
    }
}
