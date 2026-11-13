<?php

namespace Tests\Feature\Front;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $this->category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => '3 Seater Fabric Sofa',
            'slug' => '3-seater-fabric-sofa',
            'sku' => 'SOF-1',
            'short_description' => 'A roomy three seater.',
            'description' => 'Solid sheesham frame with high-density foam.',
            'material' => 'Sheesham Wood',
            'dimensions' => '198 x 86 x 82 cm',
            'warranty' => '3 Years',
            'price' => 22999,
            'sale_price' => 18999,
            'stock_quantity' => 4,
            'status' => 'active',
        ]);

        $this->product->specifications()->create(['name' => 'Seating Capacity', 'value' => '3 Seater']);
    }

    public function test_the_product_page_shows_the_full_detail(): void
    {
        $this->get(route('product.show', '3-seater-fabric-sofa'))
            ->assertOk()
            ->assertSee('3 Seater Fabric Sofa')
            ->assertSee('SOF-1')
            ->assertSee('A roomy three seater.')
            ->assertSee('Sheesham Wood')
            ->assertSee('198 x 86 x 82 cm')
            ->assertSee('3 Years')
            ->assertSee('Seating Capacity')
            ->assertSee('Solid sheesham frame');
    }

    public function test_the_sale_price_is_shown_with_the_original_struck_through(): void
    {
        $this->get(route('product.show', '3-seater-fabric-sofa'))
            ->assertOk()
            ->assertSee('₹ 18,999')
            ->assertSee('₹ 22,999');
    }

    public function test_a_draft_product_is_not_reachable(): void
    {
        $this->product->update(['status' => 'draft']);

        $this->get(route('product.show', '3-seater-fabric-sofa'))->assertNotFound();
    }

    public function test_an_unknown_slug_returns_404(): void
    {
        $this->get(route('product.show', 'no-such-product'))->assertNotFound();
    }

    public function test_variants_are_rendered_with_their_own_price_and_stock(): void
    {
        $this->product->variants()->create([
            'sku' => 'SOF-1-BLU', 'size' => '3 Seater', 'color' => 'Blue',
            'price' => 20999, 'stock_quantity' => 2, 'is_active' => true,
        ]);
        $this->product->variants()->create([
            'sku' => 'SOF-1-GRY', 'size' => '2 Seater', 'color' => 'Grey',
            'price' => 15999, 'stock_quantity' => 0, 'is_active' => true,
        ]);

        $response = $this->get(route('product.show', '3-seater-fabric-sofa'))->assertOk();

        $response->assertSee('3 Seater / Blue')
            ->assertSee('2 Seater / Grey')
            ->assertSee('₹ 20,999')
            ->assertSee('₹ 15,999');

        // The out-of-stock option must not be selectable.
        $this->assertStringContainsString(
            'disabled',
            preg_replace('/\s+/', ' ', $response->getContent())
        );
    }

    public function test_an_inactive_variant_is_not_offered(): void
    {
        $this->product->variants()->create([
            'sku' => 'SOF-1-OLD', 'size' => 'Discontinued', 'stock_quantity' => 5, 'is_active' => false,
        ]);

        $this->get(route('product.show', '3-seater-fabric-sofa'))
            ->assertOk()
            ->assertDontSee('Discontinued');
    }

    public function test_related_products_come_from_the_same_category(): void
    {
        Product::create([
            'category_id' => $this->category->id, 'name' => 'Sibling Sofa', 'slug' => 'sibling-sofa',
            'sku' => 'SOF-2', 'price' => 9999, 'status' => 'active',
        ]);

        $this->get(route('product.show', '3-seater-fabric-sofa'))
            ->assertOk()
            ->assertSee('Related products')
            ->assertSee('Sibling Sofa');
    }

    public function test_viewing_a_product_records_it_as_recently_viewed(): void
    {
        $other = Product::create([
            'category_id' => $this->category->id, 'name' => 'Second Sofa', 'slug' => 'second-sofa',
            'sku' => 'SOF-3', 'price' => 5000, 'status' => 'active',
        ]);

        $this->get(route('product.show', '3-seater-fabric-sofa'))->assertOk();
        $this->assertDatabaseHas('recently_viewed', ['product_id' => $this->product->id]);

        $this->get(route('product.show', 'second-sofa'))->assertOk();
        $this->assertDatabaseHas('recently_viewed', ['product_id' => $other->id]);

        // The list itself is asserted in RecentlyViewedTest: the test client
        // issues a fresh session per request, so it cannot carry a visitor
        // across two page loads.
    }

    public function test_the_recently_viewed_row_renders_when_there_is_something_to_show(): void
    {
        $html = view('front.partials.product-row', [
            'title' => 'Recently viewed',
            'items' => Product::where('id', $this->product->id)->with('images')->get(),
        ])->render();

        $this->assertStringContainsString('Recently viewed', $html);
        $this->assertStringContainsString('3 Seater Fabric Sofa', $html);
    }

    public function test_the_recently_viewed_row_is_hidden_when_empty(): void
    {
        $html = view('front.partials.product-row', [
            'title' => 'Recently viewed',
            'items' => Product::whereRaw('1 = 0')->get(),
        ])->render();

        $this->assertStringNotContainsString('Recently viewed', $html);
    }

    public function test_the_breadcrumb_links_back_to_the_category(): void
    {
        $this->get(route('product.show', '3-seater-fabric-sofa'))
            ->assertOk()
            ->assertSee(route('category.show', 'sofa'), false);
    }

    public function test_an_out_of_stock_product_says_so(): void
    {
        $this->product->update(['stock_quantity' => 0]);

        $this->get(route('product.show', '3-seater-fabric-sofa'))
            ->assertOk()
            ->assertSee('out of stock');
    }
}
