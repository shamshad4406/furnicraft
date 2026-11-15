<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentWebhook;
use App\Models\Product;
use App\Models\StockReservation;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\RazorpayService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        config()->set('services.razorpay.secret', 'test_secret_value');
        config()->set('services.razorpay.key', 'rzp_test_key');
        config()->set('services.razorpay.webhook_secret', 'test_webhook_secret');

        $category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Sofa', 'slug' => 'sofa-1',
            'sku' => 'SOF-1', 'price' => 10000, 'stock_quantity' => 10,
            'gst_rate' => 18, 'status' => 'active',
        ]);

        $this->orders = app(OrderService::class);
    }

    /**
     * The order belongs to a signed-in customer: the test client starts a new
     * session on each request, so session-based ownership cannot be asserted
     * over HTTP. Guest ownership is covered by the service-level tests.
     */
    protected function makeOrder(string $method = 'razorpay'): Order
    {
        $customer = \App\Models\Customer::firstOrCreate(
            ['email' => 'buyer@example.com'],
            ['name' => 'Ravi', 'password' => 'Welcome123', 'is_active' => true],
        );

        $this->actingAs($customer, 'customer');

        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        $cart = app(CartService::class);
        $cart->add($request, $this->product, null, 1);

        $address = [
            'name' => 'Ravi', 'phone' => '9811122233', 'line1' => '12 Green Park',
            'line2' => null, 'landmark' => null,
            'city' => 'New Delhi', 'state' => 'Delhi', 'pincode' => '110001',
        ];

        return $this->orders->place($cart->current($request), [
            'customer_name' => 'Ravi', 'customer_email' => 'ravi@example.com',
            'customer_phone' => '9811122233', 'gst_number' => null,
            'shipping_address' => $address, 'billing_address' => $address,
            'payment_method' => $method, 'notes' => null,
        ], (string) Str::uuid());
    }

    protected function signature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, 'test_secret_value');
    }

    /**
     * Ownership already comes from the signed-in customer; kept so the intent
     * reads clearly at each call site.
     */
    protected function ownOrder(Order $order): void
    {
        $this->assertNotNull($order->customer_id, 'The order should belong to the signed-in customer.');
    }

    /* ------------------------------------------- signature checking */

    public function test_a_correct_signature_verifies(): void
    {
        $razorpay = app(RazorpayService::class);

        $this->assertTrue($razorpay->verifyPaymentSignature(
            'order_abc', 'pay_xyz', $this->signature('order_abc', 'pay_xyz')
        ));
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        $razorpay = app(RazorpayService::class);

        $this->assertFalse($razorpay->verifyPaymentSignature('order_abc', 'pay_xyz', 'not-the-signature'));
        // Right shape, wrong payment id.
        $this->assertFalse($razorpay->verifyPaymentSignature(
            'order_abc', 'pay_OTHER', $this->signature('order_abc', 'pay_xyz')
        ));
    }

    /* ----------------------- exit: paid only after verification */

    public function test_a_forged_callback_does_not_mark_the_order_paid(): void
    {
        $order = $this->makeOrder();
        $this->ownOrder($order);

        $this->post(route('payment.callback', $order), [
            'razorpay_order_id' => 'order_fake',
            'razorpay_payment_id' => 'pay_fake',
            'razorpay_signature' => 'totally-made-up',
        ])->assertRedirect(route('checkout.failed', $order));

        $order->refresh();

        $this->assertNotSame('paid', $order->payment_status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame(0.0, (float) $order->amount_paid);
        // The hold is freed so the stock goes back on sale.
        $this->assertSame('released', StockReservation::where('order_id', $order->id)->first()->status);
    }

    public function test_a_valid_signature_but_unpaid_gateway_status_does_not_mark_it_paid(): void
    {
        $order = $this->makeOrder();
        $this->ownOrder($order);

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifyPaymentSignature')->andReturn(true);
            $mock->shouldReceive('fetchPayment')->andReturn([
                'id' => 'pay_1', 'status' => 'failed', 'amount' => 1000000,
                'error_description' => 'Card declined',
            ]);
        });

        $this->post(route('payment.callback', $order), [
            'razorpay_order_id' => 'order_1',
            'razorpay_payment_id' => 'pay_1',
            'razorpay_signature' => 'whatever',
        ])->assertRedirect(route('checkout.failed', $order));

        $this->assertSame('failed', $order->fresh()->payment_status);
    }

    public function test_a_payment_for_the_wrong_amount_is_rejected(): void
    {
        $order = $this->makeOrder();
        $this->ownOrder($order);

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('verifyPaymentSignature')->andReturn(true);
            // One rupee, for a ten-thousand rupee order.
            $mock->shouldReceive('fetchPayment')->andReturn([
                'id' => 'pay_1', 'status' => 'captured', 'amount' => 100,
            ]);
        });

        $this->post(route('payment.callback', $order), [
            'razorpay_order_id' => 'order_1',
            'razorpay_payment_id' => 'pay_1',
            'razorpay_signature' => 'whatever',
        ])->assertRedirect(route('checkout.failed', $order));

        $order->refresh();
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame(0.0, (float) $order->amount_paid);
    }

    public function test_a_verified_captured_payment_marks_the_order_paid(): void
    {
        $order = $this->makeOrder();
        $this->ownOrder($order);
        $amountPaise = (int) round((float) $order->grand_total * 100);

        $this->mock(RazorpayService::class, function ($mock) use ($amountPaise) {
            $mock->shouldReceive('verifyPaymentSignature')->andReturn(true);
            $mock->shouldReceive('fetchPayment')->andReturn([
                'id' => 'pay_ok', 'status' => 'captured', 'amount' => $amountPaise, 'method' => 'upi',
            ]);
        });

        $this->post(route('payment.callback', $order), [
            'razorpay_order_id' => 'order_ok',
            'razorpay_payment_id' => 'pay_ok',
            'razorpay_signature' => 'valid',
        ])->assertRedirect(route('checkout.success', $order));

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertSame(0.0, (float) $order->amount_due);
        $this->assertSame('committed', StockReservation::where('order_id', $order->id)->first()->status);
        $this->assertSame(9, $this->product->fresh()->stock_quantity);
    }

    public function test_replaying_the_same_payment_does_not_double_count_it(): void
    {
        $order = $this->makeOrder();
        $total = (float) $order->grand_total;

        $this->orders->recordPayment($order, 'order_1', 'pay_same', 'sig', $total);
        $this->orders->recordPayment($order, 'order_1', 'pay_same', 'sig', $total);

        $this->assertSame(1, Payment::where('gateway_payment_id', 'pay_same')->count());
        $this->assertSame($total, (float) $order->fresh()->amount_paid);
    }

    /* ----------------------------------------------------- webhooks */

    protected function webhookPayload(string $orderId, string $paymentId, int $amountPaise, string $event = 'payment.captured'): array
    {
        return [
            'event' => $event,
            'payload' => ['payment' => ['entity' => [
                'id' => $paymentId,
                'order_id' => $orderId,
                'amount' => $amountPaise,
                'status' => 'captured',
                'method' => 'card',
            ]]],
        ];
    }

    public function test_a_webhook_without_a_valid_signature_is_rejected(): void
    {
        $payload = $this->webhookPayload('order_1', 'pay_1', 1000000);

        $this->postJson(route('webhooks.razorpay'), $payload, ['X-Razorpay-Signature' => 'wrong'])
            ->assertStatus(400);

        $this->assertSame(0, PaymentWebhook::count());
    }

    public function test_a_signed_webhook_settles_the_order(): void
    {
        $order = $this->makeOrder();
        $amountPaise = (int) round((float) $order->grand_total * 100);

        // The gateway order must already be known to us.
        Payment::create([
            'order_id' => $order->id, 'gateway' => 'razorpay',
            'gateway_order_id' => 'order_wh', 'amount' => $order->grand_total, 'status' => 'created',
        ]);

        $payload = $this->webhookPayload('order_wh', 'pay_wh', $amountPaise);
        $raw = json_encode($payload);

        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $raw, 'test_webhook_secret'),
            'HTTP_X-Razorpay-Event-Id' => 'evt_1',
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('confirmed', $order->status);
        $this->assertTrue(PaymentWebhook::where('gateway_event_id', 'evt_1')->value('processed'));
    }

    public function test_a_repeated_webhook_is_only_processed_once(): void
    {
        $order = $this->makeOrder();
        $amountPaise = (int) round((float) $order->grand_total * 100);

        Payment::create([
            'order_id' => $order->id, 'gateway' => 'razorpay',
            'gateway_order_id' => 'order_wh', 'amount' => $order->grand_total, 'status' => 'created',
        ]);

        $raw = json_encode($this->webhookPayload('order_wh', 'pay_wh', $amountPaise));
        $headers = [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $raw, 'test_webhook_secret'),
            'HTTP_X-Razorpay-Event-Id' => 'evt_dup',
            'CONTENT_TYPE' => 'application/json',
        ];

        $this->call('POST', route('webhooks.razorpay'), [], [], [], $headers, $raw)->assertOk();
        $this->call('POST', route('webhooks.razorpay'), [], [], [], $headers, $raw)->assertOk();

        $this->assertSame(1, PaymentWebhook::where('gateway_event_id', 'evt_dup')->count());
        $this->assertSame(1, Payment::where('gateway_payment_id', 'pay_wh')->count());
        $this->assertSame((float) $order->grand_total, (float) $order->fresh()->amount_paid);
    }

    public function test_a_webhook_for_the_wrong_amount_does_not_mark_the_order_paid(): void
    {
        $order = $this->makeOrder();

        Payment::create([
            'order_id' => $order->id, 'gateway' => 'razorpay',
            'gateway_order_id' => 'order_wh', 'amount' => $order->grand_total, 'status' => 'created',
        ]);

        $raw = json_encode($this->webhookPayload('order_wh', 'pay_short', 100));

        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $raw, 'test_webhook_secret'),
            'HTTP_X-Razorpay-Event-Id' => 'evt_short',
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertOk();

        $this->assertNotSame('paid', $order->fresh()->payment_status);
        $this->assertSame('amount mismatch', PaymentWebhook::where('gateway_event_id', 'evt_short')->value('result'));
    }

    public function test_a_webhook_is_refused_when_no_webhook_secret_is_configured(): void
    {
        config()->set('services.razorpay.webhook_secret', null);

        $raw = json_encode($this->webhookPayload('order_1', 'pay_1', 1000));

        $this->call('POST', route('webhooks.razorpay'), [], [], [], [
            'HTTP_X-Razorpay-Signature' => hash_hmac('sha256', $raw, 'anything'),
            'CONTENT_TYPE' => 'application/json',
        ], $raw)->assertStatus(400);
    }

    /* ------------------------------------------------- authorisation */

    public function test_another_visitor_cannot_open_someone_elses_order(): void
    {
        $order = $this->makeOrder();

        // A different, unrelated visitor.
        $intruder = \App\Models\Customer::create([
            'name' => 'Nosy', 'email' => 'nosy@example.com',
            'password' => 'Welcome123', 'is_active' => true,
        ]);

        $this->actingAs($intruder, 'customer');

        $this->get(route('checkout.success', $order))->assertNotFound();
        $this->get(route('checkout.pay', $order))->assertNotFound();
        $this->post(route('payment.create', $order))->assertNotFound();
    }

    public function test_a_signed_out_visitor_cannot_open_an_order(): void
    {
        $order = $this->makeOrder();

        auth('customer')->logout();
        $this->app['auth']->forgetGuards();

        $this->get(route('checkout.success', $order))->assertNotFound();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
