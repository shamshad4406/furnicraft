<?php

namespace App\Services;

/**
 * An immutable snapshot of what a cart costs. Every figure the customer sees,
 * and later every figure written onto the order, comes from here — so the
 * cart, the checkout summary, the Razorpay amount and the invoice can never
 * disagree.
 *
 * Prices are GST-inclusive, which is how furniture is sold in India. The tax
 * figure is therefore extracted from the price, not added on top.
 */
class CartTotals
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, string>  $notices
     */
    public function __construct(
        public readonly array $lines,
        public readonly float $subtotal,
        public readonly float $discount,
        public readonly float $deliveryCharge,
        public readonly float $taxTotal,
        public readonly float $grandTotal,
        public readonly float $weight,
        public readonly ?string $couponCode = null,
        public readonly bool $freeDelivery = false,
        public readonly ?int $deliveryDays = null,
        public readonly array $notices = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * Razorpay works in paise.
     */
    public function amountInPaise(): int
    {
        return (int) round($this->grandTotal * 100);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'delivery_charge' => $this->deliveryCharge,
            'tax_total' => $this->taxTotal,
            'grand_total' => $this->grandTotal,
            'weight' => $this->weight,
            'coupon_code' => $this->couponCode,
            'delivery_days' => $this->deliveryDays,
        ];
    }
}
