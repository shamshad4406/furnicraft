<?php

namespace Tests\Feature\Front;

use App\Models\Category;
use App\Models\Product;
use App\Services\RecentlyViewedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The service is exercised directly: the HTTP test client starts a new session
 * on every request, so a visitor cannot be followed across page loads there.
 */
class RecentlyViewedTest extends TestCase
{
    use RefreshDatabase;

    protected RecentlyViewedService $service;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RecentlyViewedService::class);
        $this->category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);
    }

    protected function product(string $name): Product
    {
        return Product::create([
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => Str::upper(Str::random(6)),
            'price' => 1000,
            'status' => 'active',
        ]);
    }

    /**
     * A request bound to the application's session, so the service sees a
     * stable session id across calls.
     */
    protected function request(): Request
    {
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    public function test_products_come_back_most_recently_viewed_first(): void
    {
        $request = $this->request();

        $first = $this->product('First Sofa');
        $second = $this->product('Second Sofa');
        $third = $this->product('Third Sofa');

        // A real visitor does not open three pages in the same instant.
        $this->service->record($request, $first);
        $this->travel(1)->seconds();
        $this->service->record($request, $second);
        $this->travel(1)->seconds();
        $this->service->record($request, $third);

        $names = $this->service->products($request)->pluck('name')->all();

        $this->assertSame(['Third Sofa', 'Second Sofa', 'First Sofa'], $names);
    }

    public function test_the_product_being_viewed_is_excluded(): void
    {
        $request = $this->request();

        $a = $this->product('Sofa A');
        $b = $this->product('Sofa B');

        $this->service->record($request, $a);
        $this->travel(1)->seconds();
        $this->service->record($request, $b);

        $names = $this->service->products($request, $b)->pluck('name')->all();

        $this->assertSame(['Sofa A'], $names);
    }

    public function test_viewing_the_same_product_again_moves_it_to_the_front_without_duplicating(): void
    {
        $request = $this->request();

        $a = $this->product('Sofa A');
        $b = $this->product('Sofa B');

        $this->service->record($request, $a);
        $this->travel(1)->seconds();
        $this->service->record($request, $b);
        $this->travel(1)->seconds();
        $this->service->record($request, $a);

        $this->assertSame(2, \App\Models\RecentlyViewed::count());
        $this->assertSame(['Sofa A', 'Sofa B'], $this->service->products($request)->pluck('name')->all());
    }

    public function test_the_list_is_trimmed_to_the_keep_limit(): void
    {
        $request = $this->request();

        for ($i = 1; $i <= RecentlyViewedService::KEEP + 4; $i++) {
            $this->service->record($request, $this->product('Sofa '.$i));
        }

        $this->assertSame(RecentlyViewedService::KEEP, \App\Models\RecentlyViewed::count());
    }

    public function test_draft_products_drop_out_of_the_list(): void
    {
        $request = $this->request();

        $live = $this->product('Live Sofa');
        $draft = $this->product('Draft Sofa');

        $this->service->record($request, $live);
        $this->service->record($request, $draft);

        $draft->update(['status' => 'draft']);

        $this->assertSame(['Live Sofa'], $this->service->products($request)->pluck('name')->all());
    }

    public function test_another_visitor_does_not_see_the_first_ones_history(): void
    {
        $request = $this->request();
        $this->service->record($request, $this->product('Mine'));

        // A different session id stands in for a second visitor.
        app('session.store')->setId(Str::random(40));
        $other = $this->request();

        $this->assertTrue($this->service->products($other)->isEmpty());
    }
}
