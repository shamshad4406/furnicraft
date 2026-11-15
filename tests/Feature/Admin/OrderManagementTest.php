<?php

namespace Tests\Feature\Admin;

use App\Mail\OrderStatusChanged;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The admin order screens, and the two things a customer may do to their own
 * order from the storefront.
 */
class OrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function order(array $overrides = []): Order
    {
        $address = [
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park', 'city' => 'New Delhi',
            'state' => 'Delhi', 'pincode' => '110001',
        ];

        $order = Order::create(array_merge([
            'order_number' => 'FC/2611/'.str_pad((string) (Order::count() + 1), 5, '0', STR_PAD_LEFT),
            'customer_name' => 'Ravi Kumar',
            'customer_email' => 'ravi@example.com',
            'customer_phone' => '9811122233',
            'shipping_address' => $address,
            'billing_address' => $address,
            'shipping_pincode' => '110001',
            'subtotal' => 11800, 'grand_total' => 11800, 'amount_due' => 11800,
            'payment_method' => 'cod',
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

    /* ------------------------------------------------------ the screens */

    public function test_the_order_list_renders_with_its_counts(): void
    {
        $this->order(['status' => 'pending']);
        $this->order(['status' => 'confirmed']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.index'))
            ->assertOk()
            ->assertViewHas('summary', fn (array $summary) => $summary['pending'] === 1 && $summary['confirmed'] === 1);
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $pending = $this->order(['status' => 'pending']);
        $shipped = $this->order(['status' => 'shipped']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.index', ['status' => 'shipped']))
            ->assertOk()
            ->assertSee($shipped->order_number)
            ->assertDontSee($pending->order_number);
    }

    public function test_the_list_can_be_searched_by_customer_details(): void
    {
        $mine = $this->order(['customer_phone' => '9900011122']);
        $other = $this->order(['customer_name' => 'Someone Else']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.index', ['q' => '9900011122']))
            ->assertOk()
            ->assertSee($mine->order_number)
            ->assertDontSee($other->order_number);
    }

    public function test_the_unpaid_filter_leaves_out_cancelled_orders(): void
    {
        $unpaid = $this->order(['payment_status' => 'pending']);
        $cancelled = $this->order(['payment_status' => 'pending', 'status' => 'cancelled']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.index', ['flag' => 'unpaid']))
            ->assertOk()
            ->assertSee($unpaid->order_number)
            ->assertDontSee($cancelled->order_number);
    }

    public function test_the_order_detail_page_renders(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Steel Almirah')
            ->assertSee('Ravi Kumar');
    }

    /* ------------------------------------------------- moving an order */

    public function test_staff_can_move_an_order_and_the_customer_is_emailed(): void
    {
        Mail::fake();

        $order = $this->order(['status' => 'confirmed']);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.orders.status', $order), [
                'status' => 'shipped',
                'note' => 'Left on the 9am van.',
                'notify' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('shipped', $order->fresh()->status);
        Mail::assertSent(OrderStatusChanged::class, 1);
    }

    public function test_unticking_notify_sends_nothing(): void
    {
        Mail::fake();

        $order = $this->order(['status' => 'confirmed']);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.orders.status', $order), ['status' => 'shipped']);

        Mail::assertNothingSent();
    }

    /**
     * The workflow's refusal has to reach the person who clicked, not a 500.
     */
    public function test_an_impossible_move_is_reported_not_crashed(): void
    {
        $order = $this->order(['status' => 'pending']);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.orders.status', $order), ['status' => 'delivered'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('pending', $order->fresh()->status);
    }

    /**
     * A status change that really happened must not be undone by a mail
     * failure.
     */
    public function test_a_failed_email_does_not_undo_the_status_change(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.orders.status', $order), ['status' => 'shipped', 'notify' => 1])
            ->assertRedirect();

        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.orders.status', $order), ['status' => 'teleported'])
            ->assertSessionHasErrors('status');
    }

    public function test_moving_an_order_needs_the_edit_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'orders.view']);

        $order = $this->order(['status' => 'confirmed']);

        $this->actingAs($viewer, 'admin')
            ->patch(route('admin.orders.status', $order), ['status' => 'shipped'])
            ->assertForbidden();

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_a_role_without_order_rights_cannot_see_orders(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('dashboard.view');

        $this->actingAs($outsider, 'admin')
            ->get(route('admin.orders.index'))
            ->assertForbidden();
    }

    /* --------------------------------------------- the customer's side */

    public function test_a_customer_can_cancel_their_own_order(): void
    {
        Mail::fake();

        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $order = $this->order(['customer_id' => $customer->id, 'status' => 'confirmed']);

        $this->actingAs($customer, 'customer')
            ->patch(route('account.order.cancel', $order->public_token), [
                'reason' => 'Found it cheaper elsewhere.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $order->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertStringContainsString('Found it cheaper', $order->cancellation_reason);
        // Nobody on staff did it.
        $this->assertNull($order->cancelled_by);

        Mail::assertSent(OrderStatusChanged::class, 1);
    }

    public function test_cancelling_needs_a_reason(): void
    {
        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $order = $this->order(['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')
            ->patch(route('account.order.cancel', $order->public_token), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    /**
     * Once it is being built or on a van, cancelling is a phone call.
     */
    public function test_a_customer_cannot_cancel_an_order_already_being_prepared(): void
    {
        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $order = $this->order(['customer_id' => $customer->id, 'status' => 'processing']);

        $this->actingAs($customer, 'customer')
            ->patch(route('account.order.cancel', $order->public_token), ['reason' => 'Changed my mind.'])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('processing', $order->fresh()->status);
    }

    /**
     * A tracking link is for looking, not for cancelling somebody's sofa.
     */
    public function test_a_guest_holding_the_token_cannot_cancel_an_order(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        $this->patch(route('account.order.cancel', $order->public_token), ['reason' => 'Nope.'])
            ->assertRedirect(route('customer.login'));

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    public function test_a_customer_cannot_cancel_another_customers_order(): void
    {
        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $other = Customer::create([
            'name' => 'Someone Else', 'email' => 'other@example.com', 'phone' => '9800000000',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $order = $this->order(['customer_id' => $other->id]);

        $this->actingAs($customer, 'customer')
            ->patch(route('account.order.cancel', $order->public_token), ['reason' => 'Nope.'])
            ->assertNotFound();

        $this->assertSame('confirmed', $order->fresh()->status);
    }

    /* ------------------------------------------------------- the history */

    public function test_the_history_starts_at_the_customers_click(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        app(\App\Services\OrderWorkflow::class)->recordPlacement($order);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('Order placed by the customer.');
    }
}
