<?php

namespace Tests\Feature\Security;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
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

    /* ------------------------------------------------ response headers */

    public function test_security_headers_are_sent_on_the_storefront(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
    }

    public function test_the_csp_does_not_allow_inline_scripts(): void
    {
        $csp = $this->get(route('home'))->headers->get('Content-Security-Policy');

        // 'unsafe-inline' is allowed for styles only, never for scripts.
        $scriptDirective = collect(explode(';', $csp))
            ->first(fn ($part) => str_contains($part, 'script-src'));

        $this->assertStringNotContainsString('unsafe-inline', (string) $scriptDirective);
        $this->assertStringNotContainsString('unsafe-eval', (string) $scriptDirective);
    }

    public function test_no_view_renders_an_inline_event_handler(): void
    {
        // Inline handlers would be silently broken by the CSP.
        $files = glob(resource_path('views/**/*.blade.php'), GLOB_BRACE) ?: [];
        $files = array_merge($files, glob(resource_path('views/*/*/*.blade.php')) ?: []);

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/\son(click|change|submit|load|error|input)\s*=\s*"/i',
                (string) file_get_contents($file),
                basename($file).' contains an inline event handler, which the CSP blocks.'
            );
        }
    }

    public function test_admin_pages_are_not_cached_by_the_browser(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'))->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_the_admin_panel_asks_search_engines_not_to_index_it(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('noindex, nofollow', false);
    }

    /* ----------------------------------------------------- file uploads */

    public function test_an_svg_logo_is_rejected(): void
    {
        Storage::fake('public');

        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.brands.store'), ['name' => 'Evil Brand', 'logo' => $svg])
            ->assertSessionHasErrors('logo');

        $this->assertDatabaseMissing('brands', ['name' => 'Evil Brand']);
    }

    public function test_a_php_file_renamed_as_an_image_is_rejected(): void
    {
        Storage::fake('public');

        $payload = UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo shell_exec($_GET["c"]); ?>');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.brands.store'), ['name' => 'Shell Brand', 'logo' => $payload])
            ->assertSessionHasErrors('logo');

        $this->assertDatabaseMissing('brands', ['name' => 'Shell Brand']);
    }

    public function test_an_unreadable_image_returns_a_field_error_not_a_500(): void
    {
        Storage::fake('public');

        // Passes the rules by extension but is not decodable.
        $broken = UploadedFile::fake()->createWithContent('broken.png', str_repeat('x', 400));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.brands.store'), ['name' => 'Broken', 'logo' => $broken])
            ->assertSessionHasErrors('logo');
    }

    public function test_uploaded_images_are_stored_as_webp_not_their_original_type(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin, 'admin')->post(route('admin.brands.store'), [
            'name' => 'Good Brand',
            'logo' => UploadedFile::fake()->image('logo.png', 300, 200),
        ])->assertSessionHasNoErrors();

        $path = Brand::where('name', 'Good Brand')->value('logo');

        $this->assertStringEndsWith('.webp', $path);
    }

    /* ---------------------------------------------------- stored secrets */

    public function test_the_smtp_password_is_encrypted_at_rest(): void
    {
        $settings = app(SettingService::class);
        $settings->set('mail_password', 'super-secret-value', 'mail');

        $raw = \DB::table('settings')->where('key', 'mail_password')->value('value');

        $this->assertNotSame('super-secret-value', $raw, 'The SMTP password must not be stored in clear text.');
        $this->assertSame('super-secret-value', $settings->get('mail_password'));
    }

    public function test_the_smtp_password_is_never_rendered_into_the_settings_form(): void
    {
        app(SettingService::class)->set('mail_password', 'super-secret-value', 'mail');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.settings.index', ['tab' => 'mail']))
            ->assertOk()
            ->assertDontSee('super-secret-value');
    }

    public function test_submitting_the_mail_form_with_a_blank_password_keeps_the_stored_one(): void
    {
        $settings = app(SettingService::class);
        $settings->set('mail_password', 'keep-me', 'mail');

        $this->actingAs($this->admin, 'admin')->put(route('admin.settings.update'), [
            'group' => 'mail',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_password' => '',
            'mail_from_address' => 'no-reply@furnicraft.in',
            'mail_from_name' => 'FurniCraft',
        ])->assertSessionHasNoErrors();

        $this->assertSame('keep-me', app(SettingService::class)->get('mail_password'));
    }

    /* ------------------------------------------------------------- XSS */

    public function test_a_script_tag_in_a_product_name_is_escaped(): void
    {
        $category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id,
            'name' => 'Sofa <script>alert(1)</script>',
            'slug' => 'xss-sofa', 'sku' => 'XSS-1', 'price' => 100, 'status' => 'active',
        ]);

        $response = $this->get(route('product.show', 'xss-sofa'))->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
        $response->assertSee('&lt;script&gt;', false);
    }

    public function test_html_in_cms_content_is_stripped_on_render(): void
    {
        CmsPage::create([
            'title' => 'Policy', 'slug' => 'policy-page', 'is_active' => true,
            'content' => 'Before <script>alert(1)</script> <img src=x onerror=alert(2)> after',
        ]);

        $response = $this->get(route('page', 'policy-page'))->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('onerror=', $html);
        $response->assertSee('after');
    }

    public function test_a_search_term_is_escaped_in_the_results_page(): void
    {
        $response = $this->get(route('products', ['q' => '"><script>alert(1)</script>']))->assertOk();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    /* ------------------------------------------------- SQL injection */

    public function test_a_sql_payload_in_a_filter_is_treated_as_text(): void
    {
        $category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);
        Product::create([
            'category_id' => $category->id, 'name' => 'Safe Sofa', 'slug' => 'safe-sofa',
            'sku' => 'SAFE-1', 'price' => 100, 'status' => 'active',
        ]);

        $this->get(route('products', ['material' => "' OR 1=1 -- "]))
            ->assertOk()
            ->assertDontSee('Safe Sofa');

        // The table is still there.
        $this->assertSame(1, Product::count());
    }

    public function test_a_sql_payload_in_the_search_box_is_treated_as_text(): void
    {
        $this->get(route('products', ['q' => "'; DROP TABLE products; --"]))->assertOk();

        $this->assertTrue(\Schema::hasTable('products'));
    }

    /* ------------------------------------------------- mass assignment */

    public function test_a_system_cms_page_cannot_be_deleted_or_have_its_slug_changed(): void
    {
        $page = CmsPage::create(['title' => 'Terms', 'slug' => 'terms-page', 'is_active' => true]);
        $page->forceFill(['is_system' => true])->save();

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.cms.update', $page), ['title' => 'Terms', 'slug' => 'hijacked-slug'])
            ->assertRedirect();

        $this->assertSame('terms-page', $page->fresh()->slug);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.cms.destroy', $page))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('cms_pages', ['id' => $page->id, 'deleted_at' => null]);
    }

    public function test_is_system_cannot_be_set_through_the_form(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.cms.store'), [
            'title' => 'Sneaky', 'is_system' => 1,
        ])->assertRedirect();

        $this->assertFalse((bool) CmsPage::where('title', 'Sneaky')->value('is_system'));
    }

    /* -------------------------------------------- guard separation */

    public function test_a_customer_session_cannot_reach_the_admin_panel(): void
    {
        $customer = Customer::create([
            'name' => 'Cust', 'email' => 'c@example.com', 'password' => 'Welcome123', 'is_active' => true,
        ]);

        foreach ([
            'admin.dashboard', 'admin.users.index', 'admin.settings.index',
            'admin.products.index', 'admin.cms.index',
        ] as $route) {
            $this->actingAs($customer, 'customer')
                ->get(route($route))
                ->assertRedirect(route('admin.login'));
        }
    }

    public function test_an_admin_session_does_not_authenticate_the_storefront(): void
    {
        $this->actingAs($this->admin, 'admin');

        $this->assertFalse(auth('customer')->check());
    }

    /* ----------------------------------------------------- rate limits */

    public function test_the_pincode_endpoint_is_rate_limited(): void
    {
        $hitLimit = false;

        for ($i = 0; $i < 40; $i++) {
            $status = $this->postJson(route('delivery.check'), ['pincode' => '110001'])->getStatusCode();

            if ($status === 429) {
                $hitLimit = true;
                break;
            }
        }

        $this->assertTrue($hitLimit, 'The delivery check should be throttled.');
    }

    public function test_the_search_suggest_endpoint_is_rate_limited(): void
    {
        $hitLimit = false;

        for ($i = 0; $i < 70; $i++) {
            if ($this->getJson(route('search.suggest', ['q' => 'sofa']))->getStatusCode() === 429) {
                $hitLimit = true;
                break;
            }
        }

        $this->assertTrue($hitLimit, 'Search suggestions should be throttled.');
    }
}
