<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentNotPossible;
use App\Services\ShipmentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Consignments against an order.
 *
 * The rule under all of this: however an order is split across vans, the
 * quantities sent must never exceed the quantities bought.
 */
class ShipmentTest extends TestCase
{
    use RefreshDatabase;

    protected ShipmentService $shipments;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->shipments = app(ShipmentService::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    protected function order(int $quantity = 3, array $overrides = []): Order
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
            'name' => 'Steel Almirah', 'sku' => 'SA-1', 'quantity' => $quantity,
            'unit_price' => 11800, 'line_subtotal' => 11800,
            'gst_rate' => 18, 'tax_amount' => 1800, 'line_total' => 11800,
        ]);

        return $order->fresh('items');
    }

    /* ------------------------------------------------------- creating */

    public function test_a_consignment_records_what_went_out(): void
    {
        $order = $this->order(3);
        $item = $order->items->first();

        $shipment = $this->shipments->create($order, [$item->id => 2], [
            'courier' => 'Blue Dart',
            'tracking_number' => 'BD123',
            'status' => 'dispatched',
        ]);

        $this->assertSame('Blue Dart', $shipment->courier);
        $this->assertSame(2, $shipment->items->sum('quantity'));
        $this->assertNotNull($shipment->dispatched_at);
    }

    public function test_the_consignment_number_follows_the_order_number(): void
    {
        $order = $this->order(3);
        $item = $order->items->first();

        $first = $this->shipments->create($order, [$item->id => 1]);
        $second = $this->shipments->create($order->fresh('items'), [$item->id => 1]);

        $this->assertSame($order->order_number.'-1', $first->consignment_number);
        $this->assertSame($order->order_number.'-2', $second->consignment_number);
    }

    public function test_an_order_can_be_sent_in_parts(): void
    {
        $order = $this->order(3);
        $item = $order->items->first();

        $this->shipments->create($order, [$item->id => 1]);

        $pending = $this->shipments->pendingItems($order->fresh('items'));

        $this->assertSame(1, $pending[$item->id]['shipped']);
        $this->assertSame(2, $pending[$item->id]['pending']);
    }

    public function test_more_cannot_be_sent_than_was_ordered(): void
    {
        $order = $this->order(2);
        $item = $order->items->first();

        $this->expectException(ShipmentNotPossible::class);
        $this->expectExceptionMessage('only 2 left to send');

        $this->shipments->create($order, [$item->id => 5]);
    }

    public function test_the_remainder_cannot_be_oversent_across_two_consignments(): void
    {
        $order = $this->order(3);
        $item = $order->items->first();

        $this->shipments->create($order, [$item->id => 2]);

        $this->expectException(ShipmentNotPossible::class);

        $this->shipments->create($order->fresh('items'), [$item->id => 2]);
    }

    public function test_a_consignment_with_nothing_in_it_is_refused(): void
    {
        $order = $this->order(2);
        $item = $order->items->first();

        $this->expectException(ShipmentNotPossible::class);
        $this->expectExceptionMessage('at least one item');

        $this->shipments->create($order, [$item->id => 0]);
    }

    /**
     * A consignment that came back was never delivered, so those units are
     * owed to the customer again.
     */
    public function test_a_returned_consignment_frees_its_units_to_be_sent_again(): void
    {
        $order = $this->order(2);
        $item = $order->items->first();

        $shipment = $this->shipments->create($order, [$item->id => 2]);

        $this->assertFalse($this->shipments->hasUnshippedItems($order->fresh('items')));

        $this->shipments->updateStatus($shipment, 'returned', $this->admin);

        $this->assertTrue($this->shipments->hasUnshippedItems($order->fresh('items')));
    }

    /* --------------------------------------------------- order syncing */

    public function test_dispatching_moves_the_order_to_shipped(): void
    {
        $order = $this->order(1);
        $item = $order->items->first();

        $this->shipments->create($order, [$item->id => 1], ['status' => 'dispatched']);
        $this->shipments->syncOrderStatus($order->fresh(), $this->admin);

        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_delivering_everything_moves_the_order_to_delivered(): void
    {
        $order = $this->order(1);
        $item = $order->items->first();

        $shipment = $this->shipments->create($order, [$item->id => 1], ['status' => 'dispatched']);
        $this->shipments->syncOrderStatus($order->fresh(), $this->admin);

        $this->shipments->updateStatus($shipment, 'delivered', $this->admin);

        $order->refresh();

        $this->assertSame('delivered', $order->status);
        $this->assertNotNull($order->delivered_at);
    }

    /**
     * Half an order arriving is not a delivered order.
     */
    public function test_a_part_delivery_does_not_mark_the_order_delivered(): void
    {
        $order = $this->order(3);
        $item = $order->items->first();

        $shipment = $this->shipments->create($order, [$item->id => 1], ['status' => 'dispatched']);
        $this->shipments->syncOrderStatus($order->fresh(), $this->admin);

        $this->shipments->updateStatus($shipment, 'delivered', $this->admin);

        $this->assertSame('shipped', $order->fresh()->status);
    }

    /**
     * The order status is a convenience, not a licence to bypass the
     * workflow: an unpaid online order stays where it is.
     */
    public function test_an_unpaid_online_order_is_not_auto_delivered(): void
    {
        $order = $this->order(1, [
            'payment_method' => 'razorpay',
            'payment_status' => 'pending',
            'status' => 'shipped',
        ]);
        $item = $order->items->first();

        $shipment = $this->shipments->create($order, [$item->id => 1], ['status' => 'dispatched']);
        $this->shipments->updateStatus($shipment, 'delivered', $this->admin);

        $this->assertSame('shipped', $order->fresh()->status);
    }

    /* ------------------------------------------------------- the screens */

    public function test_staff_can_create_a_consignment_from_the_order_page(): void
    {
        $order = $this->order(2);
        $item = $order->items->first();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.shipments.store', $order), [
                'quantities' => [$item->id => 2],
                'packages' => 1,
                'status' => 'dispatched',
                'courier' => 'Blue Dart',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'courier' => 'Blue Dart',
            'status' => 'dispatched',
        ]);
    }

    public function test_an_oversend_from_the_screen_is_reported_not_saved(): void
    {
        $order = $this->order(1);
        $item = $order->items->first();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.shipments.store', $order), [
                'quantities' => [$item->id => 9],
                'packages' => 1,
                'status' => 'dispatched',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('shipments', 0);
    }

    public function test_an_expected_date_in_the_past_is_refused(): void
    {
        $order = $this->order(1);
        $item = $order->items->first();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.shipments.store', $order), [
                'quantities' => [$item->id => 1],
                'packages' => 1,
                'status' => 'dispatched',
                'expected_delivery_on' => now()->subWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('expected_delivery_on');
    }

    /**
     * A delivered consignment is a record of what happened.
     */
    public function test_a_delivered_consignment_cannot_be_deleted(): void
    {
        $order = $this->order(1);
        $item = $order->items->first();

        $shipment = $this->shipments->create($order, [$item->id => 1], ['status' => 'delivered']);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.shipments.destroy', $shipment))
            ->assertRedirect();

        $this->assertDatabaseHas('shipments', ['id' => $shipment->id, 'deleted_at' => null]);
    }

    public function test_deleting_an_undelivered_consignment_frees_its_items(): void
    {
        $order = $this->order(2);
        $item = $order->items->first();

        $shipment = $this->shipments->create($order, [$item->id => 2], ['status' => 'pending']);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.shipments.destroy', $shipment))
            ->assertRedirect();

        $this->assertSoftDeleted('shipments', ['id' => $shipment->id]);
        $this->assertTrue($this->shipments->hasUnshippedItems($order->fresh('items')));
    }

    public function test_the_shipments_list_renders(): void
    {
        $order = $this->order(1);
        $this->shipments->create($order, [$order->items->first()->id => 1], ['courier' => 'Blue Dart']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.shipments.index'))
            ->assertOk()
            ->assertSee($order->order_number.'-1')
            ->assertSee('Blue Dart');
    }

    public function test_shipment_screens_need_the_shipment_permission(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('dashboard.view');

        $this->actingAs($outsider, 'admin')
            ->get(route('admin.shipments.index'))
            ->assertForbidden();
    }
}
