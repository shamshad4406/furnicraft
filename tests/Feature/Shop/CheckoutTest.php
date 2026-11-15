<?php

namespace Tests\Feature\Shop;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Models\StockReservation;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PricingService;
use App\Services\StockService;
use App\Services\StockUnavailable;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected CartService $cart;

    protected OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $this->category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);
        $this->cart = app(CartService::class);
        $this->orders = app(OrderService::class);

        $zone = DeliveryZone::create([
            'name' => 'Local', 'delivery_days' => 3,
            'base_charge' => 500, 'per_kg_charge' => 10, 'free_above' => 50000,
            'cod_available' => true, 'is_active' => true,
        ]);

        Pincode::create([
            'pincode' => '110001', 'city' => 'New Delhi', 'state' => 'Delhi',
            'delivery_zone_id' => $zone->id, 'is_serviceable' => true, 'cod_available' => true,
        ]);
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
            'weight' => 10,
            'status' => 'active',
        ], $extra));
    }

    protected function request(): Request
    {
        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        return $request;
    }

    protected function cartWith(Product $product, int $qty = 1): Cart
    {
        $request = $this->request();
        $this->cart->add($request, $product, null, $qty);

        return $this->cart->current($request);
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkoutData(array $overrides = []): array
    {
        $address = [
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park', 'line2' => null, 'landmark' => null,
            'city' => 'New Delhi', 'state' => 'Delhi', 'pincode' => '110001',
        ];

        return array_merge([
            'customer_name' => 'Ravi Kumar',
            'customer_email' => 'ravi@example.com',
            'customer_phone' => '9811122233',
            'gst_number' => null,
            'shipping_address' => $address,
            'billing_address' => $address,
            'payment_method' => 'razorpay',
            'notes' => null,
        ], $overrides);
    }

    /* ---------------------------------------------------- totals */

    public function test_totals_extract_gst_from_an_inclusive_price(): void
    {
        $product = $this->product('Sofa', 11800, stock: 5);
        $cart = $this->cartWith($product, 1);

        $totals = app(PricingService::class)->totals($cart, '110001');

        $this->assertSame(11800.0, $totals->subtotal);
        // 11800 inclusive of 18% => 1800 tax.
        $this->assertEqualsWithDelta(1800.0, $totals->taxTotal, 0.5);
    }

    public function test_delivery_is_charged_by_zone_and_weight(): void
    {
        $product = $this->product('Sofa', 10000, stock: 5); // 10 kg each
        $cart = $this->cartWith($product, 2);               // 20 kg

        $totals = app(PricingService::class)->totals($cart, '110001');

        // base 500 + (10/kg * 20kg) = 700
        $this->assertSame(700.0, $totals->deliveryCharge);
        $this->assertSame(3, $totals->deliveryDays);
        $this->assertSame(20700.0, $totals->grandTotal);
    }

    public function test_delivery_is_free_above_the_zone_threshold(): void
    {
        $product = $this->product('Big Sofa', 60000, stock: 5);
        $cart = $this->cartWith($product, 1);

        $totals = app(PricingService::class)->totals($cart, '110001');

        $this->assertSame(0.0, $totals->deliveryCharge);
    }

    public function test_a_free_delivery_coupon_removes_the_charge(): void
    {
        $product = $this->product('Sofa', 10000, stock: 5);
        $cart = $this->cartWith($product, 1);

        $coupon = Coupon::create(['code' => 'FREEDEL', 'type' => 'free_delivery', 'is_active' => true]);
        $this->cart->applyCoupon($cart, $coupon);

        $totals = app(PricingService::class)->totals($cart->fresh('items'), '110001');

        $this->assertSame(0.0, $totals->deliveryCharge);
        $this->assertTrue($totals->freeDelivery);
    }

    public function test_a_percentage_coupon_is_capped_by_max_discount(): void
    {
        $product = $this->product('Sofa', 20000, stock: 5);
        $cart = $this->cartWith($product, 1);

        $coupon = Coupon::create([
            'code' => 'TEN', 'type' => 'percentage', 'value' => 10,
            'max_discount' => 1500, 'is_active' => true,
        ]);
        $this->cart->applyCoupon($cart, $coupon);

        $totals = app(PricingService::class)->totals($cart->fresh('items'), '110001');

        // 10% of 20000 = 2000, capped at 1500.
        $this->assertSame(1500.0, $totals->discount);
    }

    /* ------------------------------------- exit: no duplicate orders */

    public function test_submitting_the_same_checkout_twice_creates_one_order(): void
    {
        $product = $this->product('Sofa', 10000, stock: 10);
        $cart = $this->cartWith($product, 1);
        $key = (string) Str::uuid();

        $first = $this->orders->place($cart, $this->checkoutData(), $key);
        $second = $this->orders->place($cart->fresh('items'), $this->checkoutData(), $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::count());
    }

    public function test_a_different_attempt_creates_a_separate_order(): void
    {
        $product = $this->product('Sofa', 10000, stock: 10);
        $cart = $this->cartWith($product, 1);

        $this->orders->place($cart, $this->checkoutData(), (string) Str::uuid());
        $this->orders->place($cart->fresh('items'), $this->checkoutData(), (string) Str::uuid());

        $this->assertSame(2, Order::count());
    }

    public function test_the_order_captures_a_snapshot_of_every_line(): void
    {
        $product = $this->product('Sofa', 10000, stock: 10);
        $cart = $this->cartWith($product, 2);

        $order = $this->orders->place($cart, $this->checkoutData(), (string) Str::uuid());

        $item = $order->items->first();

        $this->assertSame('Sofa', $item->name);
        $this->assertSame($product->sku, $item->sku);
        $this->assertSame(2, $item->quantity);

        // Renaming the product must not rewrite the order.
        $product->update(['name' => 'Renamed Sofa', 'price' => 99999]);

        $this->assertSame('Sofa', $order->items()->first()->name);
        $this->assertSame('10000.00', $order->items()->first()->unit_price);
    }

    /* ------------------------------ exit: stock held then released */

    public function test_placing_an_order_holds_stock_for_thirty_minutes(): void
    {
        $product = $this->product('Sofa', 10000, stock: 5);
        $cart = $this->cartWith($product, 2);

        $order = $this->orders->place($cart, $this->checkoutData(), (string) Str::uuid());

        $reservation = StockReservation::where('order_id', $order->id)->first();

        $this->assertNotNull($reservation);
        $this->assertSame('held', $reservation->status);
        $this->assertSame(2, $reservation->quantity);
        $this->assertEqualsWithDelta(
            StockService::HOLD_MINUTES,
            now()->diffInMinutes($reservation->expires_at),
            1
        );

        // The catalog quantity is untouched until payment succeeds.
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_held_stock_is_not_available_to_another_customer(): void
    {
        $product = $this->product('Last Sofa', 10000, stock: 2);
        $cart = $this->cartWith($product, 2);

        $this->orders->place($cart, $this->checkoutData(), (string) Str::uuid());

        $this->assertSame(0, app(StockService::class)->available($product->fresh()));
    }

    public function test_an_order_that_cannot_be_held_is_not_created(): void
    {
        $product = $this->product('Last Sofa', 10000, stock: 1);
        $cart = $this->cartWith($product, 1);

        // Someone else takes the last unit first.
        $other = $this->orders->place($this->cartWith($product, 1), $this->checkoutData(), (string) Str::uuid());
        $this->assertNotNull($other);

        $product->update(['stock_quantity' => 1]);

        $this->expectException(StockUnavailable::class);

        try {
            $this->orders->place($cart->fresh('items'), $this->checkoutData(), (string) Str::uuid());
        } finally {
            // The failed attempt leaves no half-built order behind.
            $this->assertSame(1, Order::count());
        }
    }

    public function test_a_failed_payment_releases_the_hold(): void
    {
        $product = $this->product('Sofa', 10000, stock: 3);
        $cart = $this->cartWith($product, 3);

        $order = $this->orders->place($cart, $this->checkoutData(), (string) Str::uuid());
        $this->assertSame(0, app(StockService::class)->available($product->fresh()));

        $this->orders->fail($order, 'Card declined');

        $this->assertSame('released', StockReservation::where('order_id', $order->id)->first()->status);
        $this->assertSame(3, app(StockService::class)->available($product->fresh()));
        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_an_expired_hold_is_swept_up(): void
    {
        $product = $this->product('Sofa', 10000, stock: 3);
        $cart = $this->cartWith($product, 3);

        $this->orders->place($cart, $this->checkoutData(), (string) Str::uuid());

        $this->travel(StockService::HOLD_MINUTES + 1)->minutes();

        $released = app(StockService::class)->releaseExpired();

        $this->assertSame(1, $released);
        $this->assertSame(3, app(StockService::class)->available($product->fresh()));
    }

    /**
     * The sweep only ever runs because the scheduler calls this command, so
     * the command itself has to work — and has to be scheduled.
     */
    public function test_the_scheduled_command_releases_expired_holds(): void
    {
        $product = $this->product('Sofa', 10000, stock: 3);
        $cart = $this->cartWith($product, 3);

        $this->orders->place($cart, $this->checkoutData(), (string) Str::uuid());
        $this->assertSame(0, app(StockService::class)->available($product->fresh()));

        $this->travel(StockService::HOLD_MINUTES + 1)->minutes();

        $this->artisan('stock:release-expired')
            ->expectsOutputToContain('Released 1 expired hold(s).')
            ->assertSuccessful();

        $this->assertSame(3, app(StockService::class)->available($product->fresh()));
    }

    public function test_the_hold_sweeper_is_on_the_schedule(): void
    {
        $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->map(fn ($event) => $event->command ?? '')
            ->implode(' ');

        $this->assertStringContainsString('stock:release-expired', $commands,
            'Expired stock holds are only freed if this command is scheduled.');
    }

    public function test_payment_commits_the_hold_and_reduces_stock(): void
    {
        $product = $this->product('Sofa', 10000, stock: 5);
        $cart = $this->cartWith($product, 2);

        $order = $this->orders->place($cart, $this->checkoutData(), (string) Str::uuid());

        $this->orders->recordPayment($order, 'order_X', 'pay_X', 'sig', (float) $order->grand_total);

        $this->assertSame('committed', StockReservation::where('order_id', $order->id)->first()->status);
        $this->assertSame(3, $product->fresh()->stock_quantity);
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    /* ---------------------------------------------------------- COD */

    public function test_a_small_cod_order_needs_no_advance_and_is_confirmed(): void
    {
        $product = $this->product('Sofa', 5000, stock: 5);
        $cart = $this->cartWith($product, 1);

        $order = $this->orders->place($cart, $this->checkoutData(['payment_method' => 'cod']), (string) Str::uuid());

        $this->assertSame(0.0, $this->orders->amountPayableNow($order));

        $this->orders->confirmCashOnDelivery($order);

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(4, $product->fresh()->stock_quantity);
    }

    public function test_a_large_cod_order_requires_the_configured_advance(): void
    {
        // Seeded: advance above 20,000 at 20%.
        $product = $this->product('Big Sofa', 60000, stock: 5);
        $cart = $this->cartWith($product, 1);

        $order = $this->orders->place($cart, $this->checkoutData(['payment_method' => 'cod']), (string) Str::uuid());

        $expected = round((float) $order->grand_total * 0.20, 2);

        $this->assertSame($expected, $this->orders->amountPayableNow($order));
    }

    /* ------------------------------------------------- order numbers */

    public function test_each_order_gets_a_unique_formatted_number(): void
    {
        $product = $this->product('Sofa', 10000, stock: 50);

        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $order = $this->orders->place($this->cartWith($product, 1), $this->checkoutData(), (string) Str::uuid());
            $numbers[] = $order->order_number;
        }

        $this->assertCount(3, array_unique($numbers));
        $this->assertMatchesRegularExpression('#^FC/\d{4}/\d{5}$#', $numbers[0]);
    }
}
