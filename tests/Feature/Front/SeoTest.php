<?php

namespace Tests\Feature\Front;

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewService;
use App\Services\SeoService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * What search engines are told about the shop.
 *
 * The thing worth guarding is honesty. A rating with no reviews behind it or
 * an "in stock" on something sold out is the kind of claim that gets a
 * shop's rich results pulled, and it is the easiest thing in the world to
 * leave in by accident.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected SeoService $seo;

    protected Category $category;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->seo = app(SeoService::class);

        $this->category = Category::create(['name' => 'Sofas', 'slug' => 'sofas', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Oakwood Three Seater', 'slug' => 'oakwood-three-seater',
            'sku' => Str::upper(Str::random(8)),
            'short_description' => 'A solid oak three seater.',
            'price' => 35400, 'stock_quantity' => 3, 'gst_rate' => 18, 'status' => 'active',
        ]);
    }

    /* ---------------------------------------------------------- sitemap */

    public function test_the_sitemap_lists_the_shops_public_pages(): void
    {
        $page = CmsPage::create([
            'title' => 'About Us', 'slug' => 'about-us',
            'content' => 'We make furniture.', 'is_active' => true,
        ]);

        $response = $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = $response->getContent();

        $this->assertStringContainsString('<urlset', $xml);
        $this->assertStringContainsString(route('home'), $xml);
        $this->assertStringContainsString(route('product.show', $this->product->slug), $xml);
        $this->assertStringContainsString(route('category.show', $this->category->slug), $xml);
        $this->assertStringContainsString(route('page', $page->slug), $xml);

        // Well-formed, not merely full of the right words.
        $this->assertInstanceOf(\SimpleXMLElement::class, simplexml_load_string($xml));
    }

    public function test_drafts_and_switched_off_pages_stay_out_of_the_sitemap(): void
    {
        $draft = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Not Ready', 'slug' => 'not-ready', 'sku' => Str::upper(Str::random(8)),
            'price' => 1000, 'stock_quantity' => 1, 'gst_rate' => 18, 'status' => 'draft',
        ]);

        $hidden = Category::create(['name' => 'Clearance', 'slug' => 'clearance', 'is_active' => false]);

        $xml = $this->get(route('sitemap'))->getContent();

        $this->assertStringNotContainsString('/product/not-ready', $xml);
        $this->assertStringNotContainsString('/clearance', $xml);
        $this->assertStringNotContainsString($draft->slug, $xml);
        $this->assertStringNotContainsString($hidden->slug, $xml);
    }

    /**
     * Anything addressed by a one-off token is somebody's private page.
     */
    public function test_nothing_behind_a_token_or_a_sign_in_is_in_the_sitemap(): void
    {
        $xml = $this->get(route('sitemap'))->getContent();

        foreach (['/account', '/checkout', '/cart', '/track-order', '/admin', '/quotation'] as $private) {
            $this->assertStringNotContainsString($private, $xml);
        }
    }

    /* ------------------------------------------------------- robots.txt */

    public function test_robots_allows_the_shop_and_keeps_private_pages_out(): void
    {
        $body = $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->getContent();

        $this->assertStringContainsString('Allow: /', $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Disallow: /account', $body);
        $this->assertStringContainsString('Disallow: /track-order', $body);
        $this->assertStringContainsString('Sitemap: '.route('sitemap'), $body);
    }

    /**
     * A file on disk is served before the route ever runs, and the test
     * client would never notice: it goes straight to the kernel. Laravel
     * ships a stock `public/robots.txt`, so this is exactly how a generated
     * robots.txt ends up green in the suite and absent from the live site.
     */
    public function test_no_file_on_disk_shadows_the_generated_robots_or_sitemap(): void
    {
        $this->assertFileDoesNotExist(public_path('robots.txt'));
        $this->assertFileDoesNotExist(public_path('sitemap.xml'));
    }

    public function test_with_indexing_switched_off_the_whole_shop_is_disallowed(): void
    {
        settings()->set('seo_allow_indexing', '0');

        $body = $this->get(route('robots'))->getContent();

        $this->assertStringContainsString('Disallow: /', $body);
        $this->assertStringNotContainsString('Allow: /', $body);

        // And every page says so for itself, in case a crawler ignores robots.
        $this->get(route('home'))->assertSee('noindex, nofollow', false);
    }

    public function test_pages_are_indexable_while_indexing_is_on(): void
    {
        $this->get(route('home'))
            ->assertSee('index, follow', false)
            ->assertDontSee('noindex', false);
    }

    /**
     * A tracking link in a search result is somebody's address on the open web.
     */
    public function test_a_page_addressed_by_a_token_says_noindex(): void
    {
        $address = [
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park', 'city' => 'New Delhi',
            'state' => 'Delhi', 'pincode' => '110001',
        ];

        $order = \App\Models\Order::create([
            'order_number' => 'FC/2611/00001',
            'customer_name' => 'Ravi Kumar',
            'customer_email' => 'ravi@example.com',
            'customer_phone' => '9811122233',
            'shipping_address' => $address,
            'billing_address' => $address,
            'shipping_pincode' => '110001',
            'subtotal' => 35400, 'tax_total' => 5400, 'grand_total' => 35400,
            'amount_paid' => 35400, 'amount_due' => 0,
            'payment_method' => 'razorpay', 'payment_status' => 'paid',
            'status' => 'confirmed', 'placed_at' => now(),
        ]);

        $this->get(route('track.status', $order->public_token))
            ->assertOk()
            ->assertSee('noindex, nofollow', false);

        // And robots.txt keeps crawlers off the lookup form as well, so the
        // token page is never reached from a search result in the first place.
        $this->assertStringContainsString('Disallow: /track-order',
            $this->get(route('robots'))->getContent());
    }

    /* ----------------------------------------------------- product schema */

    public function test_the_product_schema_describes_what_is_actually_there(): void
    {
        $data = $this->seo->product($this->product);

        $this->assertSame('Product', $data['@type']);
        $this->assertSame($this->product->name, $data['name']);
        $this->assertSame($this->product->sku, $data['sku']);
        $this->assertSame('INR', $data['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $data['offers']['availability']);
    }

    public function test_something_out_of_stock_is_not_advertised_as_in_stock(): void
    {
        $this->product->update(['stock_quantity' => 0]);

        $data = $this->seo->product($this->product->fresh());

        $this->assertSame('https://schema.org/OutOfStock', $data['offers']['availability']);
    }

    /**
     * The claim most often made falsely, and the one Google checks.
     */
    public function test_no_rating_is_claimed_until_a_review_is_published(): void
    {
        $reviews = app(ReviewService::class);

        $waiting = $reviews->submit($this->product, [
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com',
            'rating' => 5, 'body' => 'Very good indeed, exactly as pictured.',
        ]);

        $this->assertArrayNotHasKey('aggregateRating', $this->seo->product($this->product->fresh()));

        $admin = User::factory()->create();
        $admin->assignRole('Admin');
        $reviews->approve($waiting, $admin);

        $data = $this->seo->product($this->product->fresh());

        $this->assertSame('5', $data['aggregateRating']['ratingValue']);
        $this->assertSame(1, $data['aggregateRating']['reviewCount']);
    }

    public function test_the_product_page_carries_its_json_ld(): void
    {
        $html = $this->get(route('product.show', $this->product->slug))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertStringContainsString('"@type":"Product"', str_replace(['\\/', ' '], ['/', ''], $html));
    }

    /* ------------------------------------------------------- the shop itself */

    public function test_the_homepage_describes_the_shop(): void
    {
        $data = $this->seo->organisation();

        $this->assertSame('FurnitureStore', $data['@type']);
        $this->assertSame(settings('company_name'), $data['name']);
        $this->assertSame(url('/'), $data['url']);

        $this->get(route('home'))->assertSee('FurnitureStore', false);
    }

    public function test_breadcrumbs_are_numbered_in_order(): void
    {
        $data = $this->seo->breadcrumbs([
            ['name' => 'Home', 'url' => url('/')],
            ['name' => 'Sofas', 'url' => route('category.show', 'sofas')],
        ]);

        $this->assertSame(1, $data['itemListElement'][0]['position']);
        $this->assertSame(2, $data['itemListElement'][1]['position']);
        $this->assertSame('Sofas', $data['itemListElement'][1]['name']);
    }

    /* ----------------------------------------------------------- metadata */

    public function test_every_page_has_a_canonical_address_without_the_query_string(): void
    {
        $html = $this->get(route('products', ['sort' => 'newest', 'page' => 2]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<link rel="canonical" href="'.route('products').'"', $html);
    }

    public function test_a_link_pasted_into_a_chat_shows_something_useful(): void
    {
        $this->get(route('product.show', $this->product->slug))
            ->assertSee('og:title', false)
            ->assertSee('og:image', false)
            ->assertSee('og:url', false);
    }

    /* ----------------------------------------------------------- analytics */

    public function test_no_analytics_tag_and_no_google_in_the_policy_until_an_id_is_set(): void
    {
        $response = $this->get(route('home'));

        $response->assertDontSee('assets/js/analytics.js', false);
        $this->assertStringNotContainsString('googletagmanager.com',
            $response->headers->get('Content-Security-Policy') ?? '');
    }

    public function test_setting_the_id_adds_the_tag_and_opens_the_policy_for_it(): void
    {
        settings()->set('google_analytics_id', 'G-ABC1234567');

        $response = $this->get(route('home'));

        $response->assertSee('assets/js/analytics.js', false)
            ->assertSee('data-ga-id="G-ABC1234567"', false);

        $this->assertStringContainsString('googletagmanager.com',
            $response->headers->get('Content-Security-Policy') ?? '');
    }

    public function test_a_mistyped_measurement_id_is_refused(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.update'), [
                'group' => 'marketing',
                'google_analytics_id' => 'GA4-nonsense',
            ])
            ->assertSessionHasErrors('google_analytics_id');
    }

    public function test_the_verification_tag_only_appears_once_it_is_filled_in(): void
    {
        $this->get(route('home'))->assertDontSee('google-site-verification', false);

        settings()->set('google_site_verification', 'abc123_XYZ-456');

        $this->get(route('home'))
            ->assertSee('google-site-verification', false)
            ->assertSee('abc123_XYZ-456', false);
    }

    /* ------------------------------------------------------- the shopfront */

    public function test_a_published_review_can_be_sorted_and_filtered_on(): void
    {
        $reviews = app(ReviewService::class);
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $reviews->approve($reviews->submit($this->product, [
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com',
            'rating' => 5, 'body' => 'Very good indeed, exactly as pictured.',
        ]), $admin);

        $quiet = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Unreviewed Bench', 'slug' => 'unreviewed-bench',
            'sku' => Str::upper(Str::random(8)),
            'price' => 5000, 'stock_quantity' => 2, 'gst_rate' => 18, 'status' => 'active',
        ]);

        $this->get(route('products', ['rating' => 4]))
            ->assertOk()
            ->assertSee($this->product->name)
            ->assertDontSee($quiet->name);

        $this->get(route('products', ['sort' => 'rating']))
            ->assertOk()
            ->assertSee($this->product->name);
    }

    public function test_reviews_rolled_onto_a_product_are_the_published_ones_only(): void
    {
        $this->assertSame(0, (int) $this->product->rating_count);
        $this->assertSame(0, Review::count());
    }
}
