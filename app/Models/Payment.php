<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'gateway', 'gateway_order_id', 'gateway_payment_id', 'gateway_signature',
        'amount', 'currency', 'status', 'method', 'failure_reason', 'payload', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * The signature is proof of a verified callback; it is never displayed.
     */
    protected $hidden = ['gateway_signature', 'payload'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isCaptured(): bool
    {
        return $this->status === 'captured';
    }
}
