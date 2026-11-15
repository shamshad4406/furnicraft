<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class PurchaseOrder extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'ordered' => 'Ordered',
        'partial' => 'Part received',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ];

    /** Nothing further happens from these. */
    public const FINAL = ['received', 'cancelled'];

    protected $fillable = [
        'number', 'supplier_id', 'status', 'expected_on',
        'subtotal', 'tax_total', 'other_charges', 'grand_total',
        'supplier_invoice_number', 'notes', 'cancel_reason',
        'created_by', 'ordered_at', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'expected_on' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'other_charges' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'ordered_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'grand_total'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('purchases');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::FINAL);
    }

    /**
     * Ordered, due, and nothing has turned up.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', ['ordered', 'partial'])
            ->whereNotNull('expected_on')
            ->whereDate('expected_on', '<', now()->toDateString());
    }

    /**
     * Only a draft can be changed. Once it has gone to the supplier it is a
     * record of what was agreed, and editing it behind their back is how a
     * delivery arrives against lines nobody recognises.
     */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }

    public function isReceivable(): bool
    {
        return in_array($this->status, ['ordered', 'partial'], true);
    }

    public function isOverdue(): bool
    {
        return $this->isReceivable()
            && $this->expected_on !== null
            && $this->expected_on->isBefore(now()->startOfDay());
    }

    public function getOutstandingQuantityAttribute(): int
    {
        return (int) $this->items->sum(fn (PurchaseOrderItem $item) => $item->outstanding);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'received' => 'success',
            'cancelled' => 'danger',
            'partial' => 'info',
            'ordered' => 'primary',
            default => 'warning',
        };
    }
}
