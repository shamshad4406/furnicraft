<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GST tax invoices.
 *
 * A tax invoice is a legal document, so the tests here are about the things
 * an auditor would ask: is the number unique and unbroken, does it say the
 * same thing on a reprint, and is the tax split right for where the goods
 * actually went.
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoices;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->invoices = app(InvoiceService::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        // The shop is in Delhi throughout these tests.
        $this->setSetting('gst_state', 'Delhi');
        $this->setSetting('gst_number', '07AABCF1234M1Z5');
    }

    protected function setSetting(string $key, string $value): void
    {
        Setting::where('key', $key)->update(['value' => $value]);
        app(SettingService::class)->flush();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    protected function order(string $deliveryState = 'Delhi', array $items = [], array $overrides = []): Order
    {
        $address = [
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park', 'city' => 'New Delhi',
            'state' => $deliveryState, 'pincode' => '110001',
        ];

        $items = $items ?: [[
            'name' => 'Steel Almirah', 'sku' => 'SA-1', 'hsn_code' => '94035000',
            'quantity' => 1, 'unit_price' => 11800, 'line_subtotal' => 11800,
            'gst_rate' => 18, 'tax_amount' => 1800, 'line_total' => 11800,
        ]];

        $order = Order::create(array_merge([
            'order_number' => 'FC/2611/'.str_pad((string) (Order::count() + 1), 5, '0', STR_PAD_LEFT),
            'customer_name' => 'Ravi Kumar',
            'customer_email' => 'ravi@example.com',
            'customer_phone' => '9811122233',
            'shipping_address' => $address,
            'billing_address' => $address,
            'shipping_pincode' => '110001',
            'subtotal' => array_sum(array_column($items, 'line_subtotal')),
            'tax_total' => array_sum(array_column($items, 'tax_amount')),
            'grand_total' => array_sum(array_column($items, 'line_total')),
            'payment_method' => 'cod',
            'status' => 'confirmed',
            'placed_at' => now(),
        ], $overrides));

        foreach ($items as $item) {
            $order->items()->create($item);
        }

        return $order->fresh('items');
    }

    /* ------------------------------------------------------- numbering */

    public function test_an_invoice_number_is_issued_once_and_kept(): void
    {
        $order = $this->order();

        $this->invoices->raise($order);
        $first = $order->fresh()->invoice_number;

        $this->assertNotNull($first);

        // A second click must not burn a number or change the document.
        $this->invoices->raise($order->fresh());

        $this->assertSame($first, $order->fresh()->invoice_number);
    }

    /**
     * Invoices run in their own unbroken sequence. Sharing the order counter
     * would leave gaps an auditor has to ask about.
     */
    public function test_invoice_numbers_run_separately_from_order_numbers(): void
    {
        $first = $this->order();
        $second = $this->order();

        $this->invoices->raise($first);
        $this->invoices->raise($second);

        $this->assertSame('INV/2611/00001', $first->fresh()->invoice_number);
        $this->assertSame('INV/2611/00002', $second->fresh()->invoice_number);
    }

    public function test_a_pending_order_cannot_be_invoiced(): void
    {
        $order = $this->order(overrides: ['status' => 'pending']);

        $this->assertFalse($this->invoices->canRaise($order));
    }

    public function test_a_cancelled_order_is_never_invoiced(): void
    {
        $order = $this->order(overrides: ['status' => 'cancelled']);

        $this->assertFalse($this->invoices->canRaise($order));
    }

    /* ------------------------------------------------------- the split */

    public function test_a_delivery_inside_the_sellers_state_is_cgst_and_sgst(): void
    {
        $order = $this->invoices->raise($this->order('Delhi'));

        $totals = $this->invoices->totals($order);

        $this->assertFalse($order->is_interstate);
        $this->assertEqualsWithDelta(900.0, $totals['cgst'], 0.01);
        $this->assertEqualsWithDelta(900.0, $totals['sgst'], 0.01);
        $this->assertSame(0.0, $totals['igst']);
    }

    public function test_a_delivery_to_another_state_is_igst(): void
    {
        $order = $this->invoices->raise($this->order('Maharashtra'));

        $totals = $this->invoices->totals($order);

        $this->assertTrue($order->is_interstate);
        $this->assertEqualsWithDelta(1800.0, $totals['igst'], 0.01);
        $this->assertSame(0.0, $totals['cgst']);
        $this->assertSame(0.0, $totals['sgst']);
    }

    /**
     * People type "UP", not "Uttar Pradesh". Failing to recognise it would
     * put CGST + SGST on an inter-state sale — the wrong tax on a legal
     * document, not a cosmetic slip.
     */
    public function test_a_state_abbreviation_is_understood(): void
    {
        $order = $this->invoices->raise($this->order('UP'));

        $this->assertTrue($order->is_interstate);
        $this->assertSame('09', $order->place_of_supply_code);
    }

    /**
     * @dataProvider stateNames
     */
    public function test_state_names_resolve_to_their_gst_codes(string $input, ?string $expected): void
    {
        $this->assertSame($expected, $this->invoices->stateCode($input));
    }

    /**
     * @return array<string, array{0: string, 1: string|null}>
     */
    public static function stateNames(): array
    {
        return [
            'full name' => ['Uttar Pradesh', '09'],
            'abbreviation' => ['UP', '09'],
            'dotted abbreviation' => ['U.P.', '09'],
            'city and state' => ['New Delhi, Delhi', '07'],
            'ampersand' => ['J&K', '01'],
            'spaced ampersand' => ['Jammu & Kashmir', '01'],
            'old name' => ['Orissa', '21'],
            'lower case' => ['tamil nadu', '33'],
            'similar prefixes' => ['Andhra Pradesh', '37'],
            'unknown' => ['Atlantis', null],
        ];
    }

    public function test_an_unrecognised_state_falls_back_to_intra_state(): void
    {
        // Safer for a domestic shop than silently switching tax type, and the
        // unknown place of supply shows on the invoice for someone to fix.
        $order = $this->invoices->raise($this->order('Atlantis'));

        $this->assertFalse($order->is_interstate);
        $this->assertNull($order->place_of_supply_code);
        $this->assertSame('Atlantis', $order->place_of_supply);
    }

    /* ----------------------------------------------------- frozen facts */

    /**
     * Settings change; an issued invoice does not. Moving the shop to another
     * state must not retrospectively rewrite last year's tax.
     */
    public function test_a_raised_invoice_keeps_its_split_when_settings_change(): void
    {
        $order = $this->invoices->raise($this->order('Delhi'));

        $this->assertFalse($order->is_interstate);

        $this->setSetting('gst_state', 'Maharashtra');

        $this->assertFalse($this->invoices->totals($order->fresh())['interstate']);
        $this->assertFalse($order->fresh()->is_interstate);
    }

    public function test_the_sellers_gst_number_is_frozen_onto_the_invoice(): void
    {
        $order = $this->invoices->raise($this->order());

        $this->assertSame('07AABCF1234M1Z5', $order->seller_gst_number);

        $this->setSetting('gst_number', '27ZZZZZ9999Z1Z9');

        $this->assertSame('07AABCF1234M1Z5', $order->fresh()->seller_gst_number);
    }

    /* ------------------------------------------------------ the summary */

    /**
     * GST has to be summarised per rate, not per product.
     */
    public function test_the_tax_table_groups_lines_by_rate(): void
    {
        $order = $this->order('Delhi', [
            [
                'name' => 'Almirah', 'sku' => 'A1', 'quantity' => 1,
                'unit_price' => 11800, 'line_subtotal' => 11800,
                'gst_rate' => 18, 'tax_amount' => 1800, 'line_total' => 11800,
            ],
            [
                'name' => 'Chair', 'sku' => 'C1', 'quantity' => 1,
                'unit_price' => 5900, 'line_subtotal' => 5900,
                'gst_rate' => 18, 'tax_amount' => 900, 'line_total' => 5900,
            ],
            [
                'name' => 'Mat', 'sku' => 'M1', 'quantity' => 1,
                'unit_price' => 1050, 'line_subtotal' => 1050,
                'gst_rate' => 5, 'tax_amount' => 50, 'line_total' => 1050,
            ],
        ]);

        $summary = $this->invoices->taxSummary($order);

        $this->assertCount(2, $summary, 'Three lines at two rates should give two rows.');
        $this->assertSame(5.0, $summary[0]['rate']);
        $this->assertSame(18.0, $summary[1]['rate']);
        $this->assertEqualsWithDelta(2700.0, $summary[1]['total'], 0.01);
    }

    /**
     * Catalogue prices include GST, so the taxable value is the price less
     * the tax already inside it — never the price itself.
     */
    public function test_the_taxable_value_has_the_tax_taken_back_out(): void
    {
        $order = $this->order('Delhi');

        $totals = $this->invoices->totals($order);

        $this->assertEqualsWithDelta(10000.0, $totals['taxable'], 0.01);
        $this->assertEqualsWithDelta(1800.0, $totals['tax'], 0.01);
    }

    /**
     * Halving an odd number of paise must not lose one.
     */
    public function test_an_odd_tax_amount_splits_without_losing_a_paisa(): void
    {
        $order = $this->order('Delhi', [[
            'name' => 'Odd', 'sku' => 'O1', 'quantity' => 1,
            'unit_price' => 100, 'line_subtotal' => 100,
            'gst_rate' => 18, 'tax_amount' => 15.25, 'line_total' => 100,
        ]]);

        $row = $this->invoices->taxSummary($order)->first();

        $this->assertEqualsWithDelta(15.25, $row['cgst'] + $row['sgst'], 0.001);
    }

    /* --------------------------------------------------------- the page */

    public function test_the_invoice_page_renders_for_a_raised_invoice(): void
    {
        $order = $this->invoices->raise($this->order('Maharashtra'));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.invoice', $order))
            ->assertOk()
            ->assertSee('Tax Invoice', false)
            ->assertSee($order->invoice_number)
            ->assertSee('IGST')
            ->assertDontSee('CGST');
    }

    public function test_the_invoice_page_is_a_404_before_one_is_raised(): void
    {
        $order = $this->order();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.invoice', $order))
            ->assertNotFound();
    }

    public function test_the_invoice_spells_the_total_out_in_words(): void
    {
        $order = $this->invoices->raise($this->order());

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.orders.invoice', $order))
            ->assertOk()
            ->assertSee('Eleven Thousand Eight Hundred');
    }

    public function test_raising_an_invoice_needs_the_edit_permission(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'orders.view']);

        $order = $this->order();

        $this->actingAs($viewer, 'admin')
            ->post(route('admin.orders.invoice.raise', $order))
            ->assertForbidden();

        $this->assertNull($order->fresh()->invoice_number);
    }
}
