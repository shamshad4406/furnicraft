<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryReply extends Model
{
    public const CHANNELS = [
        'email' => 'Emailed',
        'phone' => 'Phone call',
        'whatsapp' => 'WhatsApp',
        'note' => 'Internal note',
    ];

    protected $fillable = [
        'enquiry_id', 'user_id', 'user_name', 'channel', 'body', 'emailed_at',
    ];

    protected function casts(): array
    {
        return ['emailed_at' => 'datetime'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A note is for staff only; everything else was said to the customer.
     */
    public function isInternal(): bool
    {
        return $this->channel === 'note';
    }

    public function getAuthorAttribute(): string
    {
        return $this->user?->name ?? $this->user_name ?? 'System';
    }

    public function getChannelLabelAttribute(): string
    {
        return self::CHANNELS[$this->channel] ?? $this->channel;
    }
}
