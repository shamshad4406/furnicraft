<?php

namespace Tests\Feature\Shop;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Pincode;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The checkout page itself — what it shows, and what it says when something
 * is missing. The cart belongs to a signed-in customer throughout, because
 * the test client starts a new session for every request and a guest cart
 * would not survive from one to the next.
 */
class CheckoutPageTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $zone = DeliveryZone::create([
            'name' => 'Local', 'delivery_days' => 3,
            'base_charge' => 500, 'per_kg_charge' => 10, 'free_above' => 50000,
            'cod_available' => true, 'is_active' => true,
        ]);

        foreach ([['110001', 'New Delhi'], ['110016', 'New Delhi']] as [$code, $city]) {
            Pincode::create([
                'pincode' => $code, 'city' => $city, 'state' => 'Delhi',
                'delivery_zone_id' => $zone->id, 'is_serviceable' => true, 'cod_available' => true,
            ]);
        }

        $category = Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Steel Almirah', 'slug' => 'steel-almirah', 'sku' => Str::upper(Str::random(8)),
            'price' => 12500, 'stock_quantity' => 10, 'gst_rate' => 18, 'weight' => 10, 'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);
    }

    protected function cartForCustomer(int $quantity = 1): Cart
    {
        $cart = Cart::create(['customer_id' => $this->customer->id, 'last_activity_at' => now()]);

        $cart->items()->create([
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'unit_price' => $this->product->price,
        ]);

        return $cart;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
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
            'shipping' => $address,
            'billing_same' => 1,
            'payment_method' => 'cod',
            'terms' => 1,
        ], $overrides);
    }

    /* ------------------------------------------------------- the page */

    public function test_the_checkout_page_renders_with_a_cart(): void
    {
        $this->cartForCustomer();

        $this->actingAs($this->customer, 'customer')
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('Checkout')
            ->assertSee('Steel Almirah');
    }

    public function test_an_empty_cart_is_sent_back_to_the_cart_page(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'));
    }

    /**
     * Every delivery field comes from the saved address, the pincode
     * included — an address whose street says one place and whose pincode
     * says another would be delivered to neither.
     */
    public function test_a_saved_address_prefills_the_whole_delivery_block(): void
    {
        $this->cartForCustomer();

        Address::create([
            'customer_id' => $this->customer->id, 'type' => 'home',
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park Extension', 'city' => 'New Delhi',
            'state' => 'Delhi', 'pincode' => '110016',
            'is_default_shipping' => true, 'is_default_billing' => true,
        ]);

        $html = $this->actingAs($this->customer, 'customer')
            ->withSession(['pincode' => '110001'])   // a check done for somewhere else
            ->get(route('checkout.index'))
            ->assertOk()
            ->getContent();

        foreach (['12 Green Park Extension', 'New Delhi', 'Delhi', '110016'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }

        // The saved address wins over the stale session pincode.
        $this->assertMatchesRegularExpression(
            '/name="shipping\[pincode\]"[^>]*value="110016"/',
            $html
        );
    }

    /* -------------------------------------------------- error messages */

    /**
     * A nested rule reports itself as "the shipping.city field" unless every
     * key has a readable name. Customers should never see an array key.
     */
    public function test_validation_errors_never_show_a_raw_field_key(): void
    {
        $this->cartForCustomer();

        $response = $this->actingAs($this->customer, 'customer')
            ->post(route('checkout.store'), [
                'shipping' => ['pincode' => ''],
                'billing_same' => 1,
            ]);

        $errors = $response->baseResponse->getSession()->get('errors')->getBag('default')->all();

        $this->assertNotEmpty($errors);

        foreach ($errors as $message) {
            $this->assertStringNotContainsString('shipping.', $message, 'Raw key in: '.$message);
            $this->assertStringNotContainsString('billing.', $message, 'Raw key in: '.$message);
            $this->assertStringNotContainsString('customer_', $message, 'Raw key in: '.$message);
            $this->assertStringNotContainsString('_', $message, 'Underscored key in: '.$message);
        }
    }

    public function test_the_delivery_fields_are_named_in_their_messages(): void
    {
        $this->cartForCustomer();

        $this->actingAs($this->customer, 'customer')
            ->post(route('checkout.store'), ['billing_same' => 1])
            ->assertSessionHasErrors([
                'shipping.name' => 'The delivery name field is required.',
                'shipping.phone' => 'The delivery mobile number field is required.',
                'shipping.city' => 'The delivery city field is required.',
                'shipping.state' => 'The delivery state field is required.',
            ]);
    }

    public function test_a_separate_billing_address_is_validated_too(): void
    {
        $this->cartForCustomer();

        $this->actingAs($this->customer, 'customer')
            ->post(route('checkout.store'), $this->payload([
                'billing_same' => 0,
                'billing' => ['name' => '', 'line1' => '', 'city' => '', 'state' => '', 'pincode' => ''],
            ]))
            ->assertSessionHasErrors([
                'billing.name' => 'The billing name field is required.',
                'billing.city' => 'The billing city field is required.',
            ]);
    }

    public function test_the_terms_box_must_be_ticked(): void
    {
        $this->cartForCustomer();

        $this->actingAs($this->customer, 'customer')
            ->post(route('checkout.store'), $this->payload(['terms' => 0]))
            ->assertSessionHasErrors(['terms' => 'Please accept the terms to place your order.']);
    }

    /* --------------------------------------------------- placing it */

    /**
     * Placing the order and confirming it are two steps: the POST creates a
     * pending order and hands off to the payment step, which is where a COD
     * order with nothing to pay up front is confirmed.
     */
    public function test_a_complete_cod_checkout_places_a_confirmed_order(): void
    {
        $this->cartForCustomer();

        $placed = $this->actingAs($this->customer, 'customer')
            ->post(route('checkout.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'payment_method' => 'cod',
            'status' => 'pending',
        ]);

        // Follow the hand-off, the way a browser would.
        $this->actingAs($this->customer, 'customer')
            ->get($placed->headers->get('Location'))
            ->assertRedirect();

        $this->assertDatabaseHas('orders', [
            'customer_id' => $this->customer->id,
            'payment_method' => 'cod',
            'status' => 'confirmed',
            'payment_status' => 'pending',
        ]);
    }

    public function test_the_success_page_shows_the_order(): void
    {
        $this->cartForCustomer();

        $placed = $this->actingAs($this->customer, 'customer')
            ->post(route('checkout.store'), $this->payload());

        $this->actingAs($this->customer, 'customer')->get($placed->headers->get('Location'));

        $order = \App\Models\Order::latest('id')->firstOrFail();

        $this->actingAs($this->customer, 'customer')
            ->get(route('checkout.success', $order->public_token))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Steel Almirah');
    }

    public function test_the_address_can_be_saved_while_checking_out(): void
    {
        $this->cartForCustomer();

        $this->actingAs($this->customer, 'customer')
            ->post(route('checkout.store'), $this->payload(['save_address' => 1]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('addresses', [
            'customer_id' => $this->customer->id,
            'line1' => '12 Green Park',
            'pincode' => '110001',
        ]);
    }
}
