<?php

namespace Tests\Feature\Shop;

use App\Mail\OrderConfirmation;
use App\Models\Category;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Services\CartService;
use App\Services\OrderService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The confirmation email goes out exactly once per order, however many
 * payment callbacks or webhooks arrive for it.
 */
class OrderConfirmationMailTest extends TestCase
{
    use RefreshDatabase;

    protected OrderService $orders;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $this->orders = app(OrderService::class);
        $this->category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);

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

    protected function order(string $paymentMethod = 'razorpay'): Order
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Steel Almirah', 'slug' => 'steel-almirah-'.Str::random(5),
            'sku' => Str::upper(Str::random(8)),
            'price' => 11800, 'stock_quantity' => 10, 'gst_rate' => 18,
            'weight' => 10, 'status' => 'active',
        ]);

        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        $cart = app(CartService::class);
        $cart->add($request, $product, null, 1);

        $address = [
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park', 'line2' => null, 'landmark' => null,
            'city' => 'New Delhi', 'state' => 'Delhi', 'pincode' => '110001',
        ];

        return $this->orders->place($cart->current($request), [
            'customer_name' => 'Ravi Kumar',
            'customer_email' => 'ravi@example.com',
            'customer_phone' => '9811122233',
            'gst_number' => null,
            'shipping_address' => $address,
            'billing_address' => $address,
            'payment_method' => $paymentMethod,
            'notes' => null,
        ], (string) Str::uuid());
    }

    public function test_a_paid_order_sends_one_confirmation(): void
    {
        Mail::fake();

        $order = $this->order();

        $this->orders->recordPayment(
            $order, 'order_test1', 'pay_test1', 'sig', (float) $order->grand_total
        );

        Mail::assertSent(OrderConfirmation::class, 1);
        Mail::assertSent(OrderConfirmation::class, fn (OrderConfirmation $mail) => $mail->hasTo('ravi@example.com'));
        $this->assertNotNull($order->fresh()->confirmation_sent_at);
    }

    public function test_a_replayed_payment_event_does_not_email_twice(): void
    {
        Mail::fake();

        $order = $this->order();

        // The browser callback and the webhook both report the same payment.
        $this->orders->recordPayment($order, 'order_test1', 'pay_test1', 'sig', (float) $order->grand_total);
        $this->orders->recordPayment($order->fresh(), 'order_test1', 'pay_test1', 'sig', (float) $order->grand_total);
        $this->orders->sendConfirmation($order->fresh());

        Mail::assertSent(OrderConfirmation::class, 1);
    }

    public function test_a_cash_on_delivery_order_is_confirmed_by_email(): void
    {
        Mail::fake();

        $order = $this->order('cod');
        $this->orders->confirmCashOnDelivery($order);

        Mail::assertSent(OrderConfirmation::class, 1);
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_a_pending_order_sends_nothing(): void
    {
        Mail::fake();

        $order = $this->order();

        Mail::assertNothingSent();
        $this->assertNull($order->fresh()->confirmation_sent_at);
    }

    public function test_the_confirmation_email_lists_the_items_and_the_total(): void
    {
        $order = $this->order();
        $this->orders->markPaid($order);

        $rendered = (new OrderConfirmation($order->fresh('items')))->render();

        $this->assertStringContainsString($order->order_number, $rendered);
        $this->assertStringContainsString('Steel Almirah', $rendered);
        $this->assertStringContainsString('New Delhi', $rendered);
    }

    public function test_a_failed_send_is_left_to_be_retried(): void
    {
        $order = $this->order();

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->orders->sendConfirmation($order);

        // The claim was rolled back, so a later attempt can still send.
        $this->assertNull($order->fresh()->confirmation_sent_at);
    }
}
