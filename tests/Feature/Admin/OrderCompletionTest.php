<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\User;
use App\Services\RazorpayService;
use App\Services\RefundFailed;
use App\Services\ReturnService;
use App\Services\ShipmentService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * The pieces added to finish Phase 5: the CSV export, the two return steps
 * that had no way of being reached, the consignment edit, the dashboard's
 * order figures, and gateway refunds.
 */
class OrderCompletionTest extends TestCase
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
            'subtotal' => 11800, 'grand_total' => 11800,
            'amount_paid' => 11800, 'amount_due' => 0,
            'payment_method' => 'razorpay', 'payment_status' => 'paid',
            'status' => 'delivered',
            'placed_at' => now(), 'delivered_at' => now(),
        ], $overrides));

        $order->items()->create([
            'name' => 'Steel Almirah', 'sku' => 'SA-1', 'quantity' => 1,
            'unit_price' => 11800, 'line_subtotal' => 11800,
            'gst_rate' => 18, 'tax_amount' => 1800, 'line_total' => 11800,
        ]);

        return $order->fresh('items');
    }

    protected function capturedPayment(Order $order): Payment
    {
        return $order->payments()->create([
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_X',
            'gateway_payment_id' => 'pay_X123',
            'amount' => 11800,
            'currency' => 'INR',
            'status' => 'captured',
            'paid_at' => now(),
        ]);
    }

    protected function returnFor(Order $order, string $status = 'approved'): OrderReturn
    {
        $return = app(ReturnService::class)->request($order, [$order->items->first()->id => 1], [
            'type' => 'return', 'reason' => 'damaged',
        ]);

        if ($status !== 'requested') {
            $return->update(['status' => $status]);
        }

        return $return->fresh('items');
    }

    /* ------------------------------------------------------- CSV export */

    public function test_the_export_returns_a_csv(): void
    {
        $order = $this->order();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.export'))
            ->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));

        $csv = $response->streamedContent();

        $this->assertStringContainsString('order_number', $csv);
        $this->assertStringContainsString($order->order_number, $csv);
        $this->assertStringContainsString('Ravi Kumar', $csv);
    }

    /**
     * The CSV has to match what the person was looking at, or it is worse
     * than useless — it is quietly wrong.
     */
    public function test_the_export_honours_the_screens_filters(): void
    {
        $delivered = $this->order(['status' => 'delivered']);
        $cancelled = $this->order(['status' => 'cancelled']);

        $csv = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.export', ['status' => 'cancelled']))
            ->streamedContent();

        $this->assertStringContainsString($cancelled->order_number, $csv);
        $this->assertStringNotContainsString($delivered->order_number, $csv);
    }

    public function test_the_export_starts_with_a_bom_so_excel_reads_it(): void
    {
        $this->order();

        $csv = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.export'))
            ->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
    }

    public function test_the_export_needs_the_view_permission(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('dashboard.view');

        $this->actingAs($outsider, 'admin')
            ->get(route('admin.orders.export'))
            ->assertForbidden();
    }

    /**
     * "export" sits on the same prefix as the order token, so route order
     * matters: declared after {order} it would be read as an order.
     */
    public function test_export_is_not_mistaken_for_an_order_token(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get('/admin/orders/export')
            ->assertOk();
    }

    /* -------------------------------------------------- an order is kept */

    /**
     * An order is a financial record. There is no delete, and the permission
     * that used to hint at one grants nothing.
     */
    public function test_there_is_no_route_for_deleting_an_order(): void
    {
        $order = $this->order();

        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(
                fn ($route) => $route->getName() === 'admin.orders.destroy'
            )
        );

        $this->actingAs($this->admin, 'admin')
            ->delete('/admin/orders/'.$order->public_token)
            ->assertStatus(405);
    }

    /* ------------------------------------------------- the missing steps */

    public function test_an_approved_return_can_be_marked_collected(): void
    {
        $return = $this->returnFor($this->order(), 'approved');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.pickup', $return), ['staff_note' => 'Collected by Imran.'])
            ->assertRedirect();

        $this->assertSame('picked_up', $return->fresh()->status);
    }

    public function test_a_return_cannot_be_collected_before_it_is_approved(): void
    {
        $return = $this->returnFor($this->order(), 'requested');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.pickup', $return))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('requested', $return->fresh()->status);
    }

    /**
     * Without this a claim settled by advice would sit open forever, or be
     * falsely recorded as refunded.
     */
    public function test_a_claim_can_be_closed_without_a_refund(): void
    {
        $return = $this->returnFor($this->order(), 'approved');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.close', $return), [
                'customer_message' => 'We posted the missing bolts.',
            ])
            ->assertRedirect();

        $return->refresh();

        $this->assertSame('closed', $return->status);
        $this->assertFalse($return->isOpen());
        $this->assertSame('0.00', (string) $return->order->fresh()->amount_due);
    }

    public function test_a_settled_return_cannot_be_closed_again(): void
    {
        $return = $this->returnFor($this->order(), 'refunded');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.close', $return))
            ->assertSessionHas('error');
    }

    /* ------------------------------------------------ consignment edits */

    /**
     * A tracking number usually turns up after the van has left.
     */
    public function test_a_consignment_can_be_edited_after_it_has_gone(): void
    {
        $order = $this->order();
        $shipment = app(ShipmentService::class)->create($order, [$order->items->first()->id => 1], [
            'status' => 'dispatched',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.shipments.update', $shipment), [
                'courier' => 'Delhivery',
                'tracking_number' => 'DL998877',
                'tracking_url' => 'https://delhivery.com/track/DL998877',
                'packages' => 3,
                'received_by' => 'Watchman',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $shipment->refresh();

        $this->assertSame('Delhivery', $shipment->courier);
        $this->assertSame('DL998877', $shipment->tracking_number);
        $this->assertSame(3, $shipment->packages);
    }

    public function test_a_tracking_link_must_be_a_url(): void
    {
        $order = $this->order();
        $shipment = app(ShipmentService::class)->create($order, [$order->items->first()->id => 1]);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.shipments.update', $shipment), [
                'tracking_url' => 'not a link',
                'packages' => 1,
            ])
            ->assertSessionHasErrors('tracking_url');
    }

    /* ------------------------------------------------------- dashboard */

    public function test_the_dashboard_shows_order_figures(): void
    {
        $this->order(['status' => 'confirmed']);
        $this->order(['status' => 'pending']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Orders to action')
            ->assertSee('Received this month');
    }

    /**
     * Tiles are gated by the permission of the module they summarise, so a
     * role never sees figures from a module it cannot open.
     */
    public function test_a_role_without_order_rights_sees_no_order_figures(): void
    {
        $this->order();

        $inventory = User::factory()->create();
        $inventory->assignRole('Inventory');

        $this->actingAs($inventory, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('Orders to action');
    }

    /* --------------------------------------------------- gateway refunds */

    public function test_a_gateway_refund_is_sent_and_its_id_recorded(): void
    {
        $order = $this->order();
        $this->capturedPayment($order);
        $return = $this->returnFor($order, 'received');

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('refund')
                ->once()
                ->with('pay_X123', 11800.0, Mockery::type('string'), Mockery::type('array'))
                ->andReturn(['id' => 'rfnd_ABC123', 'status' => 'processed']);
        });

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.refund', $return), [
                'refund_amount' => 11800,
                'refund_method' => 'original',
                'send_to_gateway' => 1,
            ])
            ->assertRedirect();

        $return->refresh();

        $this->assertSame('refunded', $return->status);
        $this->assertSame('rfnd_ABC123', $return->refund_reference);
    }

    /**
     * The whole point: a refund that did not happen must not be recorded as
     * one that did.
     */
    public function test_a_refused_gateway_refund_records_nothing(): void
    {
        $order = $this->order();
        $this->capturedPayment($order);
        $return = $this->returnFor($order, 'received');

        $this->mock(RazorpayService::class, function ($mock) {
            $mock->shouldReceive('refund')
                ->once()
                ->andThrow(new RefundFailed('This payment has already been refunded in full.'));
        });

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.refund', $return), [
                'refund_amount' => 11800,
                'refund_method' => 'original',
                'send_to_gateway' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('received', $return->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_a_cod_order_cannot_be_refunded_through_the_gateway(): void
    {
        $order = $this->order(['payment_method' => 'cod', 'payment_status' => 'paid']);
        $return = $this->returnFor($order, 'received');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.refund', $return), [
                'refund_amount' => 11800,
                'refund_method' => 'original',
                'send_to_gateway' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('received', $return->fresh()->status);
    }

    /**
     * Unticking the box still records a refund made at the counter.
     */
    public function test_a_refund_can_still_be_recorded_by_hand(): void
    {
        $order = $this->order();
        $this->capturedPayment($order);
        $return = $this->returnFor($order, 'received');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.refund', $return), [
                'refund_amount' => 11800,
                'refund_method' => 'bank',
                'refund_reference' => 'UTR99887766',
            ])
            ->assertRedirect();

        $return->refresh();

        $this->assertSame('refunded', $return->status);
        $this->assertSame('UTR99887766', $return->refund_reference);
    }

    public function test_a_zero_refund_is_refused(): void
    {
        $return = $this->returnFor($this->order(), 'received');

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.refund', $return), [
                'refund_amount' => 0,
                'refund_method' => 'original',
            ])
            ->assertSessionHasErrors('refund_amount');
    }

    /* ----------------------------------------------------- state picker */

    public function test_checkout_offers_the_states_as_a_list(): void
    {
        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $cart = \App\Models\Cart::create(['customer_id' => $customer->id, 'last_activity_at' => now()]);

        $category = \App\Models\Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'is_active' => true]);
        $product = \App\Models\Product::create([
            'category_id' => $category->id, 'name' => 'Almirah', 'slug' => 'almirah-1',
            'sku' => 'SKU1', 'price' => 11800, 'stock_quantity' => 5,
            'gst_rate' => 18, 'status' => 'active',
        ]);

        $cart->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 11800]);

        $html = $this->actingAs($customer, 'customer')
            ->get(route('checkout.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="shipping[state]"', $html);
        $this->assertStringContainsString('<option value="Uttar Pradesh"', $html);
        $this->assertStringContainsString('<option value="Tamil Nadu"', $html);
    }

    public function test_the_state_list_covers_every_gst_code(): void
    {
        $states = indian_states();

        $this->assertCount(36, $states);
        $this->assertSame('07', $states['Delhi']);
        $this->assertSame('09', $states['Uttar Pradesh']);

        // Every name has to resolve through the invoice matcher, or the
        // dropdown would be offering states the tax code cannot place.
        $invoices = app(\App\Services\InvoiceService::class);

        foreach ($states as $name => $code) {
            $this->assertSame($code, $invoices->stateCode($name), $name.' does not resolve.');
        }
    }
}
