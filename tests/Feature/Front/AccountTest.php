<?php

namespace Tests\Feature\Front;

use App\Models\Address;
use App\Models\Customer;
use App\Models\Order;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The customer's own area: every page is scoped to the signed-in customer,
 * and no route accepts an id or token that selects somebody else's data.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $this->customer = $this->makeCustomer('ravi@example.com', '9811122233', 'Ravi Kumar');
    }

    protected function makeCustomer(string $email, string $phone, string $name = 'Someone Else'): Customer
    {
        return Customer::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => 'Secret12345',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function orderFor(?Customer $customer, array $overrides = []): Order
    {
        $address = [
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park', 'city' => 'New Delhi',
            'state' => 'Delhi', 'pincode' => '110001',
        ];

        $order = Order::create(array_merge([
            'order_number' => 'FC/TEST/'.str_pad((string) (Order::count() + 1), 5, '0', STR_PAD_LEFT),
            'customer_id' => $customer?->id,
            'customer_name' => $address['name'],
            'customer_email' => $customer?->email ?? 'guest@example.com',
            'customer_phone' => $address['phone'],
            'shipping_address' => $address,
            'billing_address' => $address,
            'shipping_pincode' => '110001',
            'subtotal' => 11800,
            'grand_total' => 11800,
            'amount_due' => 11800,
            'payment_method' => 'razorpay',
            'status' => 'confirmed',
            'placed_at' => now(),
        ], $overrides));

        $order->items()->create([
            'name' => 'Steel Almirah', 'sku' => 'SA-1', 'quantity' => 1,
            'unit_price' => 11800, 'line_subtotal' => 11800,
            'gst_rate' => 18, 'tax_amount' => 1800, 'line_total' => 11800,
        ]);

        return $order->fresh('items');
    }

    /* ------------------------------------------------------- guarding */

    public function test_account_pages_need_a_signed_in_customer(): void
    {
        foreach (['account.dashboard', 'account.profile', 'account.orders', 'account.addresses'] as $name) {
            $this->get(route($name))->assertRedirect(route('customer.login'));
        }
    }

    public function test_a_disabled_customer_is_signed_out_of_the_account_area(): void
    {
        $this->customer->update(['is_active' => false]);

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.dashboard'))
            ->assertRedirect(route('customer.login'));

        $this->assertGuest('customer');
    }

    /* ------------------------------------------------------ dashboard */

    public function test_the_dashboard_counts_only_this_customers_records(): void
    {
        $other = $this->makeCustomer('other@example.com', '9800000000');

        $this->orderFor($this->customer);
        $this->orderFor($this->customer);
        $this->orderFor($other);

        Address::create([
            'customer_id' => $this->customer->id, 'type' => 'home', 'name' => 'Ravi Kumar',
            'phone' => '9811122233', 'line1' => '12 Green Park', 'city' => 'New Delhi',
            'state' => 'Delhi', 'pincode' => '110001',
        ]);

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertViewHas('orderCount', 2)
            ->assertViewHas('addressCount', 1)
            ->assertSee('Ravi');
    }

    /* -------------------------------------------------------- profile */

    public function test_a_customer_can_update_their_details(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->put(route('account.profile.update'), [
                'name' => 'Ravi K Sharma',
                'email' => 'ravi.sharma@example.com',
                'phone' => '9811122244',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('customers', [
            'id' => $this->customer->id,
            'name' => 'Ravi K Sharma',
            'email' => 'ravi.sharma@example.com',
        ]);
    }

    public function test_an_email_already_used_by_another_customer_is_rejected(): void
    {
        $this->makeCustomer('taken@example.com', '9800000001');

        $this->actingAs($this->customer, 'customer')
            ->put(route('account.profile.update'), [
                'name' => 'Ravi Kumar',
                'email' => 'taken@example.com',
                'phone' => '9811122233',
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_keeping_your_own_email_is_allowed(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->put(route('account.profile.update'), [
                'name' => 'Ravi Kumar Jr',
                'email' => 'ravi@example.com',
                'phone' => '9811122233',
            ])
            ->assertSessionHasNoErrors();
    }

    public function test_the_password_change_needs_the_current_password(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->put(route('account.password.update'), [
                'current_password' => 'WrongPassword1',
                'password' => 'NewSecret12345',
                'password_confirmation' => 'NewSecret12345',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Secret12345', $this->customer->fresh()->password));
    }

    public function test_a_customer_can_change_their_password(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->put(route('account.password.update'), [
                'current_password' => 'Secret12345',
                'password' => 'NewSecret12345',
                'password_confirmation' => 'NewSecret12345',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('NewSecret12345', $this->customer->fresh()->password));
    }

    public function test_a_weak_password_is_refused(): void
    {
        $this->actingAs($this->customer, 'customer')
            ->put(route('account.password.update'), [
                'current_password' => 'Secret12345',
                'password' => 'abc',
                'password_confirmation' => 'abc',
            ])
            ->assertSessionHasErrors('password');
    }

    /* --------------------------------------------------------- orders */

    public function test_the_orders_list_shows_only_this_customers_orders(): void
    {
        $other = $this->makeCustomer('other@example.com', '9800000000');

        $mine = $this->orderFor($this->customer);
        $theirs = $this->orderFor($other);

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.orders'))
            ->assertOk()
            ->assertSee($mine->order_number)
            ->assertDontSee($theirs->order_number);
    }

    public function test_the_orders_list_can_be_filtered_by_status(): void
    {
        $confirmed = $this->orderFor($this->customer, ['status' => 'confirmed']);
        $delivered = $this->orderFor($this->customer, ['status' => 'delivered']);

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.orders', ['status' => 'delivered']))
            ->assertOk()
            ->assertSee($delivered->order_number)
            ->assertDontSee($confirmed->order_number);
    }

    public function test_an_order_detail_page_renders_for_its_owner(): void
    {
        $order = $this->orderFor($this->customer);

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.order', $order->public_token))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Steel Almirah');
    }

    public function test_another_customers_order_token_is_a_404(): void
    {
        $other = $this->makeCustomer('other@example.com', '9800000000');
        $theirs = $this->orderFor($other);

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.order', $theirs->public_token))
            ->assertNotFound();
    }

    public function test_a_guest_order_is_not_reachable_from_an_account(): void
    {
        $guestOrder = $this->orderFor(null);

        $this->actingAs($this->customer, 'customer')
            ->get(route('account.order', $guestOrder->public_token))
            ->assertNotFound();
    }
}
