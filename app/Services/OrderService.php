<?php

namespace App\Services;

use App\Mail\OrderConfirmation;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Places orders and records what happens to their payments.
 *
 * Two rules run through the whole class:
 *   1. An order is only ever marked paid from a verified gateway signature.
 *   2. The same checkout attempt can be submitted twice and still produce one
 *      order — the idempotency key makes the second attempt a lookup.
 */
class OrderService
{
    public function __construct(
        protected PricingService $pricing,
        protected StockService $stock,
        protected CouponService $coupons,
        protected OrderNumberService $numbers,
    ) {}

    /**
     * Create (or re-fetch) the pending order for a checkout attempt, and hold
     * the stock for it.
     *
     * @param  array<string, mixed>  $checkout
     *
     * @throws StockUnavailable
     */
    public function place(Cart $cart, array $checkout, string $idempotencyKey): Order
    {
        // A refresh or double-click lands here with the same key.
        $existing = Order::where('idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $existing;
        }

        $totals = $this->pricing->totals($cart, $checkout['shipping_address']['pincode'], $checkout['customer_email']);

        if ($totals->isEmpty()) {
            throw new StockUnavailable(['Your cart is empty.']);
        }

        return DB::transaction(function () use ($cart, $checkout, $idempotencyKey, $totals) {
            // Re-check inside the transaction: another request may have taken
            // the key between the lookup above and here.
            $existing = Order::where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();

            if ($existing) {
                return $existing;
            }

            $codAdvance = $checkout['payment_method'] === 'cod'
                ? $this->pricing->codAdvance($totals->grandTotal)
                : 0.0;

            $order = Order::create([
                'order_number' => $this->numbers->next(),
                'idempotency_key' => $idempotencyKey,
                'customer_id' => $cart->customer_id,

                'customer_name' => $checkout['customer_name'],
                'customer_email' => $checkout['customer_email'],
                'customer_phone' => $checkout['customer_phone'],
                'gst_number' => $checkout['gst_number'] ?? null,

                'shipping_address' => $checkout['shipping_address'],
                'billing_address' => $checkout['billing_address'],
                'shipping_pincode' => $checkout['shipping_address']['pincode'],

                'subtotal' => $totals->subtotal,
                'discount' => $totals->discount,
                'delivery_charge' => $totals->deliveryCharge,
                'tax_total' => $totals->taxTotal,
                'grand_total' => $totals->grandTotal,
                'amount_paid' => 0,
                'amount_due' => $totals->grandTotal,

                'coupon_id' => $cart->coupon_id,
                'coupon_code' => $totals->couponCode,

                'payment_method' => $checkout['payment_method'],
                'payment_status' => 'pending',
                'status' => 'pending',

                'delivery_days' => $totals->deliveryDays,
                'notes' => $checkout['notes'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($totals->lines as $line) {
                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'product_variant_id' => $line['variant']?->id,
                    'name' => $line['name'],
                    'sku' => $line['sku'],
                    'variant_label' => $line['variant_label'],
                    'hsn_code' => $line['hsn_code'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_subtotal' => $line['line_subtotal'],
                    'gst_rate' => $line['gst_rate'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'weight' => $line['weight'],
                ]);
            }

            // Throws if any line cannot be held; the transaction rolls back
            // and no order survives.
            $this->stock->reserveForOrder($order, $totals->lines);

            if ($cart->coupon) {
                $this->coupons->recordUsage(
                    $cart->coupon,
                    $totals->discount,
                    $cart->customer_id,
                    $checkout['customer_email'],
                    $order->id,
                );
            }

            if ($codAdvance > 0) {
                $order->update(['notes' => trim(($order->notes ?? '').' [COD advance required: '.money($codAdvance).']')]);
            }

            // Open the history here, so it reads from the customer's click
            // rather than from whatever staff did first.
            app(OrderWorkflow::class)->recordPlacement($order);

            return $order->fresh('items');
        });
    }

    /**
     * How much has to be paid online right now. Full amount for Razorpay; for
     * COD either nothing, or the advance the admin requires on large orders.
     */
    public function amountPayableNow(Order $order): float
    {
        if ($order->payment_method === 'razorpay') {
            return (float) $order->grand_total;
        }

        return $this->pricing->codAdvance((float) $order->grand_total);
    }

    /**
     * Record a verified payment and move the order forward.
     *
     * Safe to call twice with the same payment id: the unique index on
     * gateway_payment_id means a replayed callback or a duplicate webhook
     * updates the same row instead of adding money twice.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordPayment(
        Order $order,
        string $gatewayOrderId,
        string $paymentId,
        string $signature,
        float $amount,
        array $payload = [],
        string $status = 'captured',
    ): Payment {
        return DB::transaction(function () use ($order, $gatewayOrderId, $paymentId, $signature, $amount, $payload, $status) {
            $payment = Payment::updateOrCreate(
                ['gateway_payment_id' => $paymentId],
                [
                    'order_id' => $order->id,
                    'gateway' => 'razorpay',
                    'gateway_order_id' => $gatewayOrderId,
                    'gateway_signature' => $signature,
                    'amount' => $amount,
                    'currency' => config('services.razorpay.currency', 'INR'),
                    'status' => $status,
                    'method' => $payload['method'] ?? null,
                    'payload' => $payload,
                    'paid_at' => $status === 'captured' ? now() : null,
                ]
            );

            if ($status === 'captured') {
                $this->markPaid($order);
            }

            return $payment;
        });
    }

    /**
     * Total the order up from its captured payments and set its state.
     */
    public function markPaid(Order $order): void
    {
        $order->refresh();

        $paid = (float) $order->payments()->where('status', 'captured')->sum('amount');
        $total = (float) $order->grand_total;

        $status = match (true) {
            $paid >= $total - 0.01 => 'paid',
            $paid > 0 => 'partly_paid',
            default => 'pending',
        };

        $wasPending = $order->status === 'pending';

        $order->update([
            'amount_paid' => $paid,
            'amount_due' => max(0, round($total - $paid, 2)),
            'payment_status' => $status,
            'status' => $wasPending ? 'confirmed' : $order->status,
            'confirmed_at' => $order->confirmed_at ?? now(),
        ]);

        // The payment moved the order, so the history has to say so — the
        // workflow is not involved when a gateway confirms an order.
        if ($wasPending) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => 'pending',
                'to_status' => 'confirmed',
                'note' => 'Payment received.',
                'customer_notified' => true,
            ]);
        }

        // The stock held for this order now leaves inventory for good.
        if ($order->reservations()->where('status', 'held')->exists()) {
            $this->stock->commit($order);
        }

        if ($status === 'paid') {
            $this->sendConfirmation($order);
        }
    }

