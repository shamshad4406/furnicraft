<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Address extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPES = ['home' => 'Home', 'office' => 'Office', 'other' => 'Other'];

    protected $fillable = [
        'customer_id', 'type', 'label', 'name', 'phone', 'alternate_phone',
        'line1', 'line2', 'landmark', 'city', 'state', 'pincode',
        'is_default_shipping', 'is_default_billing',
    ];

    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Flattened copy stored on the order, so later edits here never rewrite
     * what was actually delivered to.
     *
     * @return array<string, string|null>
     */
    public function toSnapshot(): array
    {
        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'landmark' => $this->landmark,
            'city' => $this->city,
            'state' => $this->state,
            'pincode' => $this->pincode,
            'type' => $this->type,
        ];
    }

    public function getSingleLineAttribute(): string
    {
        return collect([$this->line1, $this->line2, $this->landmark, $this->city, $this->state, $this->pincode])
            ->filter()
            ->implode(', ');
    }
}
