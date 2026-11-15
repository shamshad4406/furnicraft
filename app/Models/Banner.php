<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Banner extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const PLACEMENTS = [
        'hero' => 'Homepage slider',
        'promo' => 'Promotional block',
        'strip' => 'Thin strip',
    ];

    protected $fillable = [
        'placement', 'title', 'highlight', 'subtitle',
        'cta_label', 'cta_url',
        'image_path', 'mobile_image_path', 'image_alt',
        'starts_at', 'ends_at', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'click_count' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'placement', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('banners');
    }

    /**
     * Banners that should be on screen right now.
     *
     * Scheduling is applied in the query rather than filtered afterwards, so
     * a banner whose run has ended simply stops appearing without anybody
     * having to switch it off.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query, ?string $placement = null): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->when($placement, fn (Builder $q) => $q->where('placement', $placement))
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function isLive(): bool
    {
        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->isPast())
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Why it is not showing, in words somebody can act on.
     */
    public function getStateLabelAttribute(): string
    {
        return match (true) {
            ! $this->is_active => 'Off',
            $this->ends_at !== null && $this->ends_at->isPast() => 'Finished',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'Scheduled',
            default => 'Live',
        };
    }

    public function getStateBadgeAttribute(): string
    {
        return match ($this->state_label) {
            'Live' => 'success',
            'Scheduled' => 'info',
            'Finished' => 'secondary',
            default => 'warning',
        };
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }

    public function getMobileImageUrlAttribute(): ?string
    {
        return $this->mobile_image_path
            ? Storage::disk('public')->url($this->mobile_image_path)
            : $this->image_url;
    }

    public function getPlacementLabelAttribute(): string
    {
        return self::PLACEMENTS[$this->placement] ?? $this->placement;
    }
}
