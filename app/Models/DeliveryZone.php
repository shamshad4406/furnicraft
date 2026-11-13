<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'delivery_days',
        'base_charge',
        'per_kg_charge',
        'free_above',
        'cod_available',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'delivery_days' => 'integer',
            'base_charge' => 'decimal:2',
            'per_kg_charge' => 'decimal:2',
            'free_above' => 'decimal:2',
            'cod_available' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function pincodes(): HasMany
    {
        return $this->hasMany(Pincode::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
