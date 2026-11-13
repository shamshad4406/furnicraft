<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression cover for the x-admin.select component.
 *
 * pluck('name', 'id') returns INTEGER keys, so a component that decides
 * "is this a list?" with is_int($key) renders the label as the option value
 * and the form submits a name where an id is expected. That silently cleared
 * a category's parent when the form was saved.
 */
class SelectComponentTest extends TestCase
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

    /**
     * Collapse the whitespace Blade leaves inside a tag so markup can be
     * asserted on without depending on template formatting.
     */
    protected function normalise(string $html): string
    {
        return preg_replace('/\s+/', ' ', $html);
    }

    public function test_the_parent_select_renders_ids_as_option_values(): void
    {
        $parent = Category::create(['name' => 'Steel Furniture', 'slug' => 'steel-furniture']);
        $child = Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'parent_id' => $parent->id]);

        $html = $this->normalise(
            $this->actingAs($this->admin, 'admin')
                ->get(route('admin.categories.edit', $child))
                ->assertOk()
                ->getContent()
        );

        $this->assertStringContainsString('<option value="'.$parent->id.'" selected>Steel Furniture</option>', $html);
        $this->assertStringNotContainsString('value="Steel Furniture"', $html);
    }

    public function test_opening_and_saving_a_child_category_keeps_its_parent(): void
    {
        $parent = Category::create(['name' => 'Steel Furniture', 'slug' => 'steel-furniture']);
        $child = Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'parent_id' => $parent->id]);

        // Exactly what the browser posts when the form is opened and saved untouched.
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.categories.update', $child), [
                'name' => 'Almirah',
                'slug' => 'almirah',
                'parent_id' => $parent->id,
                'sort_order' => 0,
                'is_active' => 1,
            ])
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame($parent->id, $child->fresh()->parent_id, 'Saving a child category must not clear its parent.');
    }

    public function test_the_brand_select_on_the_product_form_renders_ids(): void
    {
        Category::create(['name' => 'Sofa', 'slug' => 'sofa']);
        $brand = Brand::create(['name' => 'UrbanNest', 'slug' => 'urbannest', 'is_active' => true]);

        $html = $this->normalise(
            $this->actingAs($this->admin, 'admin')
                ->get(route('admin.products.create'))
                ->assertOk()
                ->getContent()
        );

        $this->assertStringContainsString('<option value="'.$brand->id.'" >UrbanNest</option>', $html);
        $this->assertStringNotContainsString('value="UrbanNest"', $html);
    }

    public function test_a_product_can_be_saved_with_a_brand_selected(): void
    {
        $category = Category::create(['name' => 'Sofa', 'slug' => 'sofa']);
        $brand = Brand::create(['name' => 'UrbanNest', 'slug' => 'urbannest', 'is_active' => true]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.store'), [
                'name' => 'Branded Sofa',
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'price' => 9999,
                'gst_rate' => 18,
                'status' => 'active',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($brand->id, \App\Models\Product::first()->brand_id);
    }

    public function test_a_plain_list_of_options_still_uses_its_values(): void
    {
        // The role select is built from pluck('name') — a list — and must keep
        // submitting the role name.
        $html = $this->normalise(
            $this->actingAs($this->admin, 'admin')
                ->get(route('admin.users.create'))
                ->assertOk()
                ->getContent()
        );

        $this->assertStringContainsString('<option value="Admin" >Admin</option>', $html);
    }
}
