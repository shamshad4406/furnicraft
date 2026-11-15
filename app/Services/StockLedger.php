<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The single door through which stock changes.
 *
 * Nothing else should write `stock_quantity`. Every movement is recorded
 * with what caused it, so when the shelf and the screen disagree there is a
 * trail to read rather than a guess to make.
 *
 * The count and the ledger row are written in one transaction with the row
 * locked, so two people receiving the same delivery cannot both read the old
 * balance and leave the ledger saying something the stock does not.
 */
class StockLedger
{
    public const TYPES = [
        'opening' => 'Opening balance',
        'sale' => 'Sold',
        'return' => 'Returned to stock',
        'purchase' => 'Received from supplier',
        'adjustment' => 'Stock count adjustment',
        'damage' => 'Damaged or written off',
        'correction' => 'Correction',
    ];

    /** Types that mean stock left the shelf, for the reports. */
    public const OUTWARD = ['sale', 'damage'];

    /**
     * Record a movement and move the count with it.
     *
     * @param  int  $quantity  signed: negative leaves the shelf
     *
     * @throws StockLedgerRefused
     */
    public function record(
        int $productId,
        ?int $variantId,
        string $type,
        int $quantity,
        ?string $reason = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceLabel = null,
        ?float $unitCost = null,
        ?User $actor = null,
    ): StockMovement {
        if ($quantity === 0) {
            throw new StockLedgerRefused('A movement of zero would say nothing.');
        }

        if (! array_key_exists($type, self::TYPES)) {
            throw new StockLedgerRefused('"'.$type.'" is not a kind of stock movement.');
        }

        $actor ??= Auth::guard('admin')->user();

        return DB::transaction(function () use (
            $productId, $variantId, $type, $quantity, $reason,
            $referenceType, $referenceId, $referenceLabel, $unitCost, $actor
        ) {
            // Lock whichever row actually holds the count.
            $holder = $variantId
                ? ProductVariant::whereKey($variantId)->lockForUpdate()->first()
                : Product::whereKey($productId)->lockForUpdate()->first();

            if (! $holder) {
                throw new StockLedgerRefused('That product is no longer in the catalogue.');
            }

            $before = (int) $holder->stock_quantity;
            $after = $before + $quantity;

            if ($after < 0) {
                throw new StockLedgerRefused(
                    'That would leave '.$after.' in stock. There are only '.$before.'.'
                );
            }

            $holder->forceFill(['stock_quantity' => $after])->save();

            return StockMovement::create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'type' => $type,
                'quantity' => $quantity,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_label' => $referenceLabel,
                'unit_cost' => $unitCost,
                'reason' => $reason,
                'user_id' => $actor?->id,
                'user_name' => $actor?->name,
            ]);
        });
    }

    /**
     * Set the count to a figure somebody has physically counted.
     *
     * The ledger records the difference, not the new total: "we found three
     * fewer than the system thought" is the useful fact, and it is what a
     * stock count actually produces.
     *
     * @throws StockLedgerRefused
     */
    public function countTo(
        int $productId,
        ?int $variantId,
        int $countedQuantity,
        string $reason,
        string $type = 'adjustment',
        ?User $actor = null,
    ): ?StockMovement {
        if ($countedQuantity < 0) {
            throw new StockLedgerRefused('A stock count cannot be negative.');
        }

        $current = $variantId
            ? (int) ProductVariant::whereKey($variantId)->value('stock_quantity')
            : (int) Product::whereKey($productId)->value('stock_quantity');

        $difference = $countedQuantity - $current;

        // Counting and finding exactly what you expected is not a movement.
        if ($difference === 0) {
            return null;
        }

        return $this->record(
            $productId,
            $variantId,
            $type,
            $difference,
            $reason,
            actor: $actor,
        );
    }

    /**
     * What a product is worth at cost, for the valuation report.
     */
    public function valuationFor(Product $product): float
    {
        $cost = (float) ($product->cost_price ?? 0);

        if ($cost <= 0) {
            return 0.0;
        }

        return round($cost * $product->total_stock, 2);
    }
}
