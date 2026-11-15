<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockLedger;
use App\Services\StockLedgerRefused;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The stock ledger.
 *
 * Everything here protects one idea: the number on the screen and the lines
 * explaining it must never disagree. A count that moves with nothing to
 * account for it is exactly the problem this module exists to solve.
 */
class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected StockLedger $ledger;

    protected User $admin;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->ledger = app(StockLedger::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $category = Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Steel Almirah', 'slug' => 'steel-almirah', 'sku' => Str::upper(Str::random(8)),
            'price' => 11800, 'stock_quantity' => 20, 'gst_rate' => 18, 'status' => 'active',
        ]);
    }

    /* ---------------------------------------------------- recording */

    public function test_a_movement_changes_the_count_and_leaves_a_line(): void
    {
        $movement = $this->ledger->record(
            $this->product->id, null, 'purchase', 5,
            reason: 'Delivery from the workshop',
            actor: $this->admin,
        );

        $this->assertSame(25, (int) $this->product->fresh()->stock_quantity);
        $this->assertSame(5, $movement->quantity);
        $this->assertSame(25, $movement->balance_after);
        $this->assertSame($this->admin->name, $movement->actor);
    }

    /**
     * The balance is stored so the ledger reads like a bank statement —
     * you can point at a row without re-adding everything above it.
     */
    public function test_the_balance_is_recorded_at_each_step(): void
    {
        $this->ledger->record($this->product->id, null, 'sale', -3);
        $this->ledger->record($this->product->id, null, 'purchase', 10);
        $this->ledger->record($this->product->id, null, 'damage', -2);

        $balances = StockMovement::orderBy('id')->pluck('balance_after')->all();

        $this->assertSame([17, 27, 25], $balances);
        $this->assertSame(25, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_stock_cannot_be_driven_below_zero(): void
    {
        $this->expectException(StockLedgerRefused::class);
        $this->expectExceptionMessage('only 20');

        $this->ledger->record($this->product->id, null, 'sale', -25);
    }

    public function test_a_failed_movement_leaves_the_count_alone(): void
    {
        try {
            $this->ledger->record($this->product->id, null, 'sale', -99);
        } catch (StockLedgerRefused) {
            // expected
        }

        $this->assertSame(20, (int) $this->product->fresh()->stock_quantity);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_movement_of_zero_is_refused(): void
    {
        $this->expectException(StockLedgerRefused::class);

        $this->ledger->record($this->product->id, null, 'adjustment', 0);
    }

    public function test_an_unknown_kind_of_movement_is_refused(): void
    {
        $this->expectException(StockLedgerRefused::class);
        $this->expectExceptionMessage('not a kind of stock movement');

        $this->ledger->record($this->product->id, null, 'teleported', 5);
    }

    /**
     * With variants the count lives on the variant, not the product.
     */
    public function test_a_variant_keeps_its_own_count(): void
    {
        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SA-RED', 'color' => 'Red', 'stock_quantity' => 8, 'is_active' => true,
        ]);

        $this->ledger->record($this->product->id, $variant->id, 'sale', -3);

        $this->assertSame(5, (int) $variant->fresh()->stock_quantity);
        // The product's own figure is untouched.
        $this->assertSame(20, (int) $this->product->fresh()->stock_quantity);
    }

    /* ------------------------------------------------------ counting */

    /**
     * A stock count produces a difference, which is the useful fact: "we
     * found three fewer than the system thought".
     */
    public function test_a_count_records_the_difference_not_the_total(): void
    {
        $movement = $this->ledger->countTo($this->product->id, null, 17, 'Monthly count');

        $this->assertSame(-3, $movement->quantity);
        $this->assertSame(17, $movement->balance_after);
        $this->assertSame(17, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_a_count_that_matches_records_nothing(): void
    {
        $this->assertNull($this->ledger->countTo($this->product->id, null, 20, 'Monthly count'));
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_a_negative_count_is_refused(): void
    {
        $this->expectException(StockLedgerRefused::class);

        $this->ledger->countTo($this->product->id, null, -1, 'Nonsense');
    }

    /* ------------------------------------------------- the screens */

    public function test_staff_can_record_a_count(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.inventory.adjust', $this->product), [
                'mode' => 'count',
                'counted_quantity' => 18,
                'type' => 'adjustment',
                'reason' => 'Monthly count — two missing.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(18, (int) $this->product->fresh()->stock_quantity);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'quantity' => -2,
            'reason' => 'Monthly count — two missing.',
        ]);
    }

    /**
     * A number that changed with no explanation is the whole problem.
     */
    public function test_an_adjustment_without_a_reason_is_refused(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.inventory.adjust', $this->product), [
                'mode' => 'count',
                'counted_quantity' => 18,
                'type' => 'adjustment',
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(20, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_an_impossible_adjustment_is_reported_not_crashed(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.inventory.adjust', $this->product), [
                'mode' => 'change',
                'quantity' => -500,
                'type' => 'damage',
                'reason' => 'Fire in the warehouse',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(20, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_the_inventory_screens_render(): void
    {
        $this->ledger->record($this->product->id, null, 'adjustment', -2, 'Count');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.inventory.index'))->assertOk()->assertSee('Steel Almirah');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.inventory.show', $this->product))->assertOk()->assertSee('Count');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.inventory.movements'))->assertOk()->assertSee('Steel Almirah');
    }

    public function test_the_stock_export_is_a_csv(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.inventory.export'))
            ->assertOk();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('sku,product,category', $csv);
        $this->assertStringContainsString('Steel Almirah', $csv);
    }

    public function test_inventory_needs_its_permission(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('dashboard.view');

        $this->actingAs($outsider, 'admin')
            ->get(route('admin.inventory.index'))
            ->assertForbidden();
    }

    /* ------------------------------------------------- low stock */

    public function test_low_stock_uses_the_products_own_level_before_the_default(): void
    {
        // The shop default is 5, so 20 is comfortable...
        $this->assertFalse($this->product->fresh()->isLowStock());

        // ...until this product says it wants 30 on the shelf.
        $this->product->update(['low_stock_threshold' => 30]);

        $this->assertTrue($this->product->fresh()->isLowStock());
        $this->assertSame(30, $this->product->fresh()->effective_low_stock_threshold);
    }

    public function test_the_low_stock_list_sums_variants(): void
    {
        $this->product->update(['stock_quantity' => 0, 'low_stock_threshold' => 10]);

        ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SA-A', 'color' => 'Red', 'stock_quantity' => 4, 'is_active' => true,
        ]);
        ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'SA-B', 'color' => 'Blue', 'stock_quantity' => 3, 'is_active' => true,
        ]);

        // 7 across the variants, below the threshold of 10.
        $this->assertTrue(Product::lowStock()->pluck('id')->contains($this->product->id));

        ProductVariant::where('product_id', $this->product->id)->update(['stock_quantity' => 20]);

        $this->assertFalse(Product::lowStock()->pluck('id')->contains($this->product->id));
    }

    /* -------------------------------------------- the opening command */

    public function test_the_opening_balance_command_writes_history_once(): void
    {
        $this->artisan('inventory:open-balances')->assertSuccessful();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => 'opening',
            'quantity' => 20,
            'balance_after' => 20,
        ]);

        // Running it again must not invent stock.
        $this->artisan('inventory:open-balances')->assertSuccessful();

        $this->assertSame(1, StockMovement::where('type', 'opening')->count());
        $this->assertSame(20, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_the_dry_run_writes_nothing(): void
    {
        $this->artisan('inventory:open-balances --dry-run')->assertSuccessful();

        $this->assertDatabaseCount('stock_movements', 0);
    }
}
