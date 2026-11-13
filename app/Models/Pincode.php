<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pincode extends Model
{
    use HasFactory;

    protected $fillable = [
        'pincode',
        'city',
        'state',
        'delivery_zone_id',
        'delivery_days',
        'is_serviceable',
        'cod_available',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'delivery_days' => 'integer',
            'is_serviceable' => 'boolean',
            'cod_available' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }

    public function scopeServiceable(Builder $query): Builder
    {
        return $query->where('is_serviceable', true);
    }

    /**
     * Pincode-level override wins, otherwise the zone default.
     */
    public function getEffectiveDeliveryDaysAttribute(): int
    {
        return $this->delivery_days ?? $this->zone?->delivery_days ?? 7;
    }

    public function getEffectiveCodAttribute(): bool
    {
        return $this->cod_available && ($this->zone?->cod_available ?? true);
    }
}
