<?php

namespace Tests\Feature\Security;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\DeliveryZone;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every write endpoint rejects empty and malformed input, so an audit does not
 * find a form that silently accepts rubbish.
 */
class ValidationTest extends TestCase
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

        $this->category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);
    }

    protected function asAdmin()
    {
        return $this->actingAs($this->admin, 'admin');
    }

    /* ------------------------------------------- required-field sweep */

    public static function emptySubmissions(): array
    {
        return [
            'category' => ['admin.categories.store', ['name']],
            'brand' => ['admin.brands.store', ['name']],
            'product' => ['admin.products.store', ['name', 'category_id', 'price', 'gst_rate', 'status']],
            'staff user' => ['admin.users.store', ['name', 'email', 'role', 'password']],
            'role' => ['admin.roles.store', ['name']],
            'cms page' => ['admin.cms.store', ['title']],
            'delivery zone' => ['admin.delivery.zones.store', ['name', 'delivery_days', 'base_charge', 'per_kg_charge']],
            'pincode' => ['admin.delivery.pincodes.store', ['pincode', 'city', 'state']],
        ];
    }

    /**
     * @dataProvider emptySubmissions
     */
    public function test_an_empty_submission_is_rejected(string $route, array $fields): void
    {
        $this->asAdmin()->post(route($route), [])->assertSessionHasErrors($fields);
    }

    /* ------------------------------------------------------ categories */

    public function test_a_category_slug_must_be_url_safe(): void
    {
        $this->asAdmin()
            ->post(route('admin.categories.store'), ['name' => 'Bad', 'slug' => 'Not A Slug!'])
            ->assertSessionHasErrors('slug');
    }

    public function test_a_category_parent_must_exist(): void
    {
        $this->asAdmin()
            ->post(route('admin.categories.store'), ['name' => 'Orphan', 'parent_id' => 99999])
            ->assertSessionHasErrors('parent_id');
    }

    /* -------------------------------------------------------- products */

    public function test_product_price_must_be_numeric_and_not_negative(): void
    {
        $base = ['name' => 'X', 'category_id' => $this->category->id, 'gst_rate' => 18, 'status' => 'active'];

        $this->asAdmin()->post(route('admin.products.store'), $base + ['price' => 'free'])
            ->assertSessionHasErrors('price');

        $this->asAdmin()->post(route('admin.products.store'), $base + ['price' => -5])
            ->assertSessionHasErrors('price');
    }

    public function test_product_gst_rate_is_capped(): void
    {
        $this->asAdmin()->post(route('admin.products.store'), [
            'name' => 'X', 'category_id' => $this->category->id, 'price' => 100,
            'gst_rate' => 99, 'status' => 'active',
        ])->assertSessionHasErrors('gst_rate');
    }

    public function test_product_status_must_be_a_known_value(): void
    {
        $this->asAdmin()->post(route('admin.products.store'), [
            'name' => 'X', 'category_id' => $this->category->id, 'price' => 100,
            'gst_rate' => 18, 'status' => 'published',
        ])->assertSessionHasErrors('status');
    }

    public function test_product_category_must_exist(): void
    {
        $this->asAdmin()->post(route('admin.products.store'), [
            'name' => 'X', 'category_id' => 99999, 'price' => 100,
            'gst_rate' => 18, 'status' => 'active',
        ])->assertSessionHasErrors('category_id');
    }

    public function test_a_sku_with_spaces_or_symbols_is_rejected(): void
    {
        $this->asAdmin()->post(route('admin.products.store'), [
            'name' => 'X', 'category_id' => $this->category->id, 'price' => 100,
            'gst_rate' => 18, 'status' => 'active', 'sku' => 'BAD SKU<>',
        ])->assertSessionHasErrors('sku');
    }

    public function test_more_than_ten_images_are_rejected(): void
    {
        $product = Product::create([
            'category_id' => $this->category->id, 'name' => 'P', 'slug' => 'p', 'sku' => 'P-1',
            'price' => 100, 'status' => 'active',
        ]);

        $images = [];
        for ($i = 0; $i < 11; $i++) {
            $images[] = \Illuminate\Http\UploadedFile::fake()->image("i{$i}.jpg", 50, 50);
        }

        $this->asAdmin()
            ->post(route('admin.products.images.store', $product), ['images' => $images])
            ->assertSessionHasErrors('images');
    }

    /* ----------------------------------------------------- staff users */

    public function test_a_weak_staff_password_is_rejected(): void
    {
        $base = ['name' => 'N', 'email' => 'n@example.com', 'role' => 'Sales'];

        // Too short.
        $this->asAdmin()->post(route('admin.users.store'), $base + ['password' => 'ab1', 'password_confirmation' => 'ab1'])
            ->assertSessionHasErrors('password');

        // Letters only, no digits.
        $this->asAdmin()->post(route('admin.users.store'), $base + ['password' => 'onlyletters', 'password_confirmation' => 'onlyletters'])
            ->assertSessionHasErrors('password');
    }

    public function test_a_mismatched_password_confirmation_is_rejected(): void
    {
        $this->asAdmin()->post(route('admin.users.store'), [
            'name' => 'N', 'email' => 'n@example.com', 'role' => 'Sales',
            'password' => 'Welcome123', 'password_confirmation' => 'Different123',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_staff_role_must_exist_on_the_admin_guard(): void
    {
        $this->asAdmin()->post(route('admin.users.store'), [
            'name' => 'N', 'email' => 'n@example.com', 'role' => 'Superuser',
            'password' => 'Welcome123', 'password_confirmation' => 'Welcome123',
        ])->assertSessionHasErrors('role');
    }

    /* -------------------------------------------------------- settings */

    public function test_settings_reject_an_invalid_email_and_currency(): void
    {
        $this->asAdmin()->put(route('admin.settings.update'), [
            'group' => 'company', 'company_name' => 'X',
            'company_email' => 'not-an-email', 'company_phone' => '123',
        ])->assertSessionHasErrors('company_email');

        $this->asAdmin()->put(route('admin.settings.update'), [
            'group' => 'localisation', 'currency_code' => 'RUPEES',
            'currency_symbol' => '₹', 'timezone' => 'Asia/Kolkata', 'date_format' => 'd-m-Y',
        ])->assertSessionHasErrors('currency_code');
    }

    public function test_settings_reject_an_unknown_timezone(): void
    {
        $this->asAdmin()->put(route('admin.settings.update'), [
            'group' => 'localisation', 'currency_code' => 'INR', 'currency_symbol' => '₹',
            'timezone' => 'Mars/Olympus', 'date_format' => 'd-m-Y',
        ])->assertSessionHasErrors('timezone');
    }

    public function test_an_unknown_settings_group_is_a_404(): void
    {
        $this->asAdmin()->put(route('admin.settings.update'), ['group' => 'hacking'])->assertNotFound();
    }

    public function test_an_order_prefix_must_be_capitals_and_digits(): void
    {
        $this->asAdmin()->put(route('admin.settings.update'), [
            'group' => 'order', 'order_prefix' => 'fc!', 'order_number_format' => '{PREFIX}/{SEQ}',
            'order_sequence_padding' => 5, 'order_next_sequence' => 1,
            'invoice_prefix' => 'INV', 'quotation_prefix' => 'QT',
        ])->assertSessionHasErrors('order_prefix');
    }

    /* ------------------------------------------------------- delivery */

    public function test_a_pincode_must_be_six_digits_not_starting_with_zero(): void
    {
        $zone = DeliveryZone::create([
            'name' => 'Z', 'delivery_days' => 3, 'base_charge' => 0, 'per_kg_charge' => 0,
        ]);

        foreach (['12345', '0123456', '011001', 'abcdef'] as $bad) {
            $this->asAdmin()->post(route('admin.delivery.pincodes.store'), [
                'pincode' => $bad, 'city' => 'X', 'state' => 'Y', 'delivery_zone_id' => $zone->id,
            ])->assertSessionHasErrors('pincode');
        }
    }

    public function test_a_duplicate_pincode_is_rejected(): void
    {
        Pincode::create(['pincode' => '110001', 'city' => 'Delhi', 'state' => 'Delhi']);

        $this->asAdmin()->post(route('admin.delivery.pincodes.store'), [
            'pincode' => '110001', 'city' => 'Delhi', 'state' => 'Delhi',
        ])->assertSessionHasErrors('pincode');
    }

    public function test_the_bulk_pincode_box_reports_bad_lines(): void
    {
        $zone = DeliveryZone::create([
            'name' => 'Z', 'delivery_days' => 3, 'base_charge' => 0, 'per_kg_charge' => 0,
        ]);

        $this->asAdmin()->post(route('admin.delivery.pincodes.import'), [
            'delivery_zone_id' => $zone->id,
            'rows' => "110001, New Delhi, Delhi\nnonsense line\n201301, Noida, Uttar Pradesh",
        ])->assertRedirect();

        $this->assertSame(2, Pincode::count());
        $this->assertNotEmpty(session('pincode_errors'));
    }

    public function test_a_delivery_zone_rejects_a_negative_charge(): void
    {
        $this->asAdmin()->post(route('admin.delivery.zones.store'), [
            'name' => 'Z', 'delivery_days' => 3, 'base_charge' => -10, 'per_kg_charge' => 0,
        ])->assertSessionHasErrors('base_charge');
    }

    /* ------------------------------------------------------ CMS pages */

    public function test_a_duplicate_cms_slug_is_rejected(): void
    {
        CmsPage::create(['title' => 'A', 'slug' => 'taken-slug', 'is_active' => true]);

        $this->asAdmin()->post(route('admin.cms.store'), ['title' => 'B', 'slug' => 'taken-slug'])
            ->assertSessionHasErrors('slug');
    }

    /* ----------------------------------------------- storefront input */

    public function test_the_listing_rejects_an_unknown_sort(): void
    {
        $this->get(route('products', ['sort' => 'cheapest; drop table']))->assertSessionHasErrors('sort');
    }

    public function test_the_listing_rejects_a_non_numeric_price_filter(): void
    {
        $this->get(route('products', ['min_price' => 'abc']))->assertSessionHasErrors('min_price');
    }

    public function test_the_listing_rejects_an_over_long_search_term(): void
    {
        $this->get(route('products', ['q' => str_repeat('a', 300)]))->assertSessionHasErrors('q');
    }

    public function test_the_suggest_endpoint_rejects_an_over_long_term(): void
    {
        $this->getJson(route('search.suggest', ['q' => str_repeat('a', 300)]))
            ->assertStatus(422);
    }

    public function test_the_pincode_check_requires_a_pincode(): void
    {
        $this->postJson(route('delivery.check'), [])->assertStatus(422);
    }

    /* ------------------------------------------------ customer signup */

    public function test_customer_registration_validates_every_field(): void
    {
        $this->post(route('customer.register'), [])
            ->assertSessionHasErrors(['name', 'email', 'phone', 'password']);
    }

    public function test_a_duplicate_customer_email_is_rejected(): void
    {
        \App\Models\Customer::create([
            'name' => 'A', 'email' => 'dup@example.com', 'phone' => '9811100000',
            'password' => 'Welcome123', 'is_active' => true,
        ]);

        $this->post(route('customer.register'), [
            'name' => 'B', 'email' => 'dup@example.com', 'phone' => '9811100001',
            'password' => 'Welcome123', 'password_confirmation' => 'Welcome123',
        ])->assertSessionHasErrors('email');
    }

    public function test_a_customer_phone_must_look_like_a_number(): void
    {
        $this->post(route('customer.register'), [
            'name' => 'B', 'email' => 'b@example.com', 'phone' => 'not-a-phone',
            'password' => 'Welcome123', 'password_confirmation' => 'Welcome123',
        ])->assertSessionHasErrors('phone');
    }

    /* ----------------------------------------------------- brand rules */

    public function test_a_brand_slug_must_be_url_safe(): void
    {
        Brand::create(['name' => 'Existing', 'slug' => 'existing']);

        $this->asAdmin()->post(route('admin.brands.store'), ['name' => 'X', 'slug' => 'Bad Slug'])
            ->assertSessionHasErrors('slug');
    }
}
