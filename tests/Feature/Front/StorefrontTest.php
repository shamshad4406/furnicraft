<?php

namespace Tests\Feature\Front;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);
    }

    public function test_homepage_renders_the_full_shell(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Our Categories')
            ->assertSee('Featured Products')
            ->assertSee('Subscribe for Updates');
    }

    public function test_homepage_lists_live_categories_and_featured_products(): void
    {
        $category = Category::create(['name' => 'Steel Furniture', 'slug' => 'steel-furniture', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id, 'name' => 'Steel Almirah', 'slug' => 'steel-almirah',
            'sku' => 'STL-1', 'price' => 14500, 'sale_price' => 12500, 'status' => 'active', 'is_featured' => true,
        ]);

        // Not featured, and a draft — neither should reach the homepage.
        Product::create([
            'category_id' => $category->id, 'name' => 'Hidden Shelf', 'slug' => 'hidden-shelf',
            'sku' => 'STL-2', 'price' => 900, 'status' => 'active', 'is_featured' => false,
        ]);
        Product::create([
            'category_id' => $category->id, 'name' => 'Draft Cabinet', 'slug' => 'draft-cabinet',
            'sku' => 'STL-3', 'price' => 900, 'status' => 'draft', 'is_featured' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Steel Furniture')
            ->assertSee('Steel Almirah')
            ->assertSee('₹ 12,500')
            ->assertDontSee('Hidden Shelf')
            ->assertDontSee('Draft Cabinet');
    }

    public function test_an_inactive_category_is_hidden_from_the_storefront(): void
    {
        Category::create(['name' => 'Visible Cat', 'slug' => 'visible-cat', 'is_active' => true]);
        Category::create(['name' => 'Hidden Cat', 'slug' => 'hidden-cat', 'is_active' => false]);

        $this->get(route('home'))->assertOk()->assertSee('Visible Cat')->assertDontSee('Hidden Cat');
        $this->get(route('categories'))->assertOk()->assertSee('Visible Cat')->assertDontSee('Hidden Cat');
    }

    public function test_the_categories_page_shows_the_tree_with_product_counts(): void
    {
        $parent = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);
        $child = Category::create(['name' => 'Fabric Sofa', 'slug' => 'fabric-sofa', 'parent_id' => $parent->id, 'is_active' => true]);

        Product::create([
            'category_id' => $child->id, 'name' => 'Blue Sofa', 'slug' => 'blue-sofa',
            'sku' => 'SOF-1', 'price' => 1000, 'status' => 'active',
        ]);

        $this->get(route('categories'))
            ->assertOk()
            ->assertSee('Sofa')
            ->assertSee('Fabric Sofa')
            ->assertSee('1 product');
    }

    public function test_homepage_shows_company_details_from_settings(): void
    {
        settings()->set('company_phone', '+91 11111 22222', 'company');

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('+91 11111 22222');
    }

    public function test_content_pages_are_reachable(): void
    {
        $this->seed([\Database\Seeders\CmsPageSeeder::class]);

        foreach (['about-us', 'contact-us', 'faq', 'privacy-policy', 'terms-conditions'] as $page) {
            $this->get(route('page', $page))->assertOk();
        }

        $this->get(route('products'))->assertOk();
    }

    public function test_an_unknown_page_returns_404(): void
    {
        $this->get('/not-a-real-page')->assertNotFound();
    }

    public function test_a_customer_can_register_and_is_signed_in(): void
    {
        $this->post(route('customer.register'), [
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'phone' => '9811122233',
            'password' => 'Welcome123',
            'password_confirmation' => 'Welcome123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticated('customer');
        $this->assertDatabaseHas('customers', ['email' => 'ravi@example.com']);
    }

    public function test_a_customer_can_sign_in_and_out(): void
    {
        $customer = Customer::create([
            'name' => 'Asha Verma',
            'email' => 'asha@example.com',
            'phone' => '9811100000',
            'password' => 'Welcome123',
            'is_active' => true,
        ]);

        $this->post(route('customer.login'), [
            'email' => 'asha@example.com',
            'password' => 'Welcome123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($customer, 'customer');

        $this->post(route('customer.logout'))->assertRedirect(route('home'));
        $this->assertGuest('customer');
    }

    public function test_the_customer_guard_is_separate_from_the_admin_guard(): void
    {
        $staff = User::factory()->create(['password' => 'Secret123']);
        $staff->assignRole('Admin');

        // Staff credentials must not work on the storefront.
        $this->post(route('customer.login'), [
            'email' => $staff->email,
            'password' => 'Secret123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('customer');

        // And a signed-in customer must not reach the admin panel.
        $customer = Customer::create([
            'name' => 'Asha Verma',
            'email' => 'asha2@example.com',
            'password' => 'Welcome123',
            'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_a_disabled_customer_cannot_sign_in(): void
    {
        Customer::create([
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'Welcome123',
            'is_active' => false,
        ]);

        $this->post(route('customer.login'), [
            'email' => 'blocked@example.com',
            'password' => 'Welcome123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('customer');
    }
}
