<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\ReportService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The reports, and the customer screens that go with them.
 *
 * One rule runs through every figure: a cancelled or returned order is not a
 * sale. A report that quietly includes them reads well and is wrong, and the
 * person acting on it finds out at the end of the quarter.
 */
class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected ReportService $reports;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->reports = app(ReportService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function order(array $overrides = [], int $quantity = 1): Order
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
            'subtotal' => 11800 * $quantity,
            'tax_total' => 1800 * $quantity,
            'grand_total' => 11800 * $quantity,
            'amount_paid' => 11800 * $quantity,
            'amount_due' => 0,
            'payment_method' => 'razorpay',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'placed_at' => now(),
        ], $overrides));

        $order->items()->create([
            'name' => 'Steel Almirah', 'sku' => 'SA-1', 'quantity' => $quantity,
            'unit_price' => 11800, 'line_subtotal' => 11800 * $quantity,
            'gst_rate' => 18, 'tax_amount' => 1800 * $quantity, 'line_total' => 11800 * $quantity,
        ]);

        return $order->fresh('items');
    }

    /* -------------------------------------------------------- summary */

    public function test_the_summary_counts_real_sales(): void
    {
        $this->order();
        $this->order(['amount_paid' => 0, 'amount_due' => 11800, 'payment_status' => 'pending']);

        $summary = $this->reports->summary(now()->startOfMonth(), now()->endOfDay());

        $this->assertSame(2, $summary['orders']);
        $this->assertEqualsWithDelta(11800.0, $summary['received'], 0.01);
        $this->assertEqualsWithDelta(23600.0, $summary['invoiced'], 0.01);
        $this->assertEqualsWithDelta(11800.0, $summary['outstanding'], 0.01);
    }

    /**
     * The figure that matters most, and the easiest to get wrong.
     */
    public function test_cancelled_and_returned_orders_are_left_out(): void
    {
        $this->order();
        $this->order(['status' => 'cancelled']);
        $this->order(['status' => 'returned']);

        $summary = $this->reports->summary(now()->startOfMonth(), now()->endOfDay());

        $this->assertSame(1, $summary['orders']);
        $this->assertEqualsWithDelta(11800.0, $summary['invoiced'], 0.01);

        // Counted separately, so the loss is visible rather than hidden.
        $this->assertSame(1, $summary['cancelled']);
        $this->assertSame(1, $summary['returned']);
    }

    /**
     * Revenue is money received, not money invoiced: an uncollected COD
     * order is not revenue.
     */
    public function test_received_is_money_in_not_money_invoiced(): void
    {
        $this->order(['amount_paid' => 0, 'amount_due' => 11800, 'payment_method' => 'cod']);

        $summary = $this->reports->summary(now()->startOfMonth(), now()->endOfDay());

        $this->assertSame(0.0, $summary['received']);
        $this->assertEqualsWithDelta(11800.0, $summary['invoiced'], 0.01);
    }

    public function test_orders_outside_the_period_are_not_counted(): void
    {
        $this->order(['placed_at' => now()->subMonths(3)]);

        $this->assertSame(0, $this->reports->summary(now()->startOfMonth(), now()->endOfDay())['orders']);
    }

    /* ---------------------------------------------------------- daily */

    /**
     * A gap in a sales chart reads as missing data rather than no sales.
     */
    public function test_quiet_days_still_appear(): void
    {
        $this->order();

        $days = $this->reports->daily(now()->subDays(6)->startOfDay(), now()->endOfDay());

        $this->assertCount(7, $days);
        $this->assertSame(0, $days->first()['orders']);
        $this->assertSame(1, $days->last()['orders']);
    }

    /* ------------------------------------------------------- products */

    public function test_top_products_add_up_across_orders(): void
    {
        $this->order(quantity: 2);
        $this->order(quantity: 3);

        $top = $this->reports->topProducts(now()->startOfMonth(), now()->endOfDay());

        $this->assertSame('Steel Almirah', $top->first()['name']);
        $this->assertSame(5, $top->first()['units']);
        $this->assertSame(2, $top->first()['orders']);
    }

    public function test_a_cancelled_orders_items_do_not_count_as_sold(): void
    {
        $this->order(['status' => 'cancelled'], quantity: 5);

        $this->assertCount(0, $this->reports->topProducts(now()->startOfMonth(), now()->endOfDay()));
    }

    /* ------------------------------------------------------------ GST */

    /**
     * GST is owed on invoices raised, so an order with no invoice yet does
     * not belong in the return.
     */
    public function test_the_gst_summary_only_counts_invoiced_orders(): void
    {
        $this->order();

        $this->assertCount(0, $this->reports->gstSummary(now()->startOfMonth(), now()->endOfDay()));

        $this->order([
            'invoice_number' => 'INV/2611/00001',
            'invoiced_at' => now(),
            'is_interstate' => false,
        ]);

        $rows = $this->reports->gstSummary(now()->startOfMonth(), now()->endOfDay());

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(1800.0, $rows->first()['total'], 0.01);
        $this->assertEqualsWithDelta(900.0, $rows->first()['cgst'], 0.01);
        $this->assertSame(0.0, $rows->first()['igst']);
    }

    public function test_an_interstate_invoice_reports_igst(): void
    {
        $this->order([
            'invoice_number' => 'INV/2611/00002',
            'invoiced_at' => now(),
            'is_interstate' => true,
        ]);

        $row = $this->reports->gstSummary(now()->startOfMonth(), now()->endOfDay())->first();

        $this->assertEqualsWithDelta(1800.0, $row['igst'], 0.01);
        $this->assertSame(0.0, $row['cgst']);
    }

    /* ----------------------------------------------------- valuation */

    public function test_stock_valuation_ignores_products_with_no_cost(): void
    {
        $category = \App\Models\Category::create(['name' => 'A', 'slug' => 'a', 'is_active' => true]);

        Product::create([
            'category_id' => $category->id, 'name' => 'Priced', 'slug' => 'priced', 'sku' => 'P1',
            'price' => 1000, 'cost_price' => 600, 'stock_quantity' => 10, 'status' => 'active',
        ]);

        Product::create([
            'category_id' => $category->id, 'name' => 'Unpriced', 'slug' => 'unpriced', 'sku' => 'P2',
            'price' => 1000, 'stock_quantity' => 5, 'status' => 'active',
        ]);

        $valuation = $this->reports->stockValuation();

        $this->assertEqualsWithDelta(6000.0, $valuation['value'], 0.01);
        $this->assertSame(15, $valuation['units']);
        $this->assertSame(1, $valuation['unpriced']);
    }

    /* ------------------------------------------------------ the screens */

    public function test_the_report_screens_render(): void
    {
        $this->order();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.index'))->assertOk()->assertSee('Received');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.gst'))->assertOk()->assertSee('GST summary');
    }

    public function test_the_sales_export_is_a_csv(): void
    {
        $this->order();

        $csv = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.export', ['report' => 'sales']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('date,orders,invoiced,received', $csv);
    }

    /**
     * A range the wrong way round is a typo, not a request for nothing.
     */
    public function test_backwards_dates_are_swapped_rather_than_showing_nothing(): void
    {
        $this->order(['placed_at' => now()->subDays(3)]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.index', [
                'period' => 'custom',
                'from' => now()->toDateString(),
                'to' => now()->subWeek()->toDateString(),
            ]))
            ->assertOk()
            ->assertViewHas('summary', fn (array $s) => $s['orders'] === 1);
    }

    public function test_reports_need_their_permission(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('dashboard.view');

        $this->actingAs($outsider, 'admin')
            ->get(route('admin.reports.index'))
            ->assertForbidden();
    }

    /* ------------------------------------------------------- customers */

    public function test_the_customer_list_shows_what_each_has_spent(): void
    {
        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $this->order(['customer_id' => $customer->id]);
        $this->order(['customer_id' => $customer->id, 'status' => 'cancelled']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.index'))
            ->assertOk()
            ->assertSee('Ravi Kumar')
            // The cancelled order must not inflate what they are worth.
            ->assertSee(money(11800));
    }

    public function test_staff_can_disable_a_customer(): void
    {
        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.customers.toggle', $customer))
            ->assertRedirect();

        $this->assertFalse($customer->fresh()->is_active);
    }

    /**
     * An account somebody else can silently take over is not an account.
     */
    public function test_there_is_no_route_for_changing_a_customers_credentials(): void
    {
        $names = collect(app('router')->getRoutes())->map(fn ($r) => $r->getName())->filter();

        $this->assertFalse($names->contains('admin.customers.update'));
        $this->assertFalse($names->contains('admin.customers.password'));
    }

    public function test_the_customer_export_is_a_csv(): void
    {
        Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $csv = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.customers.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('ravi@example.com', $csv);
    }
}
