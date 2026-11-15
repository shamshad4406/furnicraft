<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class OrderReturn extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const TYPES = [
        'return' => 'Return & refund',
        'replacement' => 'Replacement',
        'warranty' => 'Warranty claim',
    ];

    public const STATUSES = [
        'requested' => 'Requested',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'picked_up' => 'Picked up',
        'received' => 'Received back',
        'refunded' => 'Refunded',
        'replaced' => 'Replaced',
        'closed' => 'Closed',
    ];

    /** Reasons a customer can pick, so the list stays reportable. */
    public const REASONS = [
        'damaged' => 'Arrived damaged',
        'defective' => 'Faulty or not working',
        'wrong_item' => 'Wrong item delivered',
        'not_as_described' => 'Not as described',
        'missing_parts' => 'Parts missing',
        'size_issue' => 'Does not fit the space',
        'changed_mind' => 'Changed my mind',
        'other' => 'Something else',
    ];

    /** Nothing further happens from these. */
    public const FINAL = ['rejected', 'refunded', 'replaced', 'closed'];

    protected $fillable = [
        'order_id', 'customer_id', 'reference', 'public_token',
        'type', 'status', 'reason', 'details',
        'refund_amount', 'refund_method', 'refund_reference',
        'staff_note', 'customer_message',
        'resolved_by', 'requested_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'refund_amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['reference', 'status', 'type', 'refund_amount'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('returns');
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    protected static function booted(): void
    {
        static::creating(function (self $return) {
            $return->public_token ??= Str::lower(Str::random(32));
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderReturnItem::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, self::FINAL, true);
    }

    public function getReasonLabelAttribute(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'refunded', 'replaced' => 'success',
            'rejected' => 'danger',
            'approved', 'picked_up', 'received' => 'info',
            'closed' => 'secondary',
            default => 'warning',
        };
    }
}
