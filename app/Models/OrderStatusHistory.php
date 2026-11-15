<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    protected $fillable = [
        'order_id', 'from_status', 'to_status', 'note',
        'changed_by', 'changed_by_name', 'customer_notified',
    ];

    protected function casts(): array
    {
        return ['customer_notified' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Who made the change, falling back to the name kept at the time so a
     * deleted staff account does not erase the record.
     */
    public function getActorAttribute(): string
    {
        return $this->changedBy?->name
            ?? $this->changed_by_name
            ?? 'System';
    }
}
