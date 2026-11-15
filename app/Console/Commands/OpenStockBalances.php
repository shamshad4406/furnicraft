<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockLedger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes an opening balance for stock that was on the shelf before the
 * ledger existed.
 *
 * Without this the ledger starts mid-story: the catalogue says forty of
 * something and the ledger explains none of it. Run once, after upgrading.
 *
 * Idempotent — a product that already has any ledger history is left alone,
 * so running it twice does not invent stock.
 */
class OpenStockBalances extends Command
{
    protected $signature = 'inventory:open-balances
                            {--dry-run : Show what would be written without writing it}';

    protected $description = 'Record opening stock balances for products with no ledger history';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Products that already have history are mid-story; leave them.
        $accounted = StockMovement::distinct()->pluck('product_id')->all();

        $products = Product::query()
            ->when($accounted !== [], fn ($q) => $q->whereNotIn('id', $accounted))
            ->with('variants:id,product_id,size,color,stock_quantity')
            ->get();

        if ($products->isEmpty()) {
            $this->info('Every product already has ledger history. Nothing to do.');

            return self::SUCCESS;
        }

        $rows = 0;
        $units = 0;

        foreach ($products as $product) {
            // With variants the count lives on each variant, so that is what
            // the opening balance has to describe.
            $holders = $product->variants->isNotEmpty()
                ? $product->variants->map(fn ($v) => ['variant' => $v->id, 'qty' => (int) $v->stock_quantity])
                : collect([['variant' => null, 'qty' => (int) $product->stock_quantity]]);

            foreach ($holders as $holder) {
                if ($holder['qty'] <= 0) {
                    continue;
                }

                $rows++;
                $units += $holder['qty'];

                if ($dryRun) {
                    continue;
                }

                // Written directly rather than through the ledger: the count
                // is already correct, and record() would double it.
                DB::table('stock_movements')->insert([
                    'product_id' => $product->id,
                    'product_variant_id' => $holder['variant'],
                    'type' => 'opening',
                    'quantity' => $holder['qty'],
                    'balance_after' => $holder['qty'],
                    'reason' => 'Opening balance, recorded when stock tracking began.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($rows === 0) {
            $this->info('No product has stock to open with.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? 'Would write ' : 'Wrote ').$rows.' opening balance(s), '.$units.' unit(s) in total.');

        if ($dryRun) {
            $this->line('Run it again without --dry-run to write them.');
        }

        // A nudge towards the figure the valuation report needs next.
        $unpriced = Product::whereNull('cost_price')->where('status', 'active')->count();

        if ($unpriced > 0 && ! $dryRun) {
            $this->warn($unpriced.' active product(s) have no cost price, so they count as nothing in the stock valuation.');
        }

        return self::SUCCESS;
    }
}
