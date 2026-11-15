<?php

namespace Tests\Feature\Admin;

use App\Mail\QuotationSent;
use App\Models\Category;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\QuotationNotReady;
use App\Services\QuotationService;
use App\Services\SettingService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Quotations: pricing them, sending them, and turning an accepted one into
 * an order.
 *
 * The rule under all of it: the figure the customer agreed to is the figure
 * they are charged. A quotation that re-prices itself on conversion is an
 * argument waiting to happen.
 */
class QuotationTest extends TestCase
{
    use RefreshDatabase;

    protected QuotationService $quotations;

    protected User $admin;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->quotations = app(QuotationService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        Setting::where('key', 'gst_state')->update(['value' => 'Delhi']);
        app(SettingService::class)->flush();

        $category = Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Steel Almirah', 'slug' => 'steel-almirah', 'sku' => Str::upper(Str::random(8)),
            'price' => 11800, 'stock_quantity' => 5, 'gst_rate' => 18,
            'hsn_code' => '94035000', 'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function quotation(array $overrides = [], ?Enquiry $enquiry = null): Quotation
    {
        return $this->quotations->create(array_merge([
            'customer_name' => 'Ravi Kumar',
            'customer_email' => 'ravi@example.com',
            'customer_phone' => '9811122233',
            'shipping_address' => [
                'name' => 'Ravi Kumar', 'phone' => '9811122233',
                'line1' => '12 Green Park', 'city' => 'New Delhi',
                'state' => 'Delhi', 'pincode' => '110001',
            ],
        ], $overrides), $enquiry, $this->admin);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $lines
     */
    protected function priced(?array $lines = null, array $overrides = []): Quotation
    {
        $quotation = $this->quotation($overrides);

        return $this->quotations->setItems($quotation, $lines ?? [[
            'name' => 'Steel Almirah',
            'quantity' => 2,
            'unit_price' => 11800,
            'gst_rate' => 18,
        ]]);
    }

    protected function enquiry(): Enquiry
    {
        return app(EnquiryService::class)->create([
            'type' => 'product',
            'product_id' => $this->product->id,
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'phone' => '9811122233',
            'message' => 'Can you do two of these in teak?',
            'quantity' => 2,
        ]);
    }

    /* ----------------------------------------------------------- pricing */

    public function test_a_quotation_gets_its_own_number_and_a_validity_date(): void
    {
        $quotation = $this->quotation();

        $this->assertStringStartsWith('QT/', $quotation->number);
        $this->assertSame('draft', $quotation->status);
        $this->assertNotNull($quotation->valid_until);
    }

    /**
     * Prices include GST, exactly as in the catalogue, so the figure quoted
     * is the figure paid.
     */
    public function test_tax_is_taken_out_of_the_price_rather_than_added_on(): void
    {
        $quotation = $this->priced();

        $this->assertSame('23600.00', (string) $quotation->subtotal);
        $this->assertSame('23600.00', (string) $quotation->grand_total);
        $this->assertEqualsWithDelta(3600.0, (float) $quotation->tax_total, 0.05);
    }

    public function test_a_discount_and_delivery_move_the_total(): void
    {
        $quotation = $this->priced();

        $quotation->update(['discount' => 600, 'delivery_charge' => 500]);
        $quotation = $this->quotations->reprice($quotation);

        $this->assertSame('23500.00', (string) $quotation->grand_total);
    }

    public function test_a_discount_cannot_exceed_the_subtotal(): void
    {
        $quotation = $this->priced();

        $quotation->update(['discount' => 999999]);
        $quotation = $this->quotations->reprice($quotation);

        $this->assertSame('23600.00', (string) $quotation->discount);
        $this->assertSame('0.00', (string) $quotation->grand_total);
    }

    /**
     * The whole point of quoting rather than selling off the shelf.
     */
    public function test_a_line_can_be_something_not_in_the_catalogue(): void
    {
        $quotation = $this->priced([[
            'name' => 'Teak wardrobe, made to measure',
            'description' => '8ft × 6ft, three doors',
            'quantity' => 1,
            'unit_price' => 85000,
            'gst_rate' => 18,
        ]]);

        $item = $quotation->items->first();

        $this->assertNull($item->product_id);
        $this->assertTrue($item->isCustom());
        $this->assertSame('85000.00', (string) $quotation->grand_total);
    }

    public function test_saving_lines_again_replaces_them_rather_than_adding(): void
    {
        $quotation = $this->priced();

        $quotation = $this->quotations->setItems($quotation, [[
            'name' => 'Something else', 'quantity' => 1, 'unit_price' => 5000, 'gst_rate' => 18,
        ]]);

        $this->assertCount(1, $quotation->items);
        $this->assertSame('5000.00', (string) $quotation->grand_total);
    }

    /**
     * The same GST rule as an invoice: where the goods go decides the split.
     */
    public function test_the_gst_split_follows_the_delivery_state(): void
    {
        $local = $this->priced();
        $this->assertFalse($local->is_interstate);

        $away = $this->priced(overrides: [
            'shipping_address' => [
                'name' => 'Ravi', 'line1' => '1 Road', 'city' => 'Mumbai',
                'state' => 'Maharashtra', 'pincode' => '400001',
            ],
        ]);

        $this->assertTrue($away->is_interstate);
        $this->assertSame('27', $away->place_of_supply_code);

        $summary = $this->quotations->taxSummary($away)->first();
        $this->assertEqualsWithDelta(3600.0, $summary['igst'], 0.05);
        $this->assertSame(0.0, $summary['cgst']);
    }

    /* ------------------------------------------------------------ sending */

    public function test_a_quotation_with_no_lines_cannot_be_sent(): void
    {
        $this->expectException(QuotationNotReady::class);
        $this->expectExceptionMessage('at least one line');

        $this->quotations->send($this->quotation());
    }

    public function test_sending_emails_the_customer_and_marks_the_enquiry_quoted(): void
    {
        Mail::fake();

        $enquiry = $this->enquiry();
        $quotation = $this->quotation([], $enquiry);
        $this->quotations->setItems($quotation, [[
            'name' => 'Steel Almirah', 'quantity' => 1, 'unit_price' => 11800, 'gst_rate' => 18,
        ]]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.quotations.send', $quotation))
            ->assertRedirect();

        $this->assertSame('sent', $quotation->fresh()->status);
        $this->assertSame('quoted', $enquiry->fresh()->status);

        Mail::assertSent(QuotationSent::class, 1);
    }

    /**
     * A mail failure must not leave staff thinking it went out.
     */
    public function test_a_failed_send_is_reported(): void
    {
        $quotation = $this->priced();

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.quotations.send', $quotation))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    /* -------------------------------------------------------- the answer */

    public function test_a_customer_can_accept_with_the_token_alone(): void
    {
        Mail::fake();

        $quotation = $this->quotations->send($this->priced());

        $this->post(route('quotation.accept', $quotation->public_token), [
            'customer_note' => 'Please deliver after the 20th.',
        ])->assertRedirect();

        $quotation->refresh();

        $this->assertSame('accepted', $quotation->status);
        $this->assertSame('Please deliver after the 20th.', $quotation->customer_note);
    }

    public function test_a_customer_can_decline(): void
    {
        Mail::fake();

        $quotation = $this->quotations->send($this->priced());

        $this->post(route('quotation.decline', $quotation->public_token), [
            'decline_reason' => 'Over budget.',
        ])->assertRedirect();

        $quotation->refresh();

        $this->assertSame('declined', $quotation->status);
        $this->assertSame('Over budget.', $quotation->decline_reason);
    }

    /**
     * Expiry is worked out, not stored, so a lapsed quotation stops being
     * answerable the moment the date passes.
     */
    public function test_a_lapsed_quotation_cannot_be_accepted(): void
    {
        Mail::fake();

        $quotation = $this->quotations->send($this->priced());
        $quotation->update(['valid_until' => now()->subDay()->toDateString()]);

        $this->assertTrue($quotation->fresh()->hasLapsed());
        $this->assertSame('Expired', $quotation->fresh()->status_label);

        $this->post(route('quotation.accept', $quotation->public_token))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('sent', $quotation->fresh()->status);
    }

    public function test_a_draft_is_not_visible_to_the_customer(): void
    {
        $this->get(route('quotation.show', $this->priced()->public_token))->assertNotFound();
    }

    public function test_a_sent_quotation_renders_for_the_customer(): void
    {
        Mail::fake();

        $quotation = $this->quotations->send($this->priced());

        $this->get(route('quotation.show', $quotation->public_token))
            ->assertOk()
            ->assertSee($quotation->number)
            ->assertSee('Steel Almirah')
            ->assertSee('Accept this quotation');
    }

    /* ----------------------------------------------------- the conversion */

    public function test_an_accepted_quotation_becomes_an_order_with_the_same_figures(): void
    {
        Mail::fake();

        $quotation = $this->quotations->accept($this->quotations->send($this->priced()));

        $order = $this->quotations->convertToOrder($quotation, $this->admin);

        $this->assertSame((string) $quotation->grand_total, (string) $order->grand_total);
        $this->assertSame((string) $quotation->tax_total, (string) $order->tax_total);
        $this->assertCount(1, $order->items);
        $this->assertSame(2, $order->items->first()->quantity);

        $quotation->refresh();

        $this->assertSame('converted', $quotation->status);
        $this->assertSame($order->id, $quotation->order_id);
    }

    public function test_a_quotation_that_was_never_accepted_cannot_be_converted(): void
    {
        Mail::fake();

        $this->expectException(QuotationNotReady::class);

        $this->quotations->convertToOrder($this->quotations->send($this->priced()));
    }

    /**
     * A double-click must not raise two orders for one agreement.
     */
    public function test_converting_twice_returns_the_same_order(): void
    {
        Mail::fake();

        $quotation = $this->quotations->accept($this->quotations->send($this->priced()));

        $first = $this->quotations->convertToOrder($quotation);
        $second = $this->quotations->convertToOrder($quotation->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_converting_without_an_address_is_refused(): void
    {
        Mail::fake();

        $quotation = $this->priced(overrides: ['shipping_address' => null]);
        $quotation = $this->quotations->accept($this->quotations->send($quotation));

        $this->expectException(QuotationNotReady::class);
        $this->expectExceptionMessage('delivery address');

        $this->quotations->convertToOrder($quotation);
    }

    public function test_the_converted_order_starts_its_history(): void
    {
        Mail::fake();

        $quotation = $this->quotations->accept($this->quotations->send($this->priced()));
        $order = $this->quotations->convertToOrder($quotation);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'pending',
        ]);
    }

    public function test_converting_moves_the_enquiry_on_too(): void
    {
        Mail::fake();

        $enquiry = $this->enquiry();
        $quotation = $this->quotation([], $enquiry);
        $this->quotations->setItems($quotation, [[
            'name' => 'Almirah', 'quantity' => 1, 'unit_price' => 11800, 'gst_rate' => 18,
        ]]);

        $this->quotations->accept($this->quotations->send($quotation->fresh('items')));
        $this->quotations->convertToOrder($quotation->fresh());

        $this->assertSame('converted', $enquiry->fresh()->status);
    }

    /* -------------------------------------------------------- the screens */

    public function test_the_list_and_detail_render(): void
    {
        $quotation = $this->priced();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.quotations.index'))
            ->assertOk()
            ->assertSee($quotation->number);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.quotations.show', $quotation))
            ->assertOk()
            ->assertSee('Steel Almirah');
    }

    public function test_the_printable_copy_renders(): void
    {
        Mail::fake();

        $quotation = $this->quotations->send($this->priced());

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.quotations.print', $quotation))
            ->assertOk()
            ->assertSee('Quotation', false)
            ->assertSee($quotation->number)
            ->assertSee('Twenty Three Thousand Six Hundred');
    }

    public function test_starting_one_from_an_enquiry_carries_the_details_across(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.quotations.store'), [
                'enquiry_token' => $enquiry->public_token,
                'customer_name' => $enquiry->name,
                'customer_email' => $enquiry->email,
                'customer_phone' => $enquiry->phone,
                'shipping' => ['state' => 'Delhi'],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $quotation = Quotation::firstOrFail();

        $this->assertSame($enquiry->id, $quotation->enquiry_id);
        // The product they asked about becomes the first line.
        $this->assertSame(1, $quotation->items()->count());
        $this->assertSame(2, $quotation->items->first()->quantity);
    }

    public function test_an_unlisted_state_is_refused(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.quotations.store'), [
                'customer_name' => 'Ravi', 'customer_email' => 'r@example.com',
                'customer_phone' => '9811122233',
                'shipping' => ['state' => 'UP'],
            ])
            ->assertSessionHasErrors('shipping.state');
    }

    public function test_a_sent_quotation_cannot_be_deleted(): void
    {
        Mail::fake();

        $quotation = $this->quotations->send($this->priced());

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.quotations.destroy', $quotation))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('quotations', ['id' => $quotation->id, 'deleted_at' => null]);
    }

    public function test_quotations_need_their_permission(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('dashboard.view');

        $this->actingAs($outsider, 'admin')
            ->get(route('admin.quotations.index'))
            ->assertForbidden();
    }
}
