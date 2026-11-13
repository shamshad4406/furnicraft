<?php

namespace Tests\Feature\Front;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\DeliveryZone;
use App\Models\Pincode;
use App\Models\Product;
use Database\Seeders\CmsPageSeeder;
use Database\Seeders\DeliverySeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryAndContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);
    }

    /* ------------------------------------------------- pincode check */

    protected function seedDelivery(): void
    {
        $zone = DeliveryZone::create([
            'name' => 'Local', 'delivery_days' => 3, 'base_charge' => 0,
            'per_kg_charge' => 0, 'cod_available' => true, 'is_active' => true,
        ]);

        Pincode::create([
            'pincode' => '110001', 'city' => 'New Delhi', 'state' => 'Delhi',
            'delivery_zone_id' => $zone->id, 'is_serviceable' => true, 'cod_available' => true,
        ]);

        Pincode::create([
            'pincode' => '682001', 'city' => 'Kochi', 'state' => 'Kerala',
            'delivery_zone_id' => $zone->id, 'is_serviceable' => false, 'cod_available' => false,
            'note' => 'We do not deliver to this area yet.',
        ]);
    }

    public function test_a_serviceable_pincode_returns_the_delivery_estimate(): void
    {
        $this->seedDelivery();

        $response = $this->postJson(route('delivery.check'), ['pincode' => '110001'])->assertOk();

        $response->assertJson([
            'found' => true,
            'serviceable' => true,
            'city' => 'New Delhi',
            'days' => 3,
            'cod' => true,
        ]);

        $this->assertStringContainsString('New Delhi', $response->json('message'));
        $this->assertNotNull($response->json('eta'));
    }

    public function test_a_pincode_level_override_beats_the_zone_default(): void
    {
        $this->seedDelivery();
        Pincode::where('pincode', '110001')->update(['delivery_days' => 1]);

        $this->postJson(route('delivery.check'), ['pincode' => '110001'])
            ->assertOk()
            ->assertJson(['days' => 1]);
    }

    public function test_a_non_serviceable_pincode_says_so(): void
    {
        $this->seedDelivery();

        // A staff-written note becomes the message instead of being repeated.
        $this->postJson(route('delivery.check'), ['pincode' => '682001'])
            ->assertOk()
            ->assertJson([
                'found' => true,
                'serviceable' => false,
                'message' => 'We do not deliver to this area yet.',
                'note' => null,
            ]);
    }

    public function test_a_non_serviceable_pincode_without_a_note_gets_a_default_message(): void
    {
        $this->seedDelivery();
        Pincode::where('pincode', '682001')->update(['note' => null]);

        $response = $this->postJson(route('delivery.check'), ['pincode' => '682001'])->assertOk();

        $this->assertStringContainsString('Kochi', $response->json('message'));
    }

    public function test_an_unmapped_pincode_is_reported_as_unknown(): void
    {
        $this->seedDelivery();

        $response = $this->postJson(route('delivery.check'), ['pincode' => '999999'])->assertOk();

        $this->assertFalse($response->json('found'));
        $this->assertStringContainsString('not mapped', $response->json('message'));
    }

    public function test_a_malformed_pincode_is_rejected(): void
    {
        $response = $this->postJson(route('delivery.check'), ['pincode' => '12ab'])->assertOk();

        $this->assertFalse($response->json('found'));
        $this->assertStringContainsString('valid 6-digit', $response->json('message'));
    }

    public function test_the_pincode_box_appears_on_the_product_page(): void
    {
        $category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);
        Product::create([
            'category_id' => $category->id, 'name' => 'A Sofa', 'slug' => 'a-sofa',
            'sku' => 'S-1', 'price' => 1000, 'status' => 'active',
        ]);

        $this->get(route('product.show', 'a-sofa'))
            ->assertOk()
            ->assertSee('Check delivery to your pincode');
    }

    /* ------------------------------------------------------ CMS pages */

    public function test_seeded_content_pages_render(): void
    {
        $this->seed([CmsPageSeeder::class]);

        foreach (['about-us', 'contact-us', 'faq', 'shipping-policy', 'return-policy', 'warranty', 'privacy-policy', 'terms-conditions'] as $slug) {
            $this->get(route('page', $slug))->assertOk();
        }
    }

    public function test_markdown_content_is_rendered_as_html(): void
    {
        CmsPage::create([
            'title' => 'Test Page', 'slug' => 'test-page', 'is_active' => true,
            'content' => "## A heading\n\nSome **bold** text.\n\n- one\n- two",
        ]);

        $this->get(route('page', 'test-page'))
            ->assertOk()
            ->assertSee('<h2>A heading</h2>', false)
            ->assertSee('<strong>bold</strong>', false)
            ->assertSee('<li>one</li>', false);
    }

    public function test_raw_html_in_page_content_is_stripped(): void
    {
        CmsPage::create([
            'title' => 'Unsafe', 'slug' => 'unsafe-page', 'is_active' => true,
            'content' => 'Hello <script>alert(1)</script> world',
        ]);

        $response = $this->get(route('page', 'unsafe-page'))->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $response->assertSee('world');
    }

    public function test_a_hidden_page_returns_404(): void
    {
        CmsPage::create(['title' => 'Hidden', 'slug' => 'hidden-page', 'is_active' => false]);

        $this->get(route('page', 'hidden-page'))->assertNotFound();
    }

    public function test_an_unknown_page_returns_404(): void
    {
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_footer_links_come_from_the_cms(): void
    {
        $this->seed([CmsPageSeeder::class]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('page', 'privacy-policy'), false)
            ->assertSee(route('page', 'warranty'), false);
    }

    public function test_the_contact_page_shows_the_company_details(): void
    {
        $this->seed([CmsPageSeeder::class]);

        $this->get(route('page', 'contact-us'))
            ->assertOk()
            ->assertSee(settings('company_phone'))
            ->assertSee(settings('company_email'));
    }

    public function test_the_delivery_seeder_maps_every_pincode_to_a_zone(): void
    {
        $this->seed([DeliverySeeder::class]);

        $this->assertSame(3, DeliveryZone::count());
        $this->assertSame(21, Pincode::count());
        $this->assertSame(0, Pincode::whereNull('delivery_zone_id')->count());
    }
}
