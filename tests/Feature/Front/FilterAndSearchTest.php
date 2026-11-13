<?php

namespace Tests\Feature\Front;

use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FilterAndSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Category $sofa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $this->sofa = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);

        $this->make('Teak Sofa', 'T-1', 30000, ['material' => 'Teak Wood', 'color' => 'Brown', 'stock_quantity' => 5]);
        $this->make('Steel Sofa', 'S-1', 10000, ['material' => 'Steel', 'color' => 'Grey', 'stock_quantity' => 0]);
        $this->make('Discount Sofa', 'D-1', 20000, ['material' => 'Teak Wood', 'color' => 'Blue', 'sale_price' => 12000, 'stock_quantity' => 3]);
    }

    protected function make(string $name, string $sku, float $price, array $extra = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $this->sofa->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => $sku,
            'price' => $price,
            'status' => 'active',
        ], $extra));
    }

    /* --------------------------------------------------------- filters */

    public function test_filtering_by_material(): void
    {
        $this->get(route('products', ['material' => 'Teak Wood']))
            ->assertOk()
            ->assertSee('Teak Sofa')
            ->assertSee('Discount Sofa')
            ->assertDontSee('Steel Sofa');
    }

    public function test_filtering_by_colour(): void
    {
        $this->get(route('products', ['color' => 'Grey']))
            ->assertOk()
            ->assertSee('Steel Sofa')
            ->assertDontSee('Teak Sofa');
    }

    public function test_filtering_by_price_range_uses_the_effective_price(): void
    {
        // Discount Sofa lists at 20,000 but sells at 12,000, so a 15,000 cap
        // must include it.
        $this->get(route('products', ['max_price' => 15000]))
            ->assertOk()
            ->assertSee('Steel Sofa')
            ->assertSee('Discount Sofa')
            ->assertDontSee('Teak Sofa');
    }

    public function test_filtering_by_minimum_price(): void
    {
        $this->get(route('products', ['min_price' => 25000]))
            ->assertOk()
            ->assertSee('Teak Sofa')
            ->assertDontSee('Steel Sofa');
    }

    public function test_filtering_by_in_stock_only(): void
    {
        $this->get(route('products', ['in_stock' => 1]))
            ->assertOk()
            ->assertSee('Teak Sofa')
            ->assertDontSee('Steel Sofa');
    }

    public function test_a_product_is_in_stock_when_any_variant_has_stock(): void
    {
        $product = $this->make('Variant Sofa', 'V-1', 5000, ['stock_quantity' => 0]);
        $product->variants()->create(['sku' => 'V-1-A', 'size' => 'Large', 'stock_quantity' => 3, 'is_active' => true]);

        $this->get(route('products', ['in_stock' => 1]))
            ->assertOk()
            ->assertSee('Variant Sofa');
    }

    public function test_filtering_by_offers(): void
    {
        $this->get(route('products', ['discount' => 1]))
            ->assertOk()
            ->assertSee('Discount Sofa')
            ->assertDontSee('Teak Sofa');
    }

    public function test_filtering_by_size_looks_at_variants(): void
    {
        $product = $this->make('Sized Sofa', 'Z-1', 7000);
        $product->variants()->create(['sku' => 'Z-1-K', 'size' => 'King', 'stock_quantity' => 1, 'is_active' => true]);

        $this->get(route('products', ['size' => 'King']))
            ->assertOk()
            ->assertSee('Sized Sofa')
            ->assertDontSee('Teak Sofa');
    }

    public function test_filters_combine(): void
    {
        $this->get(route('products', ['material' => 'Teak Wood', 'discount' => 1]))
            ->assertOk()
            ->assertSee('Discount Sofa')
            ->assertDontSee('Teak Sofa');
    }

    public function test_filters_apply_on_a_category_page_too(): void
    {
        $this->get(route('category.show', ['slug' => 'sofa', 'material' => 'Steel']))
            ->assertOk()
            ->assertSee('Steel Sofa')
            ->assertDontSee('Teak Sofa');
    }

    public function test_the_sidebar_only_offers_values_that_exist(): void
    {
        $this->get(route('products'))
            ->assertOk()
            ->assertSee('Teak Wood')
            ->assertSee('Steel')
            ->assertDontSee('Velvet');
    }

    /* ---------------------------------------------- search suggestions */

    public function test_suggestions_return_matching_products(): void
    {
        $response = $this->getJson(route('search.suggest', ['q' => 'Teak']))->assertOk();

        $this->assertSame('Teak Sofa', $response->json('products.0.name'));
        $this->assertSame('T-1', $response->json('products.0.sku'));
        $this->assertStringContainsString('/product/teak-sofa', $response->json('products.0.url'));
    }

    public function test_suggestions_match_on_sku(): void
    {
        $this->getJson(route('search.suggest', ['q' => 'D-1']))
            ->assertOk()
            ->assertJsonPath('products.0.name', 'Discount Sofa');
    }

    public function test_suggestions_include_categories(): void
    {
        $this->getJson(route('search.suggest', ['q' => 'Sof']))
            ->assertOk()
            ->assertJsonPath('categories.0.name', 'Sofa');
    }

    public function test_a_one_character_query_returns_nothing(): void
    {
        $this->getJson(route('search.suggest', ['q' => 'a']))
            ->assertOk()
            ->assertJson(['products' => [], 'categories' => []]);
    }

    public function test_suggestions_skip_draft_products(): void
    {
        $this->make('Draft Sofa', 'DR-1', 1000, ['status' => 'draft']);

        $response = $this->getJson(route('search.suggest', ['q' => 'Draft']))->assertOk();

        $this->assertSame([], $response->json('products'));
    }
}
