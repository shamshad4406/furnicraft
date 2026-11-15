<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Coupon extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const TYPES = [
        'percentage' => 'Percentage off',
        'fixed' => 'Fixed amount off',
        'free_delivery' => 'Free delivery',
    ];

    protected $fillable = [
        'code', 'description', 'type', 'value', 'max_discount', 'min_order_value',
        'usage_limit', 'usage_limit_per_customer', 'starts_at', 'expires_at', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_order_value' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'type', 'value', 'is_active', 'expires_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('coupons');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Within its date window right now.
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->active()
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || $this->starts_at->isPast();
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    /**
     * Discount this coupon gives on a subtotal, before delivery.
     */
    public function discountOn(float $subtotal): float
    {
        $discount = match ($this->type) {
            'percentage' => $subtotal * ((float) $this->value / 100),
            'fixed' => (float) $this->value,
            default => 0.0,
        };

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        // Never discount more than the order is worth.
        return round(min($discount, $subtotal), 2);
    }

    public function givesFreeDelivery(): bool
    {
        return $this->type === 'free_delivery';
    }

    public function getSummaryAttribute(): string
    {
        return match ($this->type) {
            'percentage' => rtrim(rtrim((string) $this->value, '0'), '.').'% off'
                .($this->max_discount ? ' (max '.money($this->max_discount).')' : ''),
            'fixed' => money($this->value).' off',
            default => 'Free delivery',
        };
    }
}
