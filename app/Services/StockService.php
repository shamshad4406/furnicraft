<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockReservation;
use Illuminate\Support\Facades\DB;

/**
 * Stock availability and short-lived reservations.
 *
 * While a customer is paying, their stock is held so a second customer cannot
 * buy the last unit underneath them. A hold expires on its own, so an
 * abandoned checkout never locks stock permanently.
 *
 * Phase 7 replaces the raw quantity columns with the inventory module; the
 * reservation logic stays.
 */
class StockService
{
    /** How long a checkout holds stock. The plan specifies 30 minutes. */
    public const HOLD_MINUTES = 30;

    /**
     * Holds are this service's own business, but anything that actually
     * moves the count goes through the ledger, so every change has a reason
     * attached to it.
     */
    public function __construct(protected StockLedger $ledger) {}

    /**
     * Units a customer can actually buy right now: what the catalog says,
     * minus everything currently held by other checkouts.
     */
    public function available(Product $product, ?ProductVariant $variant = null, ?int $ignoreOrderId = null): int
    {
        $onHand = (int) ($variant?->stock_quantity ?? $product->stock_quantity);

        $held = StockReservation::query()
            ->holding()
            ->where('product_id', $product->id)
            ->when($variant, fn ($q) => $q->where('product_variant_id', $variant->id))
            ->when(! $variant, fn ($q) => $q->whereNull('product_variant_id'))
            ->when($ignoreOrderId, fn ($q) => $q->where('order_id', '!=', $ignoreOrderId))
            ->sum('quantity');

        return max(0, $onHand - (int) $held);
    }

    /**
     * Hold stock for an order. Rows are locked for the check so two
     * simultaneous checkouts cannot both pass it.
     *
     * @return array<int, string> problems; empty means every line was held
     */
    public function reserveForOrder(Order $order, iterable $lines): array
    {
        $problems = [];

        DB::transaction(function () use ($order, $lines, &$problems) {
            $expires = now()->addMinutes(self::HOLD_MINUTES);

            foreach ($lines as $line) {
                /** @var Product|null $product */
                $product = Product::query()->whereKey($line['product_id'])->lockForUpdate()->first();

                if (! $product) {
                    $problems[] = 'A product in your cart is no longer available.';
                    continue;
                }

                $variant = $line['product_variant_id']
                    ? ProductVariant::query()->whereKey($line['product_variant_id'])->lockForUpdate()->first()
                    : null;

                $available = $this->available($product, $variant, $order->id);

                if ($available < $line['quantity']) {
                    $problems[] = $available > 0
                        ? sprintf('Only %d left of %s.', $available, $product->name)
                        : sprintf('%s has just gone out of stock.', $product->name);

                    continue;
                }

                StockReservation::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'quantity' => $line['quantity'],
                    'expires_at' => $expires,
                    'status' => 'held',
                ]);
            }

            if ($problems !== []) {
                // Nothing is held unless the whole order can be held.
                throw new StockUnavailable($problems);
            }
        });

        return $problems;
    }

    /**
     * Payment succeeded: take the held units out of stock for good.
     *
     * Through the ledger, so the sale leaves a line somebody can read later
     * rather than a number that silently moved.
     */
    public function commit(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $reservations = StockReservation::where('order_id', $order->id)
                ->where('status', 'held')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $this->ledger->record(
                    $reservation->product_id,
                    $reservation->product_variant_id,
                    'sale',
                    -$reservation->quantity,
                    referenceType: 'order',
                    referenceId: $order->id,
                    referenceLabel: $order->order_number,
                );

                $reservation->update(['status' => 'committed']);
            }
        });
    }

    /**
     * Goods came back and are fit to sell again.
     *
     * Deliberately separate from release(): a reservation that is released
     * was never taken out of stock, while this is adding units back that
     * were. Only ever called from the returns screen, once somebody has
     * actually looked at what arrived.
     */
    public function returnToShelf(
        int $productId,
        ?int $variantId,
        int $quantity,
        ?string $referenceLabel = null,
        ?int $referenceId = null,
    ): void {
        if ($quantity <= 0) {
            return;
        }

        $this->ledger->record(
            $productId,
            $variantId,
            'return',
            $quantity,
            referenceType: $referenceId ? 'return' : null,
            referenceId: $referenceId,
            referenceLabel: $referenceLabel,
        );
    }

    /**
     * Payment failed, the order was cancelled, or the hold timed out.
     */
    public function release(Order $order): void
    {
        StockReservation::where('order_id', $order->id)
            ->where('status', 'held')
            ->update(['status' => 'released', 'released_at' => now()]);
    }

    /**
     * Sweep expired holds. Called by the scheduler.
     */
    public function releaseExpired(): int
    {
        return StockReservation::query()
            ->expired()
            ->update(['status' => 'released', 'released_at' => now()]);
    }
}
