<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Buying stock in, and booking it onto the shelf when it arrives.
 *
 * Receiving is the only operation here that matters much: it is where a
 * number on a delivery note becomes a number in the catalogue, and it goes
 * through the stock ledger so there is a line explaining every unit.
 *
 * Costs are entered inclusive of GST, the same way selling prices are, so
 * nobody has to remember which figures on this screen include tax.
 */
class PurchaseService
{
    public function __construct(
        protected OrderNumberService $numbers,
        protected StockLedger $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Supplier $supplier, array $data, ?User $actor = null): PurchaseOrder
    {
        return PurchaseOrder::create($data + [
            'number' => $this->nextNumber(),
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'created_by' => $actor?->id,
        ]);
    }

    /**
     * Replace the lines and re-total.
     *
     * @param  array<int, array<string, mixed>>  $lines
     *
     * @throws PurchaseNotPossible
     */
    public function setItems(PurchaseOrder $order, array $lines): PurchaseOrder
    {
        if (! $order->isEditable()) {
            throw new PurchaseNotPossible('Only a draft purchase order can be changed.');
        }

        return DB::transaction(function () use ($order, $lines) {
            $order->items()->delete();

            foreach (array_values($lines) as $index => $line) {
                $quantity = max(1, (int) ($line['quantity'] ?? 1));
                $cost = round((float) ($line['unit_cost'] ?? 0), 2);
                $rate = (float) ($line['gst_rate'] ?? 0);
                $total = round($cost * $quantity, 2);

                $order->items()->create([
                    'product_id' => $line['product_id'] ?? null,
                    'product_variant_id' => $line['product_variant_id'] ?? null,
                    'name' => $line['name'],
                    'sku' => $line['sku'] ?? null,
                    'quantity' => $quantity,
                    'received_quantity' => 0,
                    'unit_cost' => $cost,
                    'gst_rate' => $rate,
                    // Costs include GST, so the tax is inside the figure.
                    'tax_amount' => $rate > 0 ? round($total - ($total / (1 + $rate / 100)), 2) : 0,
                    'line_total' => $total,
                    'sort_order' => $index,
                ]);
            }

            return $this->retotal($order->fresh('items'));
        });
    }

    public function retotal(PurchaseOrder $order): PurchaseOrder
    {
        $items = $order->items;

        $subtotal = round((float) $items->sum('line_total'), 2);
        $tax = round((float) $items->sum('tax_amount'), 2);
        $other = round((float) $order->other_charges, 2);

        $order->update([
            'subtotal' => $subtotal,
            'tax_total' => $tax,
            'grand_total' => round($subtotal + $other, 2),
        ]);

        return $order->fresh('items');
    }

    /**
     * Send it to the supplier. After this the lines are fixed.
     *
     * @throws PurchaseNotPossible
     */
    public function place(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->status !== 'draft') {
            throw new PurchaseNotPossible('This purchase order has already been placed.');
        }

        if ($order->items()->count() === 0) {
            throw new PurchaseNotPossible('Add at least one line before placing this order.');
        }

        $order->update(['status' => 'ordered', 'ordered_at' => now()]);

        return $order->fresh('items');
    }

    /**
     * Book a delivery in.
     *
     * @param  array<int, int>  $quantities  purchase order item id => quantity arriving now
     *
     * @throws PurchaseNotPossible
     */
    public function receive(
        PurchaseOrder $order,
        array $quantities,
        ?string $supplierInvoice = null,
        ?User $actor = null,
    ): PurchaseOrder {
        if (! $order->isReceivable()) {
            throw new PurchaseNotPossible('Only a placed purchase order can be received against.');
        }

        $items = $order->items->keyBy('id');
        $booking = [];

        foreach ($quantities as $itemId => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity <= 0) {
                continue;
            }

            /** @var PurchaseOrderItem|null $item */
            $item = $items->get((int) $itemId);

            if (! $item) {
                throw new PurchaseNotPossible('That line is not part of this purchase order.');
            }

            if ($quantity > $item->outstanding) {
                throw new PurchaseNotPossible(
                    $item->name.': only '.$item->outstanding.' still to come, but '.$quantity.' was entered.'
                );
            }

            // A line with no product behind it cannot go onto a shelf —
            // there is nothing in the catalogue to add it to.
            if (! $item->product_id) {
                throw new PurchaseNotPossible(
                    $item->name.' is not linked to a catalogue product, so it cannot be booked into stock.'
                );
            }

            $booking[$item->id] = $quantity;
        }

        if ($booking === []) {
            throw new PurchaseNotPossible('Enter how many of something arrived.');
        }

        return DB::transaction(function () use ($order, $items, $booking, $supplierInvoice, $actor) {
            foreach ($booking as $itemId => $quantity) {
                /** @var PurchaseOrderItem $item */
                $item = $items->get($itemId);

                $this->ledger->record(
                    $item->product_id,
                    $item->product_variant_id,
                    'purchase',
                    $quantity,
                    reason: 'Received against '.$order->number,
                    referenceType: 'purchase_order',
                    referenceId: $order->id,
                    referenceLabel: $order->number,
                    unitCost: (float) $item->unit_cost,
                    actor: $actor,
                );

                $item->increment('received_quantity', $quantity);

                // Keep the catalogue's cost price current, so the valuation
                // report reflects what we actually last paid.
                if ($item->product && $item->unit_cost > 0) {
                    $item->product->forceFill(['cost_price' => $item->unit_cost])->save();
                }
            }

            $order->refresh()->load('items');

            $complete = $order->items->every(fn (PurchaseOrderItem $item) => $item->isFullyReceived());

            $order->update([
                'status' => $complete ? 'received' : 'partial',
                'received_at' => $complete ? now() : $order->received_at,
                'supplier_invoice_number' => $supplierInvoice ?: $order->supplier_invoice_number,
            ]);

            return $order->fresh('items');
        });
    }

    /**
     * @throws PurchaseNotPossible
     */
    public function cancel(PurchaseOrder $order, ?string $reason = null): PurchaseOrder
    {
        if ($order->status === 'received') {
            throw new PurchaseNotPossible('This order has been received in full, so it cannot be cancelled.');
        }

        if ($order->items->sum('received_quantity') > 0) {
            throw new PurchaseNotPossible(
                'Some of this order has already arrived. Cancelling it would leave that stock unexplained.'
            );
        }

        $order->update([
            'status' => 'cancelled',
            'cancel_reason' => $reason,
        ]);

        return $order->fresh();
    }

    /**
     * PO/2611/00001 — its own series, like orders and invoices.
     */
    protected function nextNumber(): string
    {
        $prefix = (string) settings('purchase_prefix', 'PO');
        $padding = max(1, (int) settings('order_sequence_padding', 5));

        $sequence = (int) settings('purchase_next_sequence', 1);

        \App\Models\Setting::updateOrCreate(
            ['key' => 'purchase_next_sequence'],
            ['value' => (string) ($sequence + 1), 'group' => 'order', 'type' => 'number'],
        );

        app(SettingService::class)->flush();

        return $prefix.'/'.now()->format('ym').'/'.str_pad((string) $sequence, $padding, '0', STR_PAD_LEFT);
    }
}
