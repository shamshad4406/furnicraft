<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Review extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const STATUSES = [
        'pending' => 'Waiting to be read',
        'approved' => 'Published',
        'rejected' => 'Not published',
        'spam' => 'Spam',
    ];

    protected $fillable = [
        'product_id', 'customer_id', 'order_id',
        'name', 'email', 'city',
        'rating', 'title', 'body',
        'status', 'is_verified', 'is_featured',
        'reply', 'replied_at',
        'moderation_note', 'moderated_by', 'moderated_at',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_verified' => 'boolean',
            'is_featured' => 'boolean',
            'helpful_count' => 'integer',
            'replied_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'rating', 'is_featured'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('reviews');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }

    /** Only these are ever shown to the public. */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isPublished(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * The name to print. Surnames are shortened to an initial — a review is
     * public, and somebody leaving one did not ask to be searchable by their
     * full name.
     */
    public function getDisplayNameAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        if (count($parts) < 2) {
            return $this->name;
        }

        return $parts[0].' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'approved' => 'success',
            'rejected' => 'secondary',
            'spam' => 'danger',
            default => 'warning',
        };
    }

    /** Whole stars, for the simple star row. */
    public function getStarsAttribute(): int
    {
        return max(1, min(5, (int) $this->rating));
    }
}
