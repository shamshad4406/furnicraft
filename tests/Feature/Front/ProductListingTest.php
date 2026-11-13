<?php

namespace Tests\Feature\Front;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductListingTest extends TestCase
{
    use RefreshDatabase;

    protected Category $steel;

    protected Category $almirah;

    protected Category $sofa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $this->steel = Category::create(['name' => 'Steel Furniture', 'slug' => 'steel-furniture', 'is_active' => true]);
        $this->almirah = Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'parent_id' => $this->steel->id, 'is_active' => true]);
        $this->sofa = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);

        $this->product('Steel Almirah 3 Door', 'STL-1', $this->almirah, 14500);
        $this->product('Steel Shoe Cabinet', 'STL-2', $this->steel, 6400);
        $this->product('3 Seater Sofa', 'SOF-1', $this->sofa, 18999);
    }

    protected function product(string $name, string $sku, Category $category, float $price, array $extra = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $category->id,
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'sku' => $sku,
            'price' => $price,
            'status' => 'active',
        ], $extra));
    }

    /* ---------------------------------------------- the reported behaviour */

    public function test_a_category_page_shows_only_that_categorys_products(): void
    {
        $this->get(route('category.show', 'steel-furniture'))
            ->assertOk()
            ->assertSee('Steel Furniture')
            ->assertSee('Steel Almirah 3 Door')   // in a sub-category
            ->assertSee('Steel Shoe Cabinet')     // directly in this category
            ->assertDontSee('3 Seater Sofa');     // a different category
    }

    public function test_a_sub_category_page_shows_only_its_own_products(): void
    {
        $this->get(route('category.show', 'almirah'))
            ->assertOk()
            ->assertSee('Steel Almirah 3 Door')
            ->assertDontSee('Steel Shoe Cabinet')
            ->assertDontSee('3 Seater Sofa');
    }

    public function test_the_sofa_category_does_not_leak_steel_products(): void
    {
        $this->get(route('category.show', 'sofa'))
            ->assertOk()
            ->assertSee('3 Seater Sofa')
            ->assertDontSee('Steel Almirah 3 Door')
            ->assertDontSee('Steel Shoe Cabinet');
    }

    /* -------------------------------------------------------- visibility */

    public function test_draft_and_inactive_products_are_hidden(): void
    {
        $this->product('Draft Almirah', 'STL-9', $this->almirah, 1000, ['status' => 'draft']);
        $this->product('Inactive Almirah', 'STL-8', $this->almirah, 1000, ['status' => 'inactive']);

        $this->get(route('category.show', 'steel-furniture'))
            ->assertOk()
            ->assertDontSee('Draft Almirah')
            ->assertDontSee('Inactive Almirah');
    }

    public function test_an_inactive_category_page_returns_404(): void
    {
        Category::create(['name' => 'Hidden', 'slug' => 'hidden-cat', 'is_active' => false]);

        $this->get(route('category.show', 'hidden-cat'))->assertNotFound();
    }

    public function test_an_unknown_category_slug_returns_404(): void
    {
        $this->get(route('category.show', 'does-not-exist'))->assertNotFound();
    }

    /* ----------------------------------------------------------- listing */

    public function test_the_products_page_lists_every_active_product(): void
    {
        $this->get(route('products'))
            ->assertOk()
            ->assertSee('Steel Almirah 3 Door')
            ->assertSee('Steel Shoe Cabinet')
            ->assertSee('3 Seater Sofa');
    }

    public function test_the_products_page_can_be_filtered_by_category(): void
    {
        $this->get(route('products', ['category' => 'sofa']))
            ->assertOk()
            ->assertSee('3 Seater Sofa')
            ->assertDontSee('Steel Shoe Cabinet');
    }

    public function test_header_search_finds_products_by_name_and_sku(): void
    {
        $this->get(route('products', ['q' => 'Almirah']))
            ->assertOk()
            ->assertSee('Steel Almirah 3 Door')
            ->assertDontSee('3 Seater Sofa');

        $this->get(route('products', ['q' => 'SOF-1']))
            ->assertOk()
            ->assertSee('3 Seater Sofa')
            ->assertDontSee('Steel Shoe Cabinet');
    }

    public function test_a_search_with_no_matches_shows_an_empty_state(): void
    {
        $this->get(route('products', ['q' => 'zzzznothing']))
            ->assertOk()
            ->assertSee('Nothing matched')
            ->assertDontSee('3 Seater Sofa');
    }

    public function test_products_can_be_filtered_by_brand(): void
    {
        $brand = Brand::create(['name' => 'UrbanNest', 'slug' => 'urbannest', 'is_active' => true]);
        $this->product('Branded Chair', 'BR-1', $this->sofa, 5000, ['brand_id' => $brand->id]);

        $this->get(route('products', ['brand' => 'urbannest']))
            ->assertOk()
            ->assertSee('Branded Chair')
            ->assertDontSee('Steel Shoe Cabinet');
    }

    /* -------------------------------------------------------- sorting */

    public function test_sorting_by_price_low_to_high_orders_on_the_effective_price(): void
    {
        // Sale price must win over the list price when sorting.
        $this->product('Discounted Premium', 'DSC-1', $this->sofa, 90000, ['sale_price' => 100]);

        $html = $this->get(route('products', ['sort' => 'price_low']))->assertOk()->getContent();

        $first = strpos($html, 'Discounted Premium');
        $second = strpos($html, 'Steel Shoe Cabinet');

        $this->assertNotFalse($first);
        $this->assertLessThan($second, $first, 'The ₹100 sale price should sort before the ₹6,400 product.');
    }

    public function test_sorting_by_price_high_to_low_reverses_the_order(): void
    {
        $html = $this->get(route('products', ['sort' => 'price_high']))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Steel Shoe Cabinet'),
            strpos($html, '3 Seater Sofa'),
            'The ₹18,999 sofa should sort before the ₹6,400 cabinet.'
        );
    }

    public function test_sorting_by_name_is_alphabetical(): void
    {
        $html = $this->get(route('products', ['sort' => 'name']))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'Steel Almirah 3 Door'),
            strpos($html, '3 Seater Sofa'),
            'Names starting with a digit sort before letters.'
        );
    }

    public function test_the_sort_control_keeps_the_current_filters(): void
    {
        $this->get(route('products', ['category' => 'sofa', 'sort' => 'name']))
            ->assertOk()
            ->assertSee('name="category" value="sofa"', false);
    }

    /* ------------------------------------------------------ navigation */

    public function test_the_header_dropdown_links_to_category_pages(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('category.show', 'steel-furniture'), false);
    }

    public function test_pagination_appears_past_twelve_products(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->product('Bulk Sofa '.$i, 'BULK-'.$i, $this->sofa, 1000 + $i);
        }

        $this->get(route('category.show', 'sofa'))
            ->assertOk()
            ->assertSee('of 16 products')
            ->assertSee('page=2', false);
    }
}
