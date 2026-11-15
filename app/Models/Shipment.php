<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Shipment extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const STATUSES = [
        'pending' => 'Ready to dispatch',
        'dispatched' => 'Dispatched',
        'in_transit' => 'In transit',
        'out_for_delivery' => 'Out for delivery',
        'delivered' => 'Delivered',
        'failed' => 'Delivery failed',
        'returned' => 'Returned to us',
    ];

    /** Statuses from which nothing more happens. */
    public const CLOSED = ['delivered', 'returned'];

    protected $fillable = [
        'order_id', 'consignment_number',
        'courier', 'tracking_number', 'tracking_url',
        'status', 'packages', 'weight', 'assigned_to',
        'expected_delivery_on', 'dispatched_at', 'delivered_at',
        'received_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'packages' => 'integer',
            'weight' => 'decimal:2',
            'expected_delivery_on' => 'date',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['consignment_number', 'status', 'courier', 'tracking_number'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('shipments');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED, true);
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'delivered' => 'success',
            'failed', 'returned' => 'danger',
            'out_for_delivery' => 'primary',
            'in_transit', 'dispatched' => 'info',
            default => 'warning',
        };
    }

    /**
     * Total units in this consignment, for the list view.
     */
    public function getQuantityAttribute(): int
    {
        return (int) $this->items->sum('quantity');
    }
}
