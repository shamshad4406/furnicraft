<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    use HasFactory;

    public const STATUSES = [
        'pending' => 'Waiting to confirm',
        'subscribed' => 'Subscribed',
        'unsubscribed' => 'Unsubscribed',
        'bounced' => 'Bouncing',
    ];

    protected $fillable = [
        'email', 'name', 'status', 'unsubscribe_token', 'confirm_token',
        'source', 'customer_id', 'ip_address',
        'confirmed_at', 'unsubscribed_at', 'unsubscribe_reason',
    ];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'unsubscribe_token';
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscriber) {
            // Every mail can then carry a one-click way out, which is both
            // the law in most places and the difference between a list and
            // a stream of complaints.
            $subscriber->unsubscribe_token ??= Str::lower(Str::random(40));
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Who may actually be sent to. */
    public function scopeMailable(Builder $query): Builder
    {
        return $query->where('status', 'subscribed');
    }

    public function isSubscribed(): bool
    {
        return $this->status === 'subscribed';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'subscribed' => 'success',
            'unsubscribed' => 'secondary',
            'bounced' => 'danger',
            default => 'warning',
        };
    }
}
