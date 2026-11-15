<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponUsage;

class CouponService
{
    /**
     * Find a coupon by code, whatever case the customer typed.
     */
    public function find(string $code): ?Coupon
    {
        return Coupon::whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))])->first();
    }

    /**
     * Every reason a coupon might not apply, checked in the order a customer
     * would find most useful.
     *
     * @return array{ok: bool, message: string}
     */
    public function validate(Coupon $coupon, float $subtotal, ?int $customerId = null, ?string $email = null): array
    {
        if (! $coupon->is_active) {
            return $this->fail('This coupon is no longer active.');
        }

        if (! $coupon->hasStarted()) {
            return $this->fail('This coupon is not valid yet. It starts on '.$coupon->starts_at->format('d M Y').'.');
        }

        if ($coupon->hasExpired()) {
            return $this->fail('This coupon expired on '.$coupon->expires_at->format('d M Y').'.');
        }

        if ($coupon->isExhausted()) {
            return $this->fail('This coupon has been fully claimed.');
        }

        if ($subtotal < (float) $coupon->min_order_value) {
            return $this->fail('Spend '.money($coupon->min_order_value).' or more to use this coupon.');
        }

        if ($coupon->usage_limit_per_customer !== null) {
            $used = $this->timesUsedBy($coupon, $customerId, $email);

            if ($used >= $coupon->usage_limit_per_customer) {
                return $this->fail($coupon->usage_limit_per_customer === 1
                    ? 'You have already used this coupon.'
                    : 'You have used this coupon the maximum number of times.');
            }
        }

        return ['ok' => true, 'message' => $coupon->summary.' applied.'];
    }

    /**
     * Record the redemption. Called once the order is actually placed.
     */
    public function recordUsage(Coupon $coupon, float $discount, ?int $customerId, ?string $email, ?int $orderId): void
    {
        CouponUsage::create([
            'coupon_id' => $coupon->id,
            'customer_id' => $customerId,
            'order_id' => $orderId,
            'email' => $email,
            'discount' => $discount,
        ]);

        $coupon->increment('used_count');
    }

    /**
     * An order that never got paid releases its redemption.
     */
    public function releaseUsage(int $orderId): void
    {
        CouponUsage::where('order_id', $orderId)->get()->each(function (CouponUsage $usage) {
            Coupon::whereKey($usage->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
            $usage->delete();
        });
    }

    protected function timesUsedBy(Coupon $coupon, ?int $customerId, ?string $email): int
    {
        $query = CouponUsage::where('coupon_id', $coupon->id);

        if ($customerId) {
            return (int) $query->where('customer_id', $customerId)->count();
        }

        if ($email) {
            return (int) $query->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->count();
        }

        return 0;
    }

    /**
     * @return array{ok: bool, message: string}
     */
    protected function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message];
    }
}
