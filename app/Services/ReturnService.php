<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderReturn;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Returns, replacements and warranty claims.
 *
 * Money and stock only move on a deliberate act. Approving a return does not
 * refund anything and does not put the goods back on the shelf — somebody has
 * to receive the item, look at it, and say so. Too many shops refund on
 * approval and then find the box never came back.
 */
class ReturnService
{
    public function __construct(protected StockService $stock) {}

    /**
     * What is still available to send back, after anything already returned.
     *
     * @return Collection<int, array<string, mixed>> keyed by order item id
     */
    public function returnableItems(Order $order): Collection
    {
        $claimed = DB::table('order_return_items')
            ->join('order_returns', 'order_returns.id', '=', 'order_return_items.order_return_id')
            ->where('order_returns.order_id', $order->id)
            ->whereNull('order_returns.deleted_at')
            // A refused claim frees the units up again.
            ->where('order_returns.status', '!=', 'rejected')
            ->groupBy('order_return_items.order_item_id')
            // The sum needs a name of its own: pluck reads a column from the
            // result, and "SUM(...)" is not one.
            ->selectRaw('order_return_items.order_item_id, SUM(order_return_items.quantity) as claimed')
            ->pluck('claimed', 'order_item_id');

        return $order->items->mapWithKeys(function (OrderItem $item) use ($claimed) {
            $already = (int) ($claimed[$item->id] ?? 0);

            return [$item->id => [
                'item' => $item,
                'ordered' => $item->quantity,
                'claimed' => $already,
                'available' => max(0, $item->quantity - $already),
            ]];
        });
    }

    /**
     * Raise a request.
     *
     * @param  array<int, int>  $quantities  order item id => quantity
     * @param  array<string, mixed>  $details
     *
     * @throws ReturnNotPossible
     */
    public function request(Order $order, array $quantities, array $details): OrderReturn
    {
        if ($order->status !== 'delivered') {
            throw new ReturnNotPossible('Only a delivered order can be returned.');
        }

        $available = $this->returnableItems($order);
        $lines = [];

        foreach ($quantities as $itemId => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity <= 0) {
                continue;
            }

            $row = $available->get((int) $itemId);

            if (! $row) {
                throw new ReturnNotPossible('That item is not part of this order.');
            }

            if ($quantity > $row['available']) {
                throw new ReturnNotPossible(
                    $row['item']->name.': only '.$row['available'].' can still be returned.'
                );
            }

            $lines[(int) $itemId] = $quantity;
        }

        if ($lines === []) {
            throw new ReturnNotPossible('Choose at least one item to return.');
        }

