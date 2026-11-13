<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        return $this->path && Storage::disk('public')->exists($this->path)
            ? Storage::disk('public')->url($this->path)
            : null;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if ($this->thumbnail_path && Storage::disk('public')->exists($this->thumbnail_path)) {
            return Storage::disk('public')->url($this->thumbnail_path);
        }

        return $this->url;
    }

    /**
     * Remove the stored files when the row goes.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $image) {
            foreach (array_filter([$image->path, $image->thumbnail_path]) as $path) {
                if (Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        });
    }
}
