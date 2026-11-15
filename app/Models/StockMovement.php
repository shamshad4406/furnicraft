<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the stock ledger. Written only by StockLedger, and never
 * edited afterwards — a ledger you can go back and change is not a ledger.
 */
class StockMovement extends Model
{
    protected $fillable = [
        'product_id', 'product_variant_id', 'type',
        'quantity', 'balance_after',
        'reference_type', 'reference_id', 'reference_label',
        'unit_cost', 'reason', 'user_id', 'user_name',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'balance_after' => 'integer',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeInward(Builder $query): Builder
    {
        return $query->where('quantity', '>', 0);
    }

    /** @param  Builder<self>  $query */
    public function scopeOutward(Builder $query): Builder
    {
        return $query->where('quantity', '<', 0);
    }

    public function getTypeLabelAttribute(): string
    {
        return \App\Services\StockLedger::TYPES[$this->type] ?? $this->type;
    }

    public function getActorAttribute(): string
    {
        return $this->user?->name ?? $this->user_name ?? 'System';
    }

    /**
     * Movements in are green, out are red — the only thing anyone reads at a
     * glance down a ledger.
     */
    public function getToneAttribute(): string
    {
        return $this->quantity > 0 ? 'success' : 'danger';
    }

    public function getSignedQuantityAttribute(): string
    {
        return ($this->quantity > 0 ? '+' : '').$this->quantity;
    }
}
