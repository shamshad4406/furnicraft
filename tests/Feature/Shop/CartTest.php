<?php

namespace Tests\Feature\Shop;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Product;
use App\Services\CartService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected CartService $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $this->category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);
        $this->cart = app(CartService::class);
    }

    protected function product(string $name, float $price, int $stock = 10, array $extra = []): Product
    {
        return Product::create(array_merge([
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => Str::upper(Str::random(8)),
            'price' => $price,
            'stock_quantity' => $stock,
            'gst_rate' => 18,
            'status' => 'active',
        ], $extra));
    }

    /** A request bound to the app session, for driving the service directly. */
    /** Signed-in customer, so the cart survives across test requests. */
    protected function actingAsCustomer(): Customer
    {
        $customer = Customer::create([
            "name" => "Test Buyer", "email" => "buyer@example.com",
            "password" => "Welcome123", "is_active" => true,
        ]);

        $this->actingAs($customer, "customer");

        return $customer;
    }

    protected function request(): Request
    {
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    /* ------------------------------------------------------- adding */

    public function test_a_product_can_be_added_to_the_cart(): void
    {
        $product = $this->product('Blue Sofa', 10000);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertRedirect(route('cart.index'));

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_adding_the_same_product_again_increases_the_quantity(): void
    {
        $product = $this->product('Blue Sofa', 10000);
        $request = $this->request();

        $this->cart->add($request, $product, null, 2);
        $this->cart->add($request, $product, null, 3);

        $this->assertSame(1, \App\Models\CartItem::count());
        $this->assertSame(5, \App\Models\CartItem::first()->quantity);
    }

    public function test_the_quantity_is_capped_at_available_stock(): void
    {
        $product = $this->product('Scarce Sofa', 5000, stock: 3);
        $request = $this->request();

        $result = $this->cart->add($request, $product, null, 10);

        $this->assertTrue($result['ok']);
        $this->assertSame(3, \App\Models\CartItem::first()->quantity);
    }

    public function test_an_out_of_stock_product_cannot_be_added(): void
    {
        $product = $this->product('Gone Sofa', 5000, stock: 0);

        $result = $this->cart->add($this->request(), $product, null, 1);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('out of stock', $result['message']);
        $this->assertSame(0, \App\Models\CartItem::count());
    }

    public function test_a_draft_product_cannot_be_added(): void
    {
        $product = $this->product('Draft Sofa', 5000, extra: ['status' => 'draft']);

        $result = $this->cart->add($this->request(), $product, null, 1);

        $this->assertFalse($result['ok']);
    }

    public function test_the_quantity_cannot_exceed_the_per_line_maximum(): void
    {
        $product = $this->product('Bulk Sofa', 1000, stock: 500);

        $this->cart->add($this->request(), $product, null, 999);

        $this->assertSame(CartService::MAX_QUANTITY, \App\Models\CartItem::first()->quantity);
    }

    /* ----------------------------------------------- update / remove */

    public function test_setting_the_quantity_to_zero_removes_the_line(): void
    {
        $this->actingAsCustomer();
        $product = $this->product('Blue Sofa', 10000);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2]);
        $item = \App\Models\CartItem::firstOrFail();

        $this->patch(route('cart.update', $item), ['quantity' => 0])->assertRedirect();

        $this->assertSame(0, \App\Models\CartItem::count());
    }

    public function test_an_item_can_be_removed(): void
    {
        $this->actingAsCustomer();
        $product = $this->product('Blue Sofa', 10000);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1]);
        $item = \App\Models\CartItem::firstOrFail();

        $this->delete(route('cart.remove', $item))->assertRedirect();

        $this->assertSame(0, \App\Models\CartItem::count());
    }

    /* ------------------------------------------------- guest merging */

    public function test_a_guest_cart_is_adopted_on_sign_in(): void
    {
        $product = $this->product('Blue Sofa', 10000);
        $request = $this->request();

        $this->cart->add($request, $product, null, 2);
        $guestCart = Cart::first();
        $this->assertNull($guestCart->customer_id);

        $customer = Customer::create([
            'name' => 'A', 'email' => 'a@example.com', 'password' => 'Welcome123', 'is_active' => true,
        ]);

        $this->cart->mergeGuestCart($request->session()->getId(), $customer->id);

        $this->assertSame($customer->id, Cart::first()->customer_id);
        $this->assertSame(2, \App\Models\CartItem::first()->quantity);
    }

    public function test_merging_adds_quantities_when_the_customer_already_had_a_cart(): void
    {
        $product = $this->product('Blue Sofa', 10000, stock: 50);
        $request = $this->request();

        $customer = Customer::create([
            'name' => 'A', 'email' => 'a@example.com', 'password' => 'Welcome123', 'is_active' => true,
        ]);

        // The customer's existing cart.
        $customerCart = Cart::create(['customer_id' => $customer->id]);
        $customerCart->items()->create([
            'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 10000,
        ]);

        // And a guest cart in this session.
        $this->cart->add($request, $product, null, 2);

        $this->cart->mergeGuestCart($request->session()->getId(), $customer->id);

        $this->assertSame(1, Cart::count());
        $this->assertSame(3, \App\Models\CartItem::first()->quantity);
    }

    /**
     * Signing in regenerates the session, which changes the id the guest cart
     * row is keyed on. The merge therefore has to be handed the id from
     * *before* sign-in — handing it the current one silently loses the cart.
     */
    public function test_the_merge_uses_the_session_id_the_guest_cart_was_written_under(): void
    {
        $product = $this->product('Blue Sofa', 10000);
        $request = $this->request();

        $this->cart->add($request, $product, null, 2);
        $guestSessionId = $request->session()->getId();

        $customer = Customer::create([
            'name' => 'A', 'email' => 'a@example.com', 'password' => 'Welcome123', 'is_active' => true,
        ]);

        // What signing in does to the session.
        $request->session()->regenerate();
        $newSessionId = $request->session()->getId();
        $this->assertNotSame($guestSessionId, $newSessionId);

        // The post-regeneration id matches nothing, so nothing is adopted.
        $this->cart->mergeGuestCart($newSessionId, $customer->id);
        $this->assertNull(Cart::first()->customer_id);

        // The id from before sign-in does carry the cart across.
        $this->cart->mergeGuestCart($guestSessionId, $customer->id);
        $this->assertSame($customer->id, Cart::first()->customer_id);
        $this->assertSame(2, \App\Models\CartItem::first()->quantity);
    }

    /* ------------------------------------------------------ problems */

    public function test_the_cart_reports_a_line_that_outgrew_its_stock(): void
    {
        $product = $this->product('Blue Sofa', 10000, stock: 10);
        $request = $this->request();
        $this->cart->add($request, $product, null, 5);

        $product->update(['stock_quantity' => 2]);

        $cart = $this->cart->current($request);
        $problems = $this->cart->problems($cart);

        $this->assertNotEmpty($problems);
        $this->assertStringContainsString('Only 2 left', $problems[0]);
        $this->assertFalse($this->cart->isCheckoutable($cart));
    }

    public function test_the_cart_reports_a_price_change(): void
    {
        $product = $this->product('Blue Sofa', 10000);
        $request = $this->request();
        $this->cart->add($request, $product, null, 1);

        $product->update(['price' => 12000]);

        $problems = $this->cart->problems($this->cart->current($request));

        $this->assertStringContainsString('price of Blue Sofa changed', implode(' ', $problems));
    }

    /* ------------------------------------------------------- coupons */

    public function test_a_valid_coupon_can_be_applied(): void
    {
        $this->actingAsCustomer();
        $product = $this->product('Blue Sofa', 10000);
        $this->post(route('cart.add'), ['product_id' => $product->id]);

        Coupon::create([
            'code' => 'SAVE10', 'type' => 'percentage', 'value' => 10, 'is_active' => true,
        ]);

        $this->post(route('cart.coupon.apply'), ['code' => 'save10'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('SAVE10', Cart::first()->coupon_code);
    }

    public function test_an_unknown_coupon_is_refused(): void
    {
        $product = $this->product('Blue Sofa', 10000);
        $this->cart->add($this->request(), $product, null, 1);

        $this->post(route('cart.coupon.apply'), ['code' => 'NOPE'])
            ->assertSessionHas('error');
    }

    public function test_a_coupon_below_its_minimum_order_value_is_refused(): void
    {
        $product = $this->product('Cheap Stool', 500);
        $this->cart->add($this->request(), $product, null, 1);

        Coupon::create([
            'code' => 'BIG', 'type' => 'fixed', 'value' => 100,
            'min_order_value' => 5000, 'is_active' => true,
        ]);

        $response = $this->post(route('cart.coupon.apply'), ['code' => 'BIG']);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('or more', session('error'));
    }

    public function test_a_coupon_can_be_removed(): void
    {
        $this->actingAsCustomer();
        $product = $this->product('Blue Sofa', 10000);
        $this->post(route('cart.add'), ['product_id' => $product->id]);

        Coupon::create(['code' => 'SAVE10', 'type' => 'percentage', 'value' => 10, 'is_active' => true]);
        $this->post(route('cart.coupon.apply'), ['code' => 'SAVE10']);

        $this->delete(route('cart.coupon.remove'))->assertRedirect();

        $this->assertNull(Cart::first()->coupon_id);
    }
}
