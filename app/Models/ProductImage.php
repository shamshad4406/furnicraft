<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\BundledArt;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'path',
        'thumbnail_path',
        'original_name',
        'alt_text',
        'width',
        'height',
        'size',
        'sort_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getUrlAttribute(): ?string
    {
        return $this->resolve($this->path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->resolve($this->thumbnail_path) ?? $this->url;
    }

    /**
     * Bundled demo artwork lives in public/images; uploads live on the
     * public disk.
     */
    protected function resolve(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (BundledArt::is($path)) {
            return BundledArt::exists($path) ? BundledArt::url($path) : null;
        }

        return Storage::disk('public')->exists($path)
            ? Storage::disk('public')->url($path)
            : null;
    }

    /**
     * Remove the stored files when the row goes.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $image) {
            foreach (array_filter([$image->path, $image->thumbnail_path]) as $path) {
                // Never delete artwork that ships with the application.
                if (BundledArt::is($path)) {
                    continue;
                }

                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        });
    }
}
