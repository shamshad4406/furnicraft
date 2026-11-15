<?php

namespace Tests\Feature\Mail;

use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Pincode;
use App\Models\Product;
use App\Services\CartService;
use App\Services\OrderService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End to end, with Zoho's API faked at the HTTP boundary rather than the mail
 * layer: placing an order must actually put a request on the wire, addressed
 * to the customer, carrying the link they need to check on the order later.
 *
 * Mail::fake() would prove the Mailable was dispatched; this proves it would
 * have left the building.
 */
class OrderMailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected const ENDPOINT = 'https://api.zeptomail.in/v1.1/email';

    protected OrderService $orders;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        config([
            'mail.default' => 'zeptomail',
            'mail.mailers.zeptomail' => [
                'transport' => 'zeptomail',
                'key' => 'Zoho-enczapikey test-key',
                'endpoint' => self::ENDPOINT,
            ],
            'mail.from.address' => 'support@innobles.com',
            'mail.from.name' => 'Innobles FurniCraft',
        ]);

        $this->orders = app(OrderService::class);
        $this->category = Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'is_active' => true]);

        $zone = DeliveryZone::create([
            'name' => 'Local', 'delivery_days' => 3,
            'base_charge' => 0, 'per_kg_charge' => 0, 'free_above' => 1,
            'cod_available' => true, 'is_active' => true,
        ]);

        Pincode::create([
            'pincode' => '110001', 'city' => 'New Delhi', 'state' => 'Delhi',
            'delivery_zone_id' => $zone->id, 'is_serviceable' => true, 'cod_available' => true,
        ]);
    }

    protected function placeOrder(?Customer $customer = null): Order
    {
        $product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Steel Almirah', 'slug' => 'steel-almirah-'.Str::random(5),
            'sku' => Str::upper(Str::random(8)),
            'price' => 12500, 'stock_quantity' => 10, 'gst_rate' => 18,
            'weight' => 10, 'status' => 'active',
        ]);

        $request = Request::create('/');
        $request->setLaravelSession(app('session.store'));

        if ($customer) {
            $this->actingAs($customer, 'customer');
        }

        $cart = app(CartService::class);
        $cart->add($request, $product, null, 1);

        $address = [
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park', 'line2' => null, 'landmark' => null,
            'city' => 'New Delhi', 'state' => 'Delhi', 'pincode' => '110001',
        ];

        return $this->orders->place($cart->current($request), [
            'customer_name' => 'Ravi Kumar',
            'customer_email' => $customer?->email ?? 'guest@example.com',
            'customer_phone' => '9811122233',
            'gst_number' => null,
            'shipping_address' => $address,
            'billing_address' => $address,
            'payment_method' => 'cod',
            'notes' => null,
        ], (string) Str::uuid());
    }

    /**
     * @return array<string, mixed>
     */
    protected function sentPayload(): array
    {
        $recorded = Http::recorded();

        $this->assertNotEmpty($recorded, 'No email was sent to ZeptoMail.');

        return $recorded[0][0]->data();
    }

    /* ----------------------------------------------------------- guests */

    public function test_a_placed_order_sends_the_confirmation_through_zoho(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $order = $this->placeOrder();
        $this->orders->confirmCashOnDelivery($order);

        Http::assertSentCount(1);

        $payload = $this->sentPayload();

        $this->assertSame('support@innobles.com', $payload['from']['address']);
        $this->assertSame('Innobles FurniCraft', $payload['from']['name']);
        $this->assertSame('guest@example.com', $payload['to'][0]['email_address']['address']);
        $this->assertStringContainsString($order->order_number, $payload['subject']);
    }

    /**
     * The whole point of the email for a guest: it is the only way back to
     * the order once their session is gone.
     */
    public function test_a_guests_email_carries_the_tracking_link(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $order = $this->placeOrder();
        $this->orders->confirmCashOnDelivery($order);

        $body = $this->sentPayload()['htmlbody'];

        $this->assertStringContainsString(route('track.status', $order->public_token), $body);
        $this->assertStringContainsString($order->order_number, $body);
        $this->assertStringContainsString('Steel Almirah', $body);
    }

    public function test_a_signed_in_customers_email_links_to_their_account(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $order = $this->placeOrder($customer);
        $this->orders->confirmCashOnDelivery($order);

        $payload = $this->sentPayload();

        $this->assertSame('ravi@example.com', $payload['to'][0]['email_address']['address']);
        $this->assertStringContainsString(route('account.order', $order->public_token), $payload['htmlbody']);
    }

    /* --------------------------------------------------------- failures */

    /**
     * A rejected email must not look like a sent one, or the order would be
     * marked as confirmed-and-notified while the customer heard nothing.
     */
    public function test_a_rejection_leaves_the_confirmation_to_be_retried(): void
    {
        Http::fake([self::ENDPOINT => Http::response([
            'error' => ['details' => [['message' => 'Sender address is not verified']]],
        ], 400)]);

        $order = $this->placeOrder();
        $this->orders->confirmCashOnDelivery($order);

        // The order still went through — a mail failure is not a payment failure.
        $this->assertSame('confirmed', $order->fresh()->status);

        // But it is not recorded as notified, so it can be sent again.
        $this->assertNull($order->fresh()->confirmation_sent_at);
    }

    public function test_the_order_survives_zoho_being_unreachable(): void
    {
        Http::fake([self::ENDPOINT => Http::response('', 500)]);

        $order = $this->placeOrder();
        $this->orders->confirmCashOnDelivery($order);

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'confirmed']);
    }

    public function test_a_duplicate_confirmation_does_not_send_a_second_email(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['request_id' => 'req-1'], 201)]);

        $order = $this->placeOrder();

        $this->orders->confirmCashOnDelivery($order);
        $this->orders->sendConfirmation($order->fresh());
        $this->orders->sendConfirmation($order->fresh());

        Http::assertSentCount(1);
    }
}
