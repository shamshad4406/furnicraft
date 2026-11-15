<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consignments against an order.
 *
 * The rule that shapes everything here: an order line can be split across
 * several consignments, but the totals must always add up to what was
 * ordered. Shipping five of a quantity of three is not a typo to be corrected
 * later — it is a van going to the wrong place.
 */
class ShipmentService
{
    public function __construct(protected OrderWorkflow $workflow) {}

    /**
     * How many of each order line are still waiting to be sent.
     *
     * @return Collection<int, array<string, mixed>> keyed by order item id
     */
    public function pendingItems(Order $order): Collection
    {
        $shipped = DB::table('shipment_items')
            ->join('shipments', 'shipments.id', '=', 'shipment_items.shipment_id')
            ->where('shipments.order_id', $order->id)
            ->whereNull('shipments.deleted_at')
            // A consignment that came back has not been delivered, so its
            // units are owed to the customer again.
            ->where('shipments.status', '!=', 'returned')
            ->groupBy('shipment_items.order_item_id')
            // The sum needs a name of its own: pluck reads a column from the
            // result, and "SUM(...)" is not one.
            ->selectRaw('shipment_items.order_item_id, SUM(shipment_items.quantity) as sent')
            ->pluck('sent', 'order_item_id');

        return $order->items->mapWithKeys(function (OrderItem $item) use ($shipped) {
            $already = (int) ($shipped[$item->id] ?? 0);

            return [$item->id => [
                'item' => $item,
                'ordered' => $item->quantity,
                'shipped' => $already,
                'pending' => max(0, $item->quantity - $already),
            ]];
        });
    }

    public function hasUnshippedItems(Order $order): bool
    {
        return $this->pendingItems($order)->contains(fn (array $row) => $row['pending'] > 0);
    }

    /**
     * Create a consignment for the given lines.
     *
     * @param  array<int, int>  $quantities  order item id => quantity
     * @param  array<string, mixed>  $details
     *
     * @throws ShipmentNotPossible
     */
    public function create(Order $order, array $quantities, array $details = []): Shipment
    {
        $pending = $this->pendingItems($order);
        $lines = [];

        foreach ($quantities as $itemId => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity <= 0) {
                continue;
            }

            $row = $pending->get((int) $itemId);

            if (! $row) {
                throw new ShipmentNotPossible('That item is not part of this order.');
            }

            if ($quantity > $row['pending']) {
                throw new ShipmentNotPossible(
                    $row['item']->name.': only '.$row['pending'].' left to send, but '.$quantity.' was entered.'
                );
            }

            $lines[(int) $itemId] = $quantity;
        }

        if ($lines === []) {
            throw new ShipmentNotPossible('Choose at least one item to send.');
        }

        return DB::transaction(function () use ($order, $lines, $details) {
            $shipment = $order->shipments()->create([
                'consignment_number' => $this->nextConsignmentNumber($order),
                'courier' => $details['courier'] ?? null,
                'tracking_number' => $details['tracking_number'] ?? null,
                'tracking_url' => $details['tracking_url'] ?? null,
                'status' => $details['status'] ?? 'pending',
                'packages' => $details['packages'] ?? 1,
                'weight' => $details['weight'] ?? null,
                'assigned_to' => $details['assigned_to'] ?? null,
                'expected_delivery_on' => $details['expected_delivery_on'] ?? null,
                'notes' => $details['notes'] ?? null,
                'dispatched_at' => ($details['status'] ?? 'pending') !== 'pending' ? now() : null,
            ]);

            foreach ($lines as $itemId => $quantity) {
                $shipment->items()->create([
                    'order_item_id' => $itemId,
                    'quantity' => $quantity,
                ]);
            }

            return $shipment->load('items');
        });
    }

    /**
     * Move a consignment on, and keep the order's own status in step.
     *
     * The order follows the consignments rather than the other way round:
     * staff work on the van-load in front of them, and the order status is a
     * summary of those.
     */
    public function updateStatus(Shipment $shipment, string $status, ?User $actor = null, ?string $note = null): Shipment
    {
        $shipment->fill([
            'status' => $status,
            'dispatched_at' => $shipment->dispatched_at ?? ($status === 'pending' ? null : now()),
            'delivered_at' => $status === 'delivered' ? now() : null,
        ]);

        if ($note) {
            $shipment->notes = $note;
        }

        $shipment->save();

        $this->syncOrderStatus($shipment->order->fresh(), $actor);

        return $shipment;
    }

    /**
     * Nudge the order to match its consignments, where the workflow allows.
     *
     * Deliberately quiet about failure: this is a convenience, and an order
     * the workflow will not move (an unpaid online order, say) simply stays
     * where it is for someone to deal with by hand.
     */
    public function syncOrderStatus(Order $order, ?User $actor = null): void
    {
        $shipments = $order->shipments()->get();

        if ($shipments->isEmpty()) {
            return;
        }

        $target = match (true) {
            // Everything sent and everything arrived.
            $shipments->every(fn (Shipment $s) => $s->status === 'delivered')
                && ! $this->hasUnshippedItems($order) => 'delivered',

            // Something has left the building.
            $shipments->contains(fn (Shipment $s) => in_array($s->status, ['dispatched', 'in_transit', 'out_for_delivery', 'delivered'], true)) => 'shipped',

            default => null,
        };

        if ($target === null || $order->status === $target) {
            return;
        }

        try {
            $this->workflow->moveTo(
                $order,
                $target,
                $target === 'delivered'
                    ? 'All consignments delivered.'
                    : 'Consignment dispatched.',
                $actor,
                notifyCustomer: false,
            );
        } catch (InvalidStatusTransition) {
            // The workflow said no; the consignment is still recorded, and
            // the order is left for someone to move deliberately.
        }
    }

    /**
     * FC/2611/00001-1, -2, … so a consignment is readable next to its order.
     */
    protected function nextConsignmentNumber(Order $order): string
    {
        $count = $order->shipments()->withTrashed()->count();

        return $order->order_number.'-'.($count + 1);
    }
}
