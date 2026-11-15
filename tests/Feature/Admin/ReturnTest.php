<?php

namespace Tests\Feature\Admin;

use App\Mail\ReturnUpdated;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\User;
use App\Services\ReturnNotPossible;
use App\Services\ReturnService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Returns, replacements and warranty claims.
 *
 * The things worth guarding: a customer cannot return more than they bought,
 * approving does not move money, and stock only comes back when somebody has
 * looked at what arrived and said it is sellable.
 */
class ReturnTest extends TestCase
{
    use RefreshDatabase;

    protected ReturnService $returns;

    protected User $admin;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->returns = app(ReturnService::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $category = Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Steel Almirah', 'slug' => 'steel-almirah', 'sku' => Str::upper(Str::random(8)),
            'price' => 11800, 'stock_quantity' => 10, 'gst_rate' => 18, 'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function order(int $quantity = 2, array $overrides = []): Order
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
            'subtotal' => 23600, 'grand_total' => 23600,
            'amount_paid' => 23600, 'amount_due' => 0,
            'payment_method' => 'razorpay', 'payment_status' => 'paid',
            'status' => 'delivered',
            'placed_at' => now()->subDays(3),
            'delivered_at' => now()->subDay(),
        ], $overrides));

        $order->items()->create([
            'product_id' => $this->product->id,
            'name' => 'Steel Almirah', 'sku' => 'SA-1', 'quantity' => $quantity,
            'unit_price' => 11800, 'line_subtotal' => 11800 * $quantity,
            'gst_rate' => 18, 'tax_amount' => 1800 * $quantity,
            'line_total' => 11800 * $quantity,
        ]);

        return $order->fresh('items');
    }

    /* ------------------------------------------------------- requesting */

    public function test_a_return_records_what_is_coming_back(): void
    {
        $order = $this->order(2);
        $item = $order->items->first();

        $return = $this->returns->request($order, [$item->id => 1], [
            'type' => 'return',
            'reason' => 'damaged',
            'details' => 'A dent in the left door.',
        ]);

        $this->assertSame('requested', $return->status);
        $this->assertSame(1, $return->items->sum('quantity'));
        $this->assertStringStartsWith('RET/', $return->reference);
    }

    /**
     * The refund is what was actually paid for those units, not the
     * catalogue price — a checkout discount must not come back as full value.
     */
    public function test_the_refund_is_based_on_what_was_paid_per_unit(): void
    {
        $order = $this->order(2);
        $item = $order->items->first();

        // 2 units for 23,600 → 11,800 each.
        $return = $this->returns->request($order, [$item->id => 1], [
            'type' => 'return', 'reason' => 'damaged',
        ]);

        $this->assertEqualsWithDelta(11800.0, (float) $return->refund_amount, 0.01);
    }

    public function test_more_cannot_be_returned_than_was_bought(): void
    {
        $order = $this->order(1);
        $item = $order->items->first();

        $this->expectException(ReturnNotPossible::class);
        $this->expectExceptionMessage('only 1 can still be returned');

        $this->returns->request($order, [$item->id => 3], ['type' => 'return', 'reason' => 'damaged']);
    }

    public function test_the_same_unit_cannot_be_returned_twice(): void
    {
        $order = $this->order(2);
        $item = $order->items->first();

        $this->returns->request($order, [$item->id => 2], ['type' => 'return', 'reason' => 'damaged']);

        $this->expectException(ReturnNotPossible::class);

        $this->returns->request($order->fresh('items'), [$item->id => 1], ['type' => 'return', 'reason' => 'damaged']);
    }

    /**
     * A refused claim frees its units: the customer may raise it again, or
     * for a different reason.
     */
    public function test_a_refused_claim_frees_its_units_again(): void
    {
        $order = $this->order(1);
        $item = $order->items->first();

        $return = $this->returns->request($order, [$item->id => 1], ['type' => 'return', 'reason' => 'damaged']);
        $this->returns->reject($return, $this->admin, 'Outside the window.');

        $available = $this->returns->returnableItems($order->fresh('items'));

        $this->assertSame(1, $available[$item->id]['available']);
    }

    public function test_an_order_that_never_arrived_cannot_be_returned(): void
    {
        $order = $this->order(1, ['status' => 'shipped', 'delivered_at' => null]);

        $this->expectException(ReturnNotPossible::class);
        $this->expectExceptionMessage('delivered');

        $this->returns->request($order, [$order->items->first()->id => 1], [
            'type' => 'return', 'reason' => 'damaged',
        ]);
    }

    /**
     * A replacement or a repair is not a refund, so no money is promised.
     */
    public function test_a_replacement_claim_carries_no_refund(): void
    {
        $order = $this->order(1);

        $return = $this->returns->request($order, [$order->items->first()->id => 1], [
            'type' => 'replacement', 'reason' => 'defective',
        ]);

        $this->assertSame('0.00', (string) $return->refund_amount);
    }

    /* ---------------------------------------------------------- stock */

    /**
     * Approving a return does not put anything back on the shelf — the box
     * has not arrived yet.
     */
    public function test_approving_does_not_restock(): void
    {
        $order = $this->order(1);
        $before = $this->product->stock_quantity;

        $return = $this->returns->request($order, [$order->items->first()->id => 1], [
            'type' => 'return', 'reason' => 'changed_mind',
        ]);

        $this->returns->approve($return, $this->admin);

        $this->assertSame($before, $this->product->fresh()->stock_quantity);
    }

    public function test_receiving_restocks_only_what_was_ticked(): void
    {
        $order = $this->order(2);
        $before = $this->product->stock_quantity;

        $return = $this->returns->request($order, [$order->items->first()->id => 2], [
            'type' => 'return', 'reason' => 'changed_mind',
        ]);

        $this->returns->approve($return, $this->admin);
        $this->returns->receive($return, $this->admin, [$return->items->first()->id => true]);

        $this->assertSame($before + 2, $this->product->fresh()->stock_quantity);
    }

    /**
     * Damaged goods come back but are not sellable.
     */
    public function test_an_unticked_line_does_not_go_back_on_the_shelf(): void
    {
        $order = $this->order(1);
        $before = $this->product->stock_quantity;

        $return = $this->returns->request($order, [$order->items->first()->id => 1], [
            'type' => 'return', 'reason' => 'damaged',
        ]);

        $this->returns->approve($return, $this->admin);
        $this->returns->receive($return, $this->admin, []);

        $this->assertSame($before, $this->product->fresh()->stock_quantity);
        $this->assertFalse($return->fresh('items')->items->first()->restock);
    }

    /* -------------------------------------------------------- refunds */

    public function test_recording_a_refund_marks_the_order_refunded(): void
    {
        $order = $this->order(2);

        $return = $this->returns->request($order, [$order->items->first()->id => 2], [
            'type' => 'return', 'reason' => 'damaged',
        ]);

        $this->returns->approve($return, $this->admin);
        $this->returns->refund($return, $this->admin, 23600.0, 'original', 'rfnd_123');

        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertSame('refunded', $order->fresh()->payment_status);
    }

    public function test_a_partial_refund_leaves_the_order_part_paid(): void
    {
        $order = $this->order(2);

        $return = $this->returns->request($order, [$order->items->first()->id => 1], [
            'type' => 'return', 'reason' => 'damaged',
        ]);

        $this->returns->approve($return, $this->admin);
        $this->returns->refund($return, $this->admin, 11800.0, 'original');

        $this->assertSame('partly_paid', $order->fresh()->payment_status);
    }

    /* ------------------------------------------------------- the screens */

    public function test_staff_can_approve_and_the_customer_is_told(): void
    {
        Mail::fake();

        $order = $this->order(1);
        $return = $this->returns->request($order, [$order->items->first()->id => 1], [
            'type' => 'return', 'reason' => 'damaged',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.approve', $return), [
                'customer_message' => 'We will collect on Friday.',
                'notify' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('approved', $return->fresh()->status);
        Mail::assertSent(ReturnUpdated::class, 1);
    }

    /**
     * A refusal with no reason is the thing customers complain about.
     */
    public function test_refusing_needs_a_reason(): void
    {
        $order = $this->order(1);
        $return = $this->returns->request($order, [$order->items->first()->id => 1], [
            'type' => 'return', 'reason' => 'damaged',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.reject', $return), ['customer_message' => ''])
            ->assertSessionHasErrors('customer_message');

        $this->assertSame('requested', $return->fresh()->status);
    }

    public function test_a_refund_cannot_be_recorded_before_approval(): void
    {
        $order = $this->order(1);
        $return = $this->returns->request($order, [$order->items->first()->id => 1], [
            'type' => 'return', 'reason' => 'damaged',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.returns.refund', $return), [
                'refund_amount' => 11800,
                'refund_method' => 'original',
            ])
            ->assertRedirect();

        $this->assertSame('requested', $return->fresh()->status);
    }

    public function test_the_returns_list_and_detail_render(): void
    {
        $order = $this->order(1);
        $return = $this->returns->request($order, [$order->items->first()->id => 1], [
            'type' => 'return', 'reason' => 'damaged', 'details' => 'Dented door.',
        ]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.returns.index'))
            ->assertOk()
            ->assertSee($return->reference);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.returns.show', $return))
            ->assertOk()
            ->assertSee('Dented door.')
            ->assertSee('Arrived damaged');
    }

    public function test_returns_need_the_order_permission(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('dashboard.view');

        $this->actingAs($outsider, 'admin')
            ->get(route('admin.returns.index'))
            ->assertForbidden();
    }

    /* ------------------------------------------------- the customer side */

    public function test_a_customer_can_raise_a_return_within_the_window(): void
    {
        Mail::fake();

        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $order = $this->order(1, ['customer_id' => $customer->id]);

        $this->actingAs($customer, 'customer')
            ->post(route('account.order.return.store', $order->public_token), [
                'quantities' => [$order->items->first()->id => 1],
                'type' => 'return',
                'reason' => 'damaged',
                'details' => 'Arrived with a scratch.',
            ])
            ->assertRedirect(route('account.order', $order->public_token))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('order_returns', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'status' => 'requested',
        ]);
    }

    public function test_the_return_window_closes(): void
    {
        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        // The window is seven days; this was delivered a month ago.
        $order = $this->order(1, [
            'customer_id' => $customer->id,
            'delivered_at' => now()->subMonth(),
        ]);

        $this->assertFalse($order->isReturnable());

        $this->actingAs($customer, 'customer')
            ->get(route('account.order.return', $order->public_token))
            ->assertNotFound();
    }

    public function test_a_customer_cannot_return_someone_elses_order(): void
    {
        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $other = Customer::create([
            'name' => 'Someone Else', 'email' => 'other@example.com', 'phone' => '9800000000',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $order = $this->order(1, ['customer_id' => $other->id]);

        $this->actingAs($customer, 'customer')
            ->get(route('account.order.return', $order->public_token))
            ->assertNotFound();
    }
}
