<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'active' => 'Active',
        'inactive' => 'Inactive',
    ];

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'material',
        'color',
        'dimensions',
        'weight',
        'warranty',
        'hsn_code',
        'gst_rate',
        'price',
        'sale_price',
        'stock_quantity',
        'low_stock_threshold',
        'cost_price',
        'meta_title',
        'meta_description',
        'custom_order_allowed',
        'is_featured',
        'is_best_seller',
        'is_new_arrival',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'weight' => 'decimal:2',
            'stock_quantity' => 'integer',
            'sort_order' => 'integer',
            'custom_order_allowed' => 'boolean',
            'is_featured' => 'boolean',
            'is_best_seller' => 'boolean',
            'is_new_arrival' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'sku', 'slug', 'category_id', 'brand_id', 'price', 'sale_price', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('catalog');
    }

    /* ------------------------------------------------------- relations */

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class)->orderBy('sort_order')->orderBy('id');
    }

    /* --------------------------------------------------------- scopes */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderByDesc('id');
    }

    /**
     * Free-text search across the fields staff actually look things up by.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $q) use ($like) {
            $q->where('name', 'like', $like)
                ->orWhere('sku', 'like', $like)
                ->orWhere('slug', 'like', $like)
                ->orWhere('material', 'like', $like)
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $like));
        });
    }

    /* ------------------------------------------------------ accessors */

    /**
     * Sale price when one is set, otherwise the list price.
     */
    public function getEffectivePriceAttribute(): float
    {
        return (float) ($this->sale_price ?? $this->price);
    }

    public function getDiscountPercentAttribute(): ?int
    {
        if (! $this->sale_price || (float) $this->price <= 0) {
            return null;
        }

        $percent = (1 - ((float) $this->sale_price / (float) $this->price)) * 100;

        return $percent > 0 ? (int) round($percent) : null;
    }

    public function getHasVariantsAttribute(): bool
    {
        return $this->variants_count !== null
            ? $this->variants_count > 0
            : $this->variants()->exists();
    }

    /**
     * Total stock: the sum of variant stock when the product has variants,
     * otherwise the product's own quantity. Phase 7 replaces this with the
     * inventory module.
     */
    public function getTotalStockAttribute(): int
    {
        if ($this->relationLoaded('variants') && $this->variants->isNotEmpty()) {
            return (int) $this->variants->sum('stock_quantity');
        }

        $variantStock = $this->variants()->sum('stock_quantity');

        return $this->variants()->exists() ? (int) $variantStock : (int) $this->stock_quantity;
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function hasRating(): bool
    {
        return $this->rating_count > 0;
    }

    /**
     * The level below which this product needs reordering.
     *
     * Its own figure if it has one, otherwise the shop-wide default: a
     * three-piece sofa set and a pack of screws should not share a threshold,
     * but most products never need one of their own.
     */
    public function getEffectiveLowStockThresholdAttribute(): int
    {
        return (int) ($this->low_stock_threshold ?? settings('low_stock_threshold', 5));
    }

    public function isLowStock(): bool
    {
        return $this->total_stock <= $this->effective_low_stock_threshold;
    }

    public function isOutOfStock(): bool
    {
        return $this->total_stock <= 0;
    }

    /**
     * Products at or below their reorder level, worst first.
     *
     * Variants are summed in SQL rather than in PHP, so this stays one query
     * however many products the shop has.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLowStock(Builder $query): Builder
    {
        $default = (int) settings('low_stock_threshold', 5);

        // Both sides of the comparison are aliases: MySQL refuses a bare
        // column in HAVING when there is no GROUP BY.
        return $query
            ->selectRaw('products.*,
                COALESCE((
                    SELECT SUM(pv.stock_quantity) FROM product_variants pv
                    WHERE pv.product_id = products.id
                ), products.stock_quantity) as effective_stock,
                COALESCE(products.low_stock_threshold, ?) as effective_threshold', [$default])
            ->where('products.status', 'active')
            ->havingRaw('effective_stock <= effective_threshold')
            ->orderByRaw('effective_stock ASC');
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        $image = $this->relationLoaded('images')
            ? ($this->images->firstWhere('is_primary', true) ?? $this->images->first())
            : ($this->primaryImage ?? $this->images()->first());

        return $image?->thumbnail_url;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'inactive' => 'secondary',
            default => 'warning',
        };
    }
}
