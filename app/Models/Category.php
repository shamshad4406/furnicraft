<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\BundledArt;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Category extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'slug', 'parent_id', 'is_active', 'sort_order'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('catalog');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Products that are visible on the storefront.
     */
    public function activeProducts(): HasMany
    {
        return $this->hasMany(Product::class)->where('status', 'active');
    }

    /**
     * Active products in this category plus all of its sub-categories.
     * Needs `children` loaded with their activeProducts counts.
     */
    public function getTotalActiveProductsAttribute(): int
    {
        $own = (int) ($this->active_products_count ?? 0);

        if (! $this->relationLoaded('children')) {
            return $own;
        }

        return $own + (int) $this->children->sum(fn (self $child) => (int) ($child->active_products_count ?? 0));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        // Demo artwork shipped with the app, not a staff upload.
        if (BundledArt::is($this->image)) {
            return BundledArt::exists($this->image) ? BundledArt::url($this->image) : null;
        }

        return Storage::disk('public')->exists($this->image)
            ? Storage::disk('public')->url($this->image)
            : null;
    }

    /**
     * "Parent › Child" for list screens and breadcrumbs.
     */
    public function getFullNameAttribute(): string
    {
        return $this->parent ? $this->parent->name.' › '.$this->name : $this->name;
    }

    /**
     * Ids of this category and all of its descendants.
     *
     * @return int[]
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }

    /**
     * Options for a parent select, excluding the category being edited and its
     * own descendants so a cycle cannot be created.
     *
     * @return array<int, string>
     */
    public static function parentOptions(?self $except = null): array
    {
        $blocked = $except ? $except->descendantIds() : [];

        return self::query()
            ->roots()
            ->ordered()
            ->get()
            ->reject(fn (self $category) => in_array($category->id, $blocked, true))
            ->pluck('name', 'id')
            ->all();
    }
}