    /**
     * One confirmation per order, however many times payment events arrive.
     * A mail failure must never roll back a payment that really happened.
     */
    public function sendConfirmation(Order $order): void
    {
        if ($order->confirmation_sent_at !== null) {
            return;
        }

        // Claim the send first: a duplicate webhook arriving at the same moment
        // then finds the column already set.
        $claimed = Order::whereKey($order->id)
            ->whereNull('confirmation_sent_at')
            ->update(['confirmation_sent_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        try {
            Mail::to($order->customer_email)->send(new OrderConfirmation($order->fresh('items')));
        } catch (\Throwable $e) {
            Log::error('Order confirmation email failed', [
                'order' => $order->order_number,
                'error' => $e->getMessage(),
            ]);

            // Let it be retried rather than silently never sending.
            Order::whereKey($order->id)->update(['confirmation_sent_at' => null]);
        }
    }

    /**
     * A COD order with no advance is confirmed on placement.
     */
    public function confirmCashOnDelivery(Order $order): void
    {
        $wasPending = $order->status === 'pending';

        $order->update([
            'payment_status' => 'pending',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'amount_due' => $order->grand_total,
        ]);

        if ($wasPending) {
            OrderStatusHistory::create([
                'order_id' => $order->id,
                'from_status' => 'pending',
                'to_status' => 'confirmed',
                'note' => 'Cash on delivery — confirmed on placement.',
                'customer_notified' => true,
            ]);
        }

        Payment::updateOrCreate(
            ['order_id' => $order->id, 'gateway' => 'cod', 'gateway_payment_id' => null],
            ['amount' => $order->grand_total, 'status' => 'created', 'payload' => ['note' => 'Collect on delivery']]
        );

        $this->stock->commit($order);
        $this->sendConfirmation($order);
    }

    /**
     * Payment failed or was abandoned: free the stock and the coupon.
     */
    public function fail(Order $order, ?string $reason = null): void
    {
        $order->update([
            'payment_status' => 'failed',
            'notes' => trim(($order->notes ?? '').' '.($reason ? '[Payment failed: '.$reason.']' : '')),
        ]);

        $this->stock->release($order);

        Log::info('Order payment failed', ['order' => $order->order_number, 'reason' => $reason]);
    }

    /**
     * Cancelling belongs to the workflow, which decides whether an order may
     * be cancelled at all and writes the history entry. This stays as the
     * door people already knock on, but it opens onto the same room — two
     * ways to cancel an order would drift apart within a month.
     */
    public function cancel(Order $order, ?string $reason = null): void
    {
        app(OrderWorkflow::class)->moveTo($order, 'cancelled', $reason, actor: null, notifyCustomer: false);
    }
}
