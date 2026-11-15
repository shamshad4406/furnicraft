<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id', 'product_id', 'product_variant_id', 'quantity', 'unit_price',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * Today's price, which may differ from what was captured when this line
     * was added. The cart screen shows both.
     */
    public function getCurrentPriceAttribute(): float
    {
        return (float) ($this->variant?->effective_price ?? $this->product?->effective_price ?? $this->unit_price);
    }

    public function getPriceChangedAttribute(): bool
    {
        return abs($this->current_price - (float) $this->unit_price) >= 0.01;
    }

    public function getLineSubtotalAttribute(): float
    {
        return round($this->current_price * $this->quantity, 2);
    }

    /**
     * Stock available for this exact line, variant-aware.
     */
    public function getAvailableStockAttribute(): int
    {
        return (int) ($this->variant?->stock_quantity ?? $this->product?->stock_quantity ?? 0);
    }

    public function getLabelAttribute(): ?string
    {
        return $this->variant?->label;
    }

    public function getWeightAttribute(): float
    {
        return (float) ($this->product?->weight ?? 0) * $this->quantity;
    }
}
