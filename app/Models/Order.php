<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Order extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const STATUSES = [
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        'returned' => 'Returned',
    ];

    public const PAYMENT_STATUSES = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'partly_paid' => 'Part paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ];

    protected $fillable = [
        'order_number', 'invoice_number', 'idempotency_key', 'public_token', 'customer_id',
        'customer_name', 'customer_email', 'customer_phone', 'gst_number',
        'shipping_address', 'billing_address', 'shipping_pincode',
        'subtotal', 'discount', 'delivery_charge', 'tax_total', 'grand_total',
        'amount_paid', 'amount_due',
        'is_interstate', 'place_of_supply', 'place_of_supply_code', 'seller_gst_number',
        'coupon_id', 'coupon_code',
        'payment_method', 'payment_status', 'status',
        'delivery_days', 'notes',
        'placed_at', 'confirmed_at', 'confirmation_sent_at', 'invoiced_at',
        'cancelled_at', 'delivered_at', 'cancellation_reason', 'cancelled_by',
    ];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_due' => 'decimal:2',
            'delivery_days' => 'integer',
            'is_interstate' => 'boolean',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'invoiced_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['order_number', 'status', 'payment_status', 'grand_total', 'amount_paid'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('orders');
    }

    /**
     * URLs address an order by its token, never by the order number: the
     * number is an admin-configurable display format that may contain
     * slashes, and a token cannot be guessed by counting.
     */
    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            $order->public_token ??= Str::lower(Str::random(32));
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(StockReservation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->oldest('id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->latest('id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(OrderReturn::class)->latest('id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeForCustomer(Builder $query, ?int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * Whether the customer may still call this off themselves.
     *
     * Once it is being prepared or on a van, cancelling is a conversation
     * with staff, not a button — so it stops at "confirmed".
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true);
    }

    /**
     * Returns are only possible once the goods have actually arrived, and
     * only within the window the shop allows.
     */
    public function isReturnable(): bool
    {
        if ($this->status !== 'delivered' || ! $this->delivered_at) {
            return false;
        }

        $window = (int) settings('return_window_days', 7);

        return $window > 0 && $this->delivered_at->copy()->addDays($window)->isFuture();
    }

    public function returnWindowEndsOn(): ?\Illuminate\Support\Carbon
    {
        if (! $this->delivered_at) {
            return null;
        }

        return $this->delivered_at->copy()->addDays((int) settings('return_window_days', 7));
    }

    public function hasInvoice(): bool
    {
        return filled($this->invoice_number);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getPaymentLabelAttribute(): string
    {
        return self::PAYMENT_STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'delivered' => 'success',
            'cancelled', 'returned' => 'danger',
            'shipped', 'processing' => 'info',
            'confirmed' => 'primary',
            default => 'warning',
        };
    }

    public function getPaymentBadgeAttribute(): string
    {
        return match ($this->payment_status) {
            'paid' => 'success',
            'failed' => 'danger',
            'partly_paid' => 'info',
            'refunded' => 'secondary',
            default => 'warning',
        };
    }
}
