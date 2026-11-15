<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Pincode;

/**
 * Turns a cart into money. The single place that decides what anything costs.
 */
class PricingService
{
    public function __construct(protected CouponService $coupons) {}

    /**
     * @param  string|null  $pincode  when known, delivery is costed for real
     */
    public function totals(Cart $cart, ?string $pincode = null, ?string $customerEmail = null): CartTotals
    {
        $lines = [];
        $subtotal = 0.0;
        $weight = 0.0;
        $notices = [];

        foreach ($cart->items as $item) {
            if (! $item->product) {
                continue;
            }

            $unit = $item->current_price;
            $lineSubtotal = round($unit * $item->quantity, 2);
            $gstRate = (float) ($item->product->gst_rate ?? 0);

            // Prices include GST, so the tax is the portion inside the price.
            $tax = $gstRate > 0
                ? round($lineSubtotal - ($lineSubtotal / (1 + ($gstRate / 100))), 2)
                : 0.0;

            $lines[] = [
                'item' => $item,
                'product' => $item->product,
                'variant' => $item->variant,
                // Plain ids too: the stock service works from these without
                // needing the loaded models.
                'product_id' => $item->product_id,
                'product_variant_id' => $item->product_variant_id,
                'name' => $item->product->name,
                'sku' => $item->variant->sku ?? $item->product->sku,
                'variant_label' => $item->variant?->label,
                'hsn_code' => $item->product->hsn_code,
                'quantity' => $item->quantity,
                'unit_price' => $unit,
                'line_subtotal' => $lineSubtotal,
                'gst_rate' => $gstRate,
                'tax_amount' => $tax,
                'line_total' => $lineSubtotal,
                'weight' => (float) ($item->product->weight ?? 0) * $item->quantity,
            ];

            $subtotal += $lineSubtotal;
            $weight += (float) ($item->product->weight ?? 0) * $item->quantity;
        }

        $subtotal = round($subtotal, 2);

        /* ------------------------------------------------------- coupon */
        $discount = 0.0;
        $freeDelivery = false;
        $couponCode = null;

        if ($cart->coupon) {
            $check = $this->coupons->validate($cart->coupon, $subtotal, $cart->customer_id, $customerEmail);

            if ($check['ok']) {
                $couponCode = $cart->coupon->code;
                $freeDelivery = $cart->coupon->givesFreeDelivery();
                $discount = $cart->coupon->discountOn($subtotal);
            } else {
                $notices[] = 'Coupon '.$cart->coupon->code.' no longer applies: '.$check['message'];
            }
        }

        /* ----------------------------------------------------- delivery */
        $deliveryCharge = 0.0;
        $deliveryDays = null;

        $pincode = $pincode ?: $cart->pincode;

        if ($pincode) {
            $quote = $this->deliveryQuote($pincode, $weight, $subtotal - $discount);
            $deliveryCharge = $quote['charge'];
            $deliveryDays = $quote['days'];

            if ($quote['message']) {
                $notices[] = $quote['message'];
            }
        }

        if ($freeDelivery) {
            $deliveryCharge = 0.0;
        }

        /* -------------------------------------------------------- total */
        $grandTotal = round(max(0, $subtotal - $discount) + $deliveryCharge, 2);

        // Tax is proportional to what is actually paid for goods.
        $taxTotal = 0.0;
        foreach ($lines as $line) {
            $taxTotal += $line['tax_amount'];
        }

        if ($discount > 0 && $subtotal > 0) {
            $taxTotal = round($taxTotal * ((($subtotal - $discount) / $subtotal)), 2);
        }

        return new CartTotals(
            lines: $lines,
            subtotal: $subtotal,
            discount: round($discount, 2),
            deliveryCharge: round($deliveryCharge, 2),
            taxTotal: round($taxTotal, 2),
            grandTotal: $grandTotal,
            weight: round($weight, 2),
            couponCode: $couponCode,
            freeDelivery: $freeDelivery,
            deliveryDays: $deliveryDays,
            notices: $notices,
        );
    }

    /**
     * Delivery cost for a pincode, by zone and total weight.
     *
     * @return array{charge: float, days: int|null, message: string|null}
     */
    public function deliveryQuote(string $pincode, float $weight, float $goodsValue): array
    {
        $record = Pincode::with('zone')->where('pincode', $pincode)->first();

        if (! $record) {
            return ['charge' => 0.0, 'days' => null, 'message' => 'We have not mapped '.$pincode.' yet — we will confirm delivery by phone.'];
        }

        if (! $record->is_serviceable) {
            return ['charge' => 0.0, 'days' => null, 'message' => 'We do not deliver to '.$record->city.' yet.'];
        }

        $zone = $record->zone;

        if (! $zone) {
            return ['charge' => 0.0, 'days' => $record->effective_delivery_days, 'message' => null];
        }

        if ($zone->free_above !== null && $goodsValue >= (float) $zone->free_above) {
            return ['charge' => 0.0, 'days' => $record->effective_delivery_days, 'message' => null];
        }

        $charge = (float) $zone->base_charge + ((float) $zone->per_kg_charge * $weight);

        return [
            'charge' => round($charge, 2),
            'days' => $record->effective_delivery_days,
            'message' => null,
        ];
    }

    /**
     * Cash on delivery needs an advance once the order passes the limit the
     * admin set.
     */
    public function codAdvance(float $grandTotal): float
    {
        $limit = (float) settings('cod_advance_limit', 0);
        $percent = (float) settings('cod_advance_percent', 0);

        if ($limit <= 0 || $grandTotal <= $limit || $percent <= 0) {
            return 0.0;
        }

        return round(min($grandTotal, $grandTotal * ($percent / 100)), 2);
    }

    public function codAllowed(?string $pincode): bool
    {
        if (! settings()->bool('cod_enabled', true)) {
            return false;
        }

        if (! $pincode) {
            return true;
        }

        $record = Pincode::with('zone')->where('pincode', $pincode)->first();

        return $record ? $record->effective_cod : true;
    }
}
