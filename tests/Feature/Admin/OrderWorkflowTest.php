<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use App\Services\InvalidStatusTransition;
use App\Services\OrderWorkflow;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The rules about how an order may move.
 *
 * These matter more than they look: a status is what the warehouse acts on
 * and what the customer is told, so an order that can slip backwards — or
 * jump to "delivered" without being paid for — turns into a lost sofa or an
 * unpaid one.
 */
class OrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected OrderWorkflow $workflow;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->workflow = app(OrderWorkflow::class);
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

    /* ----------------------------------------------------- transitions */

    public function test_an_order_moves_forward_through_its_statuses(): void
    {
        $order = $this->order(['status' => 'pending']);

        foreach (['confirmed', 'processing', 'shipped', 'delivered'] as $status) {
            $this->workflow->moveTo($order, $status, actor: $this->admin);
            $order->refresh();

            $this->assertSame($status, $order->status);
        }

        $this->assertNotNull($order->delivered_at);
    }

    public function test_an_order_cannot_skip_from_pending_to_delivered(): void
    {
        $order = $this->order(['status' => 'pending']);

        $this->expectException(InvalidStatusTransition::class);

        $this->workflow->moveTo($order, 'delivered', actor: $this->admin);
    }

    public function test_a_delivered_order_cannot_go_back_to_processing(): void
    {
        $order = $this->order(['status' => 'delivered']);

        $this->assertFalse($this->workflow->canMoveTo($order, 'processing'));
        $this->assertSame(['returned'], $this->workflow->allowedNext($order));
    }

    public function test_a_cancelled_order_is_the_end_of_the_line(): void
    {
        $order = $this->order(['status' => 'cancelled']);

        $this->assertSame([], $this->workflow->allowedNext($order));

        $this->expectException(InvalidStatusTransition::class);
        $this->workflow->moveTo($order, 'confirmed', actor: $this->admin);
    }

    public function test_a_shipment_logged_by_mistake_can_be_put_back(): void
    {
        $order = $this->order(['status' => 'shipped']);

        $this->workflow->moveTo($order, 'processing', 'Logged against the wrong order.', $this->admin);

        $this->assertSame('processing', $order->fresh()->status);
    }

    public function test_moving_an_order_to_where_it_already_is_is_refused(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        $this->expectException(InvalidStatusTransition::class);
        $this->expectExceptionMessage('already');

        $this->workflow->moveTo($order, 'confirmed', actor: $this->admin);
    }

    /* ------------------------------------------------------ unpaid guard */

    /**
     * An online order that was never paid for must not be marked delivered —
     * that is a sofa given away.
     */
    public function test_an_unpaid_online_order_cannot_be_marked_delivered(): void
    {
        $order = $this->order([
            'status' => 'shipped',
            'payment_method' => 'razorpay',
            'payment_status' => 'pending',
        ]);

        $this->assertNotContains('delivered', $this->workflow->allowedNext($order));

        $this->expectException(InvalidStatusTransition::class);
        $this->expectExceptionMessage('not been paid for');

        $this->workflow->moveTo($order, 'delivered', actor: $this->admin);
    }

    public function test_a_paid_online_order_can_be_marked_delivered(): void
    {
        $order = $this->order([
            'status' => 'shipped',
            'payment_method' => 'razorpay',
            'payment_status' => 'paid',
            'amount_paid' => 11800,
            'amount_due' => 0,
        ]);

        $this->workflow->moveTo($order, 'delivered', actor: $this->admin);

        $this->assertSame('delivered', $order->fresh()->status);
    }

    /**
     * Paying later is the whole point of cash on delivery, so the unpaid
     * guard must not apply to it.
     */
    public function test_an_unpaid_cod_order_can_still_be_delivered(): void
    {
        $order = $this->order(['status' => 'shipped', 'payment_method' => 'cod']);

        $this->workflow->moveTo($order, 'delivered', actor: $this->admin);

        $this->assertSame('delivered', $order->fresh()->status);
    }

    /* ---------------------------------------------------------- history */

    public function test_every_move_is_recorded_with_who_and_why(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        $this->workflow->moveTo($order, 'processing', 'Started building it.', $this->admin);

        $entry = $order->statusHistories()->latest('id')->first();

        $this->assertSame('confirmed', $entry->from_status);
        $this->assertSame('processing', $entry->to_status);
        $this->assertSame('Started building it.', $entry->note);
        $this->assertSame($this->admin->id, $entry->changed_by);
        $this->assertSame($this->admin->name, $entry->actor);
    }

    /**
     * The staff member who handled an order may leave the company; the record
     * of what they did must not leave with them.
     */
    public function test_the_actor_name_survives_the_staff_account_being_deleted(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        $this->workflow->moveTo($order, 'processing', null, $this->admin);

        $name = $this->admin->name;
        $this->admin->forceDelete();

        $entry = $order->statusHistories()->latest('id')->first()->fresh();

        $this->assertNull($entry->changed_by);
        $this->assertSame($name, $entry->actor);
    }

    public function test_a_system_change_is_recorded_without_an_actor(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        $this->workflow->moveTo($order, 'processing', 'Automatic.', actor: null);

        $this->assertSame('System', $order->statusHistories()->latest('id')->first()->actor);
    }

    public function test_quiet_statuses_do_not_claim_the_customer_was_told(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        // "Processing" means work started, which is not news worth an email.
        $entry = $this->workflow->moveTo($order, 'processing', null, $this->admin, notifyCustomer: true);
        $this->assertFalse($entry->customer_notified);

        $entry = $this->workflow->moveTo($order->fresh(), 'shipped', null, $this->admin, notifyCustomer: true);
        $this->assertTrue($entry->customer_notified);
    }

    public function test_unticking_notify_is_respected(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        $entry = $this->workflow->moveTo($order, 'shipped', null, $this->admin, notifyCustomer: false);

        $this->assertFalse($entry->customer_notified);
    }

    /* ---------------------------------------------------- side effects */

    public function test_cancelling_records_the_reason_and_who_did_it(): void
    {
        $order = $this->order(['status' => 'confirmed']);

        $this->workflow->moveTo($order, 'cancelled', 'Customer changed their mind.', $this->admin);

        $order->refresh();

        $this->assertSame('cancelled', $order->status);
        $this->assertSame('Customer changed their mind.', $order->cancellation_reason);
        $this->assertSame($this->admin->id, $order->cancelled_by);
        $this->assertNotNull($order->cancelled_at);
    }

    /**
     * Money never moves because a dropdown changed. A refund is a deliberate
     * act on the returns screen.
     */
    public function test_cancelling_does_not_touch_the_money(): void
    {
        $order = $this->order([
            'status' => 'confirmed',
            'payment_status' => 'paid',
            'amount_paid' => 11800,
            'amount_due' => 0,
        ]);

        $this->workflow->moveTo($order, 'cancelled', 'Out of stock.', $this->admin);

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('11800.00', (string) $order->amount_paid);
    }
}
