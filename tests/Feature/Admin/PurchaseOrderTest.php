<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseNotPossible;
use App\Services\PurchaseService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Suppliers and the orders we place with them.
 *
 * Receiving is the operation that matters: it is where a number on a
 * delivery note becomes a number in the catalogue, and it has to go through
 * the ledger so every unit can be accounted for.
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected PurchaseService $purchases;

    protected User $admin;

    protected Supplier $supplier;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->purchases = app(PurchaseService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $this->supplier = Supplier::create([
            'name' => 'Sharma Timber Works',
            'code' => 'SHARMA',
            'is_active' => true,
        ]);

        $category = Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Steel Almirah', 'slug' => 'steel-almirah', 'sku' => Str::upper(Str::random(8)),
            'price' => 11800, 'stock_quantity' => 10, 'gst_rate' => 18, 'status' => 'active',
        ]);
    }

    protected function order(int $quantity = 10, float $cost = 8500, bool $linked = true): PurchaseOrder
    {
        $order = $this->purchases->create($this->supplier, [], $this->admin);

        return $this->purchases->setItems($order, [[
            'product_id' => $linked ? $this->product->id : null,
            'name' => $linked ? $this->product->name : 'Freight',
            'sku' => $linked ? $this->product->sku : null,
            'quantity' => $quantity,
            'unit_cost' => $cost,
            'gst_rate' => 18,
        ]]);
    }

    /* ------------------------------------------------------ suppliers */

    public function test_a_supplier_without_a_code_gets_one(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.suppliers.store'), ['name' => 'Delhi Hardware Mart'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('suppliers', ['name' => 'Delhi Hardware Mart', 'code' => 'DELHIH']);
    }

    public function test_a_supplier_state_must_be_on_the_list(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.suppliers.store'), ['name' => 'Somebody', 'state' => 'UP'])
            ->assertSessionHasErrors('state');
    }

    /**
     * A supplier with purchase history is part of the record of where stock
     * came from.
     */
    public function test_a_supplier_with_orders_cannot_be_deleted(): void
    {
        $this->order();

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.suppliers.destroy', $this->supplier))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('suppliers', ['id' => $this->supplier->id, 'deleted_at' => null]);
    }

    /* ------------------------------------------------------- pricing */

    public function test_costs_include_gst_and_the_tax_is_taken_back_out(): void
    {
        $order = $this->order(quantity: 10, cost: 11800);

        $this->assertSame('118000.00', (string) $order->subtotal);
        $this->assertEqualsWithDelta(18000.0, (float) $order->tax_total, 0.5);
        $this->assertSame('118000.00', (string) $order->grand_total);
    }

    public function test_other_charges_are_added_to_the_total(): void
    {
        $order = $this->order(quantity: 1, cost: 10000);

        $order->update(['other_charges' => 1500]);
        $order = $this->purchases->retotal($order);

        $this->assertSame('11500.00', (string) $order->grand_total);
    }

    /* -------------------------------------------------------- placing */

    public function test_an_empty_order_cannot_be_placed(): void
    {
        $this->expectException(PurchaseNotPossible::class);
        $this->expectExceptionMessage('at least one line');

        $this->purchases->place($this->purchases->create($this->supplier, [], $this->admin));
    }

    /**
     * Once it has gone to the supplier it is a record of what was agreed.
     */
    public function test_a_placed_order_cannot_have_its_lines_changed(): void
    {
        $order = $this->purchases->place($this->order());

        $this->expectException(PurchaseNotPossible::class);
        $this->expectExceptionMessage('Only a draft');

        $this->purchases->setItems($order, [[
            'name' => 'Something else', 'quantity' => 1, 'unit_cost' => 1, 'gst_rate' => 0,
        ]]);
    }

    /* ------------------------------------------------------ receiving */

    public function test_receiving_puts_stock_on_the_shelf_through_the_ledger(): void
    {
        $order = $this->purchases->place($this->order(quantity: 10, cost: 8500));

        $item = $order->items->first();

        $this->purchases->receive($order, [$item->id => 10], 'STW/4471', $this->admin);

        $this->assertSame(20, (int) $this->product->fresh()->stock_quantity);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => 'purchase',
            'quantity' => 10,
            'reference_label' => $order->number,
        ]);

        $this->assertSame('received', $order->fresh()->status);
        $this->assertSame('STW/4471', $order->fresh()->supplier_invoice_number);
    }

    /**
     * Deliveries arrive in parts, and the order has to say so.
     */
    public function test_a_part_delivery_leaves_the_order_part_received(): void
    {
        $order = $this->purchases->place($this->order(quantity: 10));
        $item = $order->items->first();

        $order = $this->purchases->receive($order, [$item->id => 6], null, $this->admin);

        $this->assertSame('partial', $order->status);
        $this->assertSame(6, $order->items->first()->received_quantity);
        $this->assertSame(4, $order->items->first()->outstanding);
        $this->assertSame(16, (int) $this->product->fresh()->stock_quantity);

        $order = $this->purchases->receive($order, [$item->id => 4], null, $this->admin);

        $this->assertSame('received', $order->status);
        $this->assertSame(20, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_more_cannot_be_received_than_was_ordered(): void
    {
        $order = $this->purchases->place($this->order(quantity: 5));
        $item = $order->items->first();

        $this->expectException(PurchaseNotPossible::class);
        $this->expectExceptionMessage('only 5 still to come');

        $this->purchases->receive($order, [$item->id => 9], null, $this->admin);
    }

    /**
     * Freight has no shelf to go on.
     */
    public function test_a_line_with_no_product_cannot_be_stocked(): void
    {
        $order = $this->purchases->place($this->order(linked: false));
        $item = $order->items->first();

        $this->expectException(PurchaseNotPossible::class);
        $this->expectExceptionMessage('not linked to a catalogue product');

        $this->purchases->receive($order, [$item->id => 1], null, $this->admin);
    }

    public function test_receiving_nothing_is_refused(): void
    {
        $order = $this->purchases->place($this->order());
        $item = $order->items->first();

        $this->expectException(PurchaseNotPossible::class);

        $this->purchases->receive($order, [$item->id => 0], null, $this->admin);
    }

    /**
     * The valuation report is only as good as the cost prices behind it.
     */
    public function test_receiving_records_what_we_paid(): void
    {
        $this->assertNull($this->product->cost_price);

        $order = $this->purchases->place($this->order(quantity: 4, cost: 7250));
        $this->purchases->receive($order, [$order->items->first()->id => 4], null, $this->admin);

        $this->assertSame('7250.00', (string) $this->product->fresh()->cost_price);
    }

    /* ----------------------------------------------------- cancelling */

    public function test_a_draft_or_placed_order_can_be_cancelled(): void
    {
        $order = $this->purchases->place($this->order());

        $this->purchases->cancel($order, 'Supplier cannot supply.');

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    /**
     * Cancelling after part of it arrived would leave that stock unexplained.
     */
    public function test_an_order_with_a_part_delivery_cannot_be_cancelled(): void
    {
        $order = $this->purchases->place($this->order(quantity: 10));
        $order = $this->purchases->receive($order, [$order->items->first()->id => 3], null, $this->admin);

        $this->expectException(PurchaseNotPossible::class);
        $this->expectExceptionMessage('already arrived');

        $this->purchases->cancel($order);
    }

    /* ------------------------------------------------------ the screens */

    public function test_the_screens_render(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.suppliers.index'))->assertOk()->assertSee('Sharma Timber Works');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.suppliers.show', $this->supplier))->assertOk()->assertSee('SHARMA');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.purchases.index'))->assertOk()->assertSee($order->number);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.purchases.show', $order))->assertOk()->assertSee('Steel Almirah');
    }

    public function test_staff_can_receive_from_the_screen(): void
    {
        $order = $this->purchases->place($this->order(quantity: 5));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.purchases.receive', $order), [
                'quantities' => [$order->items->first()->id => 5],
                'supplier_invoice_number' => 'INV-77',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(15, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_an_oversend_from_the_screen_is_reported_not_saved(): void
    {
        $order = $this->purchases->place($this->order(quantity: 2));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.purchases.receive', $order), [
                'quantities' => [$order->items->first()->id => 20],
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(10, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_purchases_need_their_permission(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('dashboard.view');

        $this->actingAs($outsider, 'admin')
            ->get(route('admin.purchases.index'))
            ->assertForbidden();
    }
}