        return DB::transaction(function () use ($order, $lines, $details, $available) {
            $return = $order->returns()->create([
                'customer_id' => $order->customer_id,
                'reference' => $this->nextReference($order),
                'type' => $details['type'] ?? 'return',
                'status' => 'requested',
                'reason' => $details['reason'],
                'details' => $details['details'] ?? null,
                'requested_at' => now(),
            ]);

            $refund = 0.0;

            foreach ($lines as $itemId => $quantity) {
                $row = $available->get($itemId);
                /** @var OrderItem $item */
                $item = $row['item'];

                // Refund the price actually paid for those units, not the
                // catalogue price: a discount applied at checkout must not
                // come back as full value.
                $perUnit = $item->quantity > 0
                    ? round((float) $item->line_total / $item->quantity, 2)
                    : 0.0;

                $lineRefund = round($perUnit * $quantity, 2);
                $refund += $lineRefund;

                $return->items()->create([
                    'order_item_id' => $itemId,
                    'quantity' => $quantity,
                    'refund_amount' => $lineRefund,
                ]);
            }

            // A replacement or a warranty repair is not a refund.
            $return->update([
                'refund_amount' => ($details['type'] ?? 'return') === 'return' ? round($refund, 2) : 0,
            ]);

            return $return->load('items');
        });
    }

    public function approve(OrderReturn $return, ?User $actor, ?string $message = null, ?float $refundAmount = null): OrderReturn
    {
        $return->update([
            'status' => 'approved',
            'customer_message' => $message,
            'refund_amount' => $refundAmount ?? $return->refund_amount,
            'resolved_by' => $actor?->id,
        ]);

        return $return;
    }

    /**
     * Collected from the customer, but not yet back at the warehouse.
     *
     * The step between approving and receiving: with furniture that gap can
     * be days, and "where is my sofa" is a question staff need an answer to.
     */
    public function markPickedUp(OrderReturn $return, ?User $actor, ?string $note = null): OrderReturn
    {
        $return->update([
            'status' => 'picked_up',
            'staff_note' => $note ?? $return->staff_note,
            'resolved_by' => $actor?->id,
        ]);

        return $return;
    }

    /**
     * Settled without money or goods moving — advice given, a part posted
     * out, the customer satisfied. Without this, such a claim would sit open
     * forever, or be falsely recorded as refunded.
     */
    public function close(OrderReturn $return, ?User $actor, ?string $message = null): OrderReturn
    {
        $return->update([
            'status' => 'closed',
            'customer_message' => $message ?? $return->customer_message,
            'resolved_by' => $actor?->id,
            'resolved_at' => now(),
        ]);

        return $return;
    }

    public function reject(OrderReturn $return, ?User $actor, string $message): OrderReturn
    {
        $return->update([
            'status' => 'rejected',
            'customer_message' => $message,
            'resolved_by' => $actor?->id,
            'resolved_at' => now(),
        ]);

        return $return;
    }

    /**
     * The goods are physically back. This is where stock may return to the
     * shelf — per line, because a damaged item comes back but is not sellable.
     *
     * @param  array<int, bool>  $restock  return item id => put back on the shelf
     */
    public function receive(OrderReturn $return, ?User $actor, array $restock = []): OrderReturn
    {
        return DB::transaction(function () use ($return, $actor, $restock) {
            foreach ($return->items as $line) {
                $putBack = (bool) ($restock[$line->id] ?? false);

                $line->update(['restock' => $putBack]);

                if ($putBack && $line->orderItem?->product_id) {
                    $this->stock->returnToShelf(
                        $line->orderItem->product_id,
                        $line->orderItem->product_variant_id,
                        $line->quantity,
                        referenceLabel: $return->reference,
                        referenceId: $return->id,
                    );
                }
            }

            $return->update([
                'status' => 'received',
                'resolved_by' => $actor?->id,
            ]);

            return $return->fresh('items');
        });
    }

    /**
     * Record that the money went back. The refund itself happens in Razorpay
     * or at the counter; this is the note that it did, with its reference.
     */
    public function refund(OrderReturn $return, ?User $actor, float $amount, string $method, ?string $reference = null): OrderReturn
    {
        $return->update([
            'status' => 'refunded',
            'refund_amount' => $amount,
            'refund_method' => $method,
            'refund_reference' => $reference,
            'resolved_by' => $actor?->id,
            'resolved_at' => now(),
        ]);

        $this->markOrderRefunded($return->order->fresh());

        return $return;
    }

    public function markReplaced(OrderReturn $return, ?User $actor, ?string $message = null): OrderReturn
    {
        $return->update([
            'status' => 'replaced',
            'customer_message' => $message ?? $return->customer_message,
            'resolved_by' => $actor?->id,
            'resolved_at' => now(),
        ]);

        return $return;
    }

    /**
     * An order whose whole value has been refunded is refunded; part of it
     * is a partial. Said in the order's own payment status so the lists and
     * the reports agree with the returns screen.
     */
    protected function markOrderRefunded(Order $order): void
    {
        $refunded = (float) $order->returns()
            ->where('status', 'refunded')
            ->sum('refund_amount');

        if ($refunded <= 0) {
            return;
        }

        $order->update([
            'payment_status' => $refunded >= (float) $order->amount_paid - 0.01 ? 'refunded' : 'partly_paid',
        ]);
    }

    protected function nextReference(Order $order): string
    {
        $count = OrderReturn::withTrashed()->where('order_id', $order->id)->count();

        return 'RET/'.Str::after($order->order_number, '/').'-'.($count + 1);
    }
}
