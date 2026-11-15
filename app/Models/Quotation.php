<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Quotation extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'expired' => 'Expired',
        'converted' => 'Became an order',
    ];

    /** Nothing further happens from these. */
    public const FINAL = ['declined', 'converted'];

    protected $fillable = [
        'number', 'public_token', 'enquiry_id', 'customer_id',
        'customer_name', 'customer_email', 'customer_phone', 'gst_number',
        'shipping_address', 'billing_address',
        'status', 'valid_until',
        'subtotal', 'discount', 'delivery_charge', 'tax_total', 'grand_total',
        'is_interstate', 'place_of_supply', 'place_of_supply_code',
        'delivery_days', 'terms', 'notes', 'customer_note',
        'created_by', 'order_id',
        'sent_at', 'accepted_at', 'declined_at', 'decline_reason',
    ];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'is_interstate' => 'boolean',
            'delivery_days' => 'integer',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['number', 'status', 'grand_total', 'valid_until'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('quotations');
    }

    public function getRouteKeyName(): string
    {
        return 'public_token';
    }

    protected static function booted(): void
    {
        static::creating(function (self $quotation) {
            $quotation->public_token ??= Str::lower(Str::random(32));
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', self::FINAL);
    }

    /**
     * Sent, still unanswered, and past its date.
     *
     * Expiry is worked out rather than stored, so a quotation does not sit
     * looking live until some scheduled job happens to run.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLapsed(Builder $query): Builder
    {
        return $query->where('status', 'sent')
            ->whereNotNull('valid_until')
            ->whereDate('valid_until', '<', now()->toDateString());
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true) && ! $this->hasLapsed();
    }

    public function hasLapsed(): bool
    {
        return $this->status === 'sent'
            && $this->valid_until !== null
            && $this->valid_until->isBefore(now()->startOfDay());
    }

    /**
     * What the customer may still do with it.
     */
    public function isAnswerable(): bool
    {
        return $this->status === 'sent' && ! $this->hasLapsed();
    }

    public function isConvertible(): bool
    {
        return $this->status === 'accepted' && $this->order_id === null;
    }

    /**
     * The status to show, which is not always the one stored: a sent
     * quotation whose date has passed reads as expired without anything
     * having to write to it.
     */
    public function getEffectiveStatusAttribute(): string
    {
        return $this->hasLapsed() ? 'expired' : $this->status;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->effective_status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->effective_status) {
            'accepted' => 'success',
            'converted' => 'success',
            'declined' => 'danger',
            'expired' => 'secondary',
            'sent' => 'primary',
            default => 'warning',
        };
    }

    public function getDaysLeftAttribute(): ?int
    {
        if (! $this->valid_until || ! $this->isAnswerable()) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->valid_until, false);
    }
}
