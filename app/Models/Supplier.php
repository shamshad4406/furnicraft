<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Supplier extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'name', 'code', 'contact_person', 'email', 'phone', 'alternate_phone',
        'address', 'city', 'state', 'pincode',
        'gst_number', 'pan_number',
        'payment_terms_days', 'lead_time_days',
        'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'payment_terms_days' => 'integer',
            'lead_time_days' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'code', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('suppliers');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)->latest('id');
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function getSingleLineAttribute(): string
    {
        return collect([$this->address, $this->city, $this->state, $this->pincode])
            ->filter()
            ->implode(', ');
    }

    /**
     * What we still owe on orders placed but not yet settled, roughly: the
     * value of everything ordered and not cancelled.
     */
    public function getOutstandingValueAttribute(): float
    {
        return (float) $this->purchaseOrders()
            ->whereIn('status', ['ordered', 'partial'])
            ->sum('grand_total');
    }
}
