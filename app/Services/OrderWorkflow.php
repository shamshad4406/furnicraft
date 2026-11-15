<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The rules about how an order moves from one status to the next.
 *
 * Kept in one place because the same question gets asked from the admin
 * screens, the customer's cancel button, the payment callbacks and the
 * shipment screens — and a different answer in any of them is a bug. A
 * delivered order that can be sent back to "pending" is a refund waiting to
 * happen.
 */
class OrderWorkflow
{
    /**
     * Where each status may go next.
     *
     * Deliberately one-way apart from the corrections staff genuinely need:
     * "shipped" back to "processing" for a dispatch logged by mistake, and
     * "delivered" to "returned" for goods that come back.
     *
     * @var array<string, string[]>
     */
    public const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['processing', 'shipped', 'cancelled'],
        'processing' => ['shipped', 'confirmed', 'cancelled'],
        'shipped' => ['delivered', 'processing', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ];

    /** Statuses after which an order is finished with. */
    public const FINAL = ['cancelled', 'returned'];

    /** Reaching these means the goods are with the customer. */
    public const FULFILLED = ['delivered'];

    public function __construct(
        protected StockService $stock,
        protected CouponService $coupons,
    ) {}

    /**
     * @return string[] the statuses this order may move to right now
     */
    public function allowedNext(Order $order): array
    {
        $next = self::TRANSITIONS[$order->status] ?? [];

        // An unpaid online order should not be marked delivered: either the
        // payment arrives, or it is cancelled. COD is the whole point of
        // paying later, so it is exempt.
        if ($order->payment_method === 'razorpay' && ! $order->isPaid()) {
            $next = array_values(array_diff($next, ['delivered']));
        }

        return $next;
    }

    public function canMoveTo(Order $order, string $status): bool
    {
        return in_array($status, $this->allowedNext($order), true);
    }

    /**
     * Move the order, record who did it and why, and deal with the side
     * effects that go with each status.
     *
     * @throws InvalidStatusTransition
     */
    public function moveTo(
        Order $order,
        string $status,
        ?string $note = null,
        ?User $actor = null,
        bool $notifyCustomer = true,
    ): OrderStatusHistory {
        if ($order->status === $status) {
            throw new InvalidStatusTransition(
                'This order is already '.($order->statusLabel ?? $status).'.'
            );
        }

        if (! $this->canMoveTo($order, $status)) {
            throw new InvalidStatusTransition($this->refusal($order, $status));
        }

        $actor ??= Auth::guard('admin')->user();
        $from = $order->status;

        return DB::transaction(function () use ($order, $status, $from, $note, $actor, $notifyCustomer) {
            $order->fill(['status' => $status] + $this->timestampsFor($status, $order));

            $this->applySideEffects($order, $status, $note, $actor);

            $order->save();

            return OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => $status,
                'note' => $note,
                'changed_by' => $actor?->id,
                'changed_by_name' => $actor?->name,
                'customer_notified' => $notifyCustomer && $this->tellsCustomer($status),
            ]);
        });
    }

    /**
     * The first entry, written when the order is placed, so the history is
     * complete rather than starting at the first staff action.
     */
    public function recordPlacement(Order $order): OrderStatusHistory
    {
        return OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => $order->status,
            'note' => 'Order placed by the customer.',
            'customer_notified' => true,
        ]);
    }

    /**
     * Statuses the customer hears about. "Processing" is deliberately quiet:
     * it means work started, which is not news worth an email.
     */
    public function tellsCustomer(string $status): bool
    {
        return in_array($status, ['confirmed', 'shipped', 'delivered', 'cancelled'], true);
    }

    /**
     * @return array<string, mixed>
     */
    protected function timestampsFor(string $status, Order $order): array
    {
        return match ($status) {
            'confirmed' => ['confirmed_at' => $order->confirmed_at ?? now()],
            'delivered' => ['delivered_at' => now()],
            'cancelled' => ['cancelled_at' => now()],
            default => [],
        };
    }

    /**
     * Stock and coupons follow the status; money never does. A refund is a
     * deliberate act on the returns screen, not a side effect of a dropdown.
     */
    protected function applySideEffects(Order $order, string $status, ?string $note, ?User $actor): void
    {
        if ($status === 'cancelled') {
            $order->cancellation_reason = $note;
            $order->cancelled_by = $actor?->id;

            $this->stock->release($order);
            $this->coupons->releaseUsage($order->id);

            return;
        }

        if ($status === 'returned') {
            // The goods are back with us; the coupon the customer used is
            // theirs again. Putting stock back is a decision for the returns
            // screen, where someone has actually looked at what came back.
            $this->coupons->releaseUsage($order->id);
        }
    }

    protected function refusal(Order $order, string $status): string
    {
        $label = Order::STATUSES[$status] ?? $status;
        $current = Order::STATUSES[$order->status] ?? $order->status;

        if (in_array($order->status, self::FINAL, true)) {
            return 'This order is '.mb_strtolower($current).', so it cannot be changed any further.';
        }

        if ($status === 'delivered' && $order->payment_method === 'razorpay' && ! $order->isPaid()) {
            return 'This order has not been paid for, so it cannot be marked delivered. Record the payment first, or cancel the order.';
        }

        return 'An order that is '.mb_strtolower($current).' cannot be marked '.mb_strtolower($label).'.';
    }
}
