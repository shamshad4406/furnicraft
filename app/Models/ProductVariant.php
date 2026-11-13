<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'size',
        'color',
        'price',
        'sale_price',
        'stock_quantity',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Variant price falls back to the parent product's price.
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->sale_price !== null) {
            return (float) $this->sale_price;
        }

        if ($this->price !== null) {
            return (float) $this->price;
        }

        return (float) ($this->product?->effective_price ?? 0);
    }

    public function getLabelAttribute(): string
    {
        $parts = array_filter([$this->size, $this->color]);

        return $parts ? implode(' / ', $parts) : $this->sku;
    }
}
